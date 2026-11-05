<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\AiExtractionException;
use App\Service\AiExtractor;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class AiExtractorTest extends TestCase
{
    public function testExtractsJobAndInterview(): void
    {
        $content = json_encode([
            'job' => [
                'title' => 'Senior Engineer',
                'company' => 'Acme',
                'status' => 'in_progress',
                'descriptionUrl' => 'https://acme.example/jobs/senior-engineer',
                'descriptionHtml' => 'Owns the payments platform and mentors juniors.',
            ],
            'interview' => [
                'date' => '2026-09-20 15:00',
                'interviewers' => ['Jane Doe'],
                'notes' => 'Video call, link sent by email',
            ],
            'summary' => 'Recruiter reached out about a senior role.',
        ], JSON_THROW_ON_ERROR);

        $result = $this->extractorWithResponse($this->envelope($content))->extract('pasted message', 'job');

        $this->assertSame('Senior Engineer', $result['job']['title']);
        $this->assertSame('Acme', $result['job']['company']);
        $this->assertSame('in_progress', $result['job']['status']);
        $this->assertSame('https://acme.example/jobs/senior-engineer', $result['job']['descriptionUrl']);
        $this->assertSame('2026-09-20T15:00:00', $result['interview']['date']);
        $this->assertSame(['Jane Doe'], $result['interview']['interviewers']);
        $this->assertSame('Video call, link sent by email', $result['interview']['notes']);
        $this->assertSame('Recruiter reached out about a senior role.', $result['summary']);
    }

    public function testParsesContentInMarkdownCodeFence(): void
    {
        $content = "Sure, here is the data:\n```json\n{\"job\":{\"title\":\"Backend Dev\",\"company\":\"Globex\"},\"interview\":null,\"summary\":null}\n```";

        $result = $this->extractorWithResponse($this->envelope($content))->extract('pasted message', 'job');

        $this->assertSame('Backend Dev', $result['job']['title']);
        $this->assertSame('Globex', $result['job']['company']);
        $this->assertNull($result['interview']);
    }

    public function testReturnsNullInterviewWhenAllFieldsEmpty(): void
    {
        $content = json_encode([
            'job' => ['title' => 'Dev', 'company' => 'Initech'],
            'interview' => ['date' => null, 'interviewers' => [], 'notes' => null],
            'summary' => null,
        ], JSON_THROW_ON_ERROR);

        $result = $this->extractorWithResponse($this->envelope($content))->extract('pasted message', 'job');

        $this->assertNull($result['interview']);
    }

    public function testUnparseableInterviewDateBecomesNull(): void
    {
        $content = json_encode([
            'interview' => [
                'date' => 'next month sometime',
                'interviewers' => ['Jane Doe'],
                'notes' => null,
            ],
        ], JSON_THROW_ON_ERROR);

        $result = $this->extractorWithResponse($this->envelope($content))->extract('pasted message', 'interview');

        $this->assertNull($result['interview']['date']);
        $this->assertSame(['Jane Doe'], $result['interview']['interviewers']);
    }

    public function testInvalidStatusFallsBackToInvestigating(): void
    {
        $content = json_encode([
            'job' => ['title' => 'Dev', 'company' => 'Initech', 'status' => 'bogus'],
        ], JSON_THROW_ON_ERROR);

        $result = $this->extractorWithResponse($this->envelope($content))->extract('pasted message', 'job');

        $this->assertSame('investigating', $result['job']['status']);
    }

    public function testNoResponseStatusIsAccepted(): void
    {
        $content = json_encode([
            'job' => ['title' => 'Dev', 'company' => 'Initech', 'status' => 'no_response'],
        ], JSON_THROW_ON_ERROR);

        $result = $this->extractorWithResponse($this->envelope($content))->extract('pasted message', 'job');

        $this->assertSame('no_response', $result['job']['status']);
    }

    public function testInvalidUrlBecomesNull(): void
    {
        $content = json_encode([
            'job' => ['title' => 'Dev', 'company' => 'Initech', 'descriptionUrl' => 'not a url'],
        ], JSON_THROW_ON_ERROR);

        $result = $this->extractorWithResponse($this->envelope($content))->extract('pasted message', 'job');

        $this->assertNull($result['job']['descriptionUrl']);
    }

    public function testInterviewIntentIgnoresJobData(): void
    {
        $content = json_encode([
            'job' => ['title' => 'Dev', 'company' => 'Initech'],
            'interview' => ['date' => '2026-09-21T10:00:00', 'interviewers' => ['Bob'], 'notes' => null],
        ], JSON_THROW_ON_ERROR);

        $result = $this->extractorWithResponse($this->envelope($content))->extract('pasted message', 'interview');

        $this->assertNull($result['job']);
        $this->assertSame('2026-09-21T10:00:00', $result['interview']['date']);
    }

    public function testMissingApiKeyThrowsConfigurationProblem(): void
    {
        $extractor = new AiExtractor(
            $this->createMock(HttpClientInterface::class),
            '',
            ['test-model'],
        );

        try {
            $extractor->extract('pasted message', 'job');
            $this->fail('Expected AiExtractionException to be thrown.');
        } catch (AiExtractionException $e) {
            $this->assertTrue($e->isConfigurationProblem());
            $this->assertStringContainsString('OPENROUTER_API_KEY', $e->getMessage());
        }
    }

    public function testUpstreamHttpErrorCarriesMessage(): void
    {
        $body = json_encode(['error' => ['message' => 'Invalid API key']], JSON_THROW_ON_ERROR);

        try {
            $this->extractorWithResponse($body, 401)->extract('pasted message', 'job');
            $this->fail('Expected AiExtractionException to be thrown.');
        } catch (AiExtractionException $e) {
            $this->assertFalse($e->isConfigurationProblem());
            $this->assertStringContainsString('Invalid API key', $e->getMessage());
            $this->assertStringContainsString('401', $e->getMessage());
        }
    }

    public function testEmptyTextThrows(): void
    {
        $extractor = new AiExtractor(
            $this->createMock(HttpClientInterface::class),
            'test-api-key',
            ['test-model'],
        );

        try {
            $extractor->extract('  ', 'job');
            $this->fail('Expected AiExtractionException to be thrown.');
        } catch (AiExtractionException $e) {
            $this->assertStringContainsString('empty', $e->getMessage());
        }
    }

    public function testFallsBackToNextModelWhenARequestFails(): void
    {
        $content = json_encode(['job' => ['title' => 'Dev', 'company' => 'Initech']], JSON_THROW_ON_ERROR);
        $error = json_encode(['error' => ['message' => 'Model unavailable']], JSON_THROW_ON_ERROR);

        $models = [];
        $client = $this->createMock(HttpClientInterface::class);
        $client->expects($this->exactly(2))
            ->method('request')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(function ($options) use (&$models): bool {
                    $models[] = $this->payloadModel($options);

                    return true;
                }),
            )
            ->willReturnOnConsecutiveCalls(
                $this->response($error, 503),
                $this->response($this->envelope($content)),
            );

        $result = (new AiExtractor($client, 'test-api-key', ['primary-model', 'fallback-model']))
            ->extract('pasted message', 'job');

        $this->assertSame(['primary-model', 'fallback-model'], $models);
        $this->assertSame('Dev', $result['job']['title']);
    }

    public function testFallsBackWhenAModelReturnsUnparseableContent(): void
    {
        $content = json_encode(['job' => ['title' => 'Dev', 'company' => 'Initech']], JSON_THROW_ON_ERROR);

        $models = [];
        $client = $this->createMock(HttpClientInterface::class);
        $client->expects($this->exactly(3))
            ->method('request')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(function ($options) use (&$models): bool {
                    $models[] = $this->payloadModel($options);

                    return true;
                }),
            )
            ->willReturnOnConsecutiveCalls(
                $this->response($this->envelope('not json')),
                $this->response($this->envelope('still not json')),
                $this->response($this->envelope($content)),
            );

        $result = (new AiExtractor($client, 'test-api-key', ['first-model', 'second-model']))
            ->extract('pasted message', 'job');

        $this->assertSame(['first-model', 'first-model', 'second-model'], $models);
        $this->assertSame('Dev', $result['job']['title']);
    }

    public function testReportsEveryModelWhenAllFail(): void
    {
        $body = json_encode(['error' => ['message' => 'boom']], JSON_THROW_ON_ERROR);

        $client = $this->createMock(HttpClientInterface::class);
        $client->expects($this->exactly(2))
            ->method('request')
            ->willReturn($this->response($body, 500));

        try {
            (new AiExtractor($client, 'test-api-key', ['model-a', 'model-b']))->extract('pasted message', 'job');
            $this->fail('Expected AiExtractionException to be thrown.');
        } catch (AiExtractionException $e) {
            $this->assertFalse($e->isConfigurationProblem());
            $this->assertStringContainsString('model-a', $e->getMessage());
            $this->assertStringContainsString('model-b', $e->getMessage());
            $this->assertStringContainsString('500', $e->getMessage());
        }
    }

    public function testEmptyModelListThrowsConfigurationProblem(): void
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client->expects($this->never())->method('request');

        try {
            (new AiExtractor($client, 'test-api-key', []))->extract('pasted message', 'job');
            $this->fail('Expected AiExtractionException to be thrown.');
        } catch (AiExtractionException $e) {
            $this->assertTrue($e->isConfigurationProblem());
            $this->assertStringContainsString('OPENROUTER_MODELS', $e->getMessage());
        }
    }

    public function testBlankAndDuplicateModelsAreIgnored(): void
    {
        $content = json_encode(['job' => ['title' => 'Dev', 'company' => 'Initech']], JSON_THROW_ON_ERROR);

        $models = [];
        $client = $this->createMock(HttpClientInterface::class);
        $client->expects($this->once())
            ->method('request')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(function ($options) use (&$models): bool {
                    $models[] = $this->payloadModel($options);

                    return true;
                }),
            )
            ->willReturn($this->response($this->envelope($content)));

        (new AiExtractor($client, 'test-api-key', ['', '  ', 'test-model', 'test-model']))
            ->extract('pasted message', 'job');

        $this->assertSame(['test-model'], $models);
    }

    private function payloadModel(mixed $options): string
    {
        if (!is_array($options)) {
            return '';
        }

        $payload = $options['json'] ?? null;
        if (!is_array($payload)) {
            return '';
        }

        $model = $payload['model'] ?? null;

        return is_string($model) ? $model : '';
    }

    private function envelope(string $content): string
    {
        return json_encode([
            'choices' => [
                ['message' => ['content' => $content]],
            ],
        ], JSON_THROW_ON_ERROR);
    }

    private function extractorWithResponse(string $body, int $status = 200): AiExtractor
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willReturn($this->response($body, $status));

        return new AiExtractor($client, 'test-api-key', ['test-model']);
    }

    private function response(string $body, int $status = 200): ResponseInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($status);
        $response->method('getContent')->willReturn($body);
        $response->method('toArray')->willReturn(json_decode($body, true));

        return $response;
    }
}
