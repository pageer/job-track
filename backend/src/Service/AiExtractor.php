<?php

namespace App\Service;

use App\Enum\JobStatus;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Calls the OpenRouter chat completions API to turn a pasted recruiter
 * email / LinkedIn message into structured data for the app.
 */
class AiExtractor
{
    private const API_URL = 'https://openrouter.ai/api/v1/chat/completions';

    private const MAX_TEXT_LENGTH = 50000;

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private string $model,
    ) {
    }

    /**
     * Extracts job / interview details from a pasted message.
     *
     * @throws AiExtractionException when the feature is not configured or OpenRouter cannot be used.
     *
     * @return array{job: array<string, mixed>|null, interview: array<string, mixed>|null, summary: string|null}
     */
    public function extract(string $text, string $intent): array
    {
        $text = trim($text);
        if ('' === $text) {
            throw new AiExtractionException('The message is empty.');
        }
        if (strlen($text) > self::MAX_TEXT_LENGTH) {
            throw new AiExtractionException(sprintf('The message is too long (max %d characters).', self::MAX_TEXT_LENGTH));
        }
        if ('' === trim($this->apiKey)) {
            throw new AiExtractionException(
                'OpenRouter is not configured. Set the OPENROUTER_API_KEY environment variable to enable this feature.',
                true
            );
        }

        $decoded = $this->decodeJson($this->complete($text, $intent));
        if (null === $decoded) {
            // One retry with a stricter prompt before giving up.
            $decoded = $this->decodeJson($this->complete($text, $intent, true));
        }
        if (null === $decoded) {
            throw new AiExtractionException('OpenRouter returned an unparseable response. Please try again.');
        }

        return $this->normalize($decoded, $intent);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(string $text, string $intent, bool $strict): array
    {
        $system = $strict
            ? 'You extract structured data from recruiter messages for a job application tracker. '
            . 'IMPORTANT: reply with ONLY a single valid JSON object. No markdown, no code fences, no prose before or after.'
            : 'You extract structured data from recruiter emails and LinkedIn messages for a job application tracker. '
            . 'Reply with a single JSON object only - no markdown, no code fences, no commentary. '
            . 'Do not invent facts; use null for unknown values and [] for empty lists. '
            . 'Return exactly this schema: '
            . '{"job":{"title":"string|null - job title/position","company":"string|null - company name",'
            . '"status":"one of investigating|applied|in_progress|no_response|rejected|accepted - investigating unless the message clearly indicates otherwise",'
            . '"descriptionUrl":"string|null - job posting URL if mentioned in the message, otherwise null",'
            . '"descriptionHtml":"string|null - 1-3 plain-text sentences summarizing the role from the message, otherwise null"},'
            . '"interview":{"date":"string|null - ISO-8601 local datetime without timezone, e.g. 2026-09-20T15:00:00; '
            . "resolve relative phrases like 'this Friday at 2pm' to the upcoming matching date; null when no usable time is given\","
            . '"interviewers":["interviewer name, one string per person"],"notes":"string|null - format, dial-in link or other practical detail"},'
            . '"summary":"string|null - 1-2 plain sentences describing what the message is about"}';

        $user = sprintf(
            "intent: %s\n\nPasted message:\n\n%s",
            'interview' === $intent ? 'interview' : 'job',
            $text
        );

        return [
            'model' => $this->model,
            'temperature' => 0,
            'max_tokens' => 2000,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
        ];
    }

    /**
     * Calls OpenRouter and returns the raw completion content (not yet decoded).
     *
     * @throws AiExtractionException when the request fails or the response is unusable.
     */
    private function complete(string $text, string $intent, bool $strict = false): string
    {
        try {
            $response = $this->httpClient->request('POST', self::API_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $this->buildPayload($text, $intent, $strict),
                'timeout' => 60,
            ]);
        } catch (TransportExceptionInterface $e) {
            throw new AiExtractionException('Could not reach OpenRouter: ' . $e->getMessage());
        }

        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new AiExtractionException(
                sprintf('OpenRouter request failed (%d): %s', $statusCode, $this->errorMessage($this->safeContent($response)))
            );
        }

        $content = $this->extractContent($this->safeBody($response));
        if (null === $content) {
            throw new AiExtractionException('OpenRouter returned an unexpected response body.');
        }

        return $content;
    }

    /**
     * Returns the chat completion content as decoded JSON.
     *
     * @return array<string, mixed>|null
     */
    private function decodeJson(string $content): ?array
    {
        $raw = trim($content);
        if (!str_starts_with($raw, '{') || !str_ends_with($raw, '}')) {
            if (1 === preg_match('/\{.*\}/s', $raw, $matches)) {
                $raw = trim($matches[0]);
            }
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<mixed> $data
     */
    private function extractContent(array $data): ?string
    {
        $choice = $data['choices'][0] ?? null;
        if (!is_array($choice)) {
            return null;
        }

        $message = $choice['message'] ?? null;
        if (!is_array($message) || !is_string($message['content'] ?? null) || '' === trim($message['content'])) {
            return null;
        }

        return $message['content'];
    }

    /**
     * @return array<mixed>
     */
    private function safeBody(ResponseInterface $response): array
    {
        try {
            return $response->toArray(false);
        } catch (TransportExceptionInterface $e) {
            throw new AiExtractionException('Could not read the OpenRouter response: ' . $e->getMessage());
        }
    }

    private function safeContent(ResponseInterface $response): string
    {
        try {
            return $response->getContent(false);
        } catch (TransportExceptionInterface $e) {
            throw new AiExtractionException('Could not read the OpenRouter response: ' . $e->getMessage());
        }
    }

    private function errorMessage(string $body): string
    {
        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            $message = $decoded['error']['message'] ?? $decoded['message'] ?? null;
            if (is_string($message) && '' !== trim($message)) {
                return trim($message);
            }
        }

        $body = trim($body);
        if ('' === $body) {
            return 'no error message provided';
        }

        return iconv_substr($body, 0, 300);
    }

    /**
     * @param array<mixed> $data
     *
     * @return array{job: array<string, mixed>|null, interview: array<string, mixed>|null, summary: string|null}
     */
    private function normalize(array $data, string $intent): array
    {
        $jobData = $data['job'] ?? null;
        $interviewData = $data['interview'] ?? null;

        $job = null;
        if ('job' === $intent && is_array($jobData)) {
            $job = $this->normalizeJob($jobData);
        }

        $interview = is_array($interviewData) ? $this->normalizeInterview($interviewData) : null;
        if (null !== $interview && $this->isEmptyInterview($interview)) {
            $interview = null;
        }

        return [
            'job' => $job,
            'interview' => $interview,
            'summary' => $this->compact($data['summary'] ?? null),
        ];
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<string, mixed>
     */
    private function normalizeJob(array $data): array
    {
        $statusValue = is_string($data['status'] ?? null) ? $data['status'] : '';
        $status = JobStatus::tryFrom($statusValue) ?? JobStatus::Investigating;

        return [
            'title' => $this->compact($data['title'] ?? null, 255),
            'company' => $this->compact($data['company'] ?? null, 255),
            'status' => $status->value,
            'descriptionUrl' => $this->validUrl($data['descriptionUrl'] ?? null),
            'descriptionHtml' => $this->compact($data['descriptionHtml'] ?? null, 4000),
        ];
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<string, mixed>
     */
    private function normalizeInterview(array $data): array
    {
        return [
            'date' => $this->normalizeDate($data['date'] ?? null),
            'interviewers' => $this->parseList($data['interviewers'] ?? null),
            'notes' => $this->compact($data['notes'] ?? null, 2000),
        ];
    }

    /**
     * @param array<string, mixed> $interview
     */
    private function isEmptyInterview(array $interview): bool
    {
        return null === $interview['date']
            && [] === $interview['interviewers']
            && null === $interview['notes'];
    }

    private function normalizeDate(mixed $value): ?string
    {
        $value = $this->compact($value);
        if (null === $value) {
            return null;
        }

        try {
            $date = new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }

        return $date->format('Y-m-d\TH:i:s');
    }

    /**
     * @return list<string>
     */
    private function parseList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            $cleaned = $this->compact($item, 120);
            if (null !== $cleaned) {
                $items[] = $cleaned;
            }
            if (20 === count($items)) {
                break;
            }
        }

        return $items;
    }

    private function validUrl(mixed $value): ?string
    {
        $value = $this->compact($value, 2048);
        if (null === $value || false === filter_var($value, FILTER_VALIDATE_URL)) {
            return null;
        }

        return $value;
    }

    private function compact(mixed $value, int $maxLength = 0): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ('' === $value) {
            return null;
        }

        if ($maxLength > 0 && strlen($value) > $maxLength) {
            return substr($value, 0, $maxLength);
        }

        return $value;
    }
}
