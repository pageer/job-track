<?php

namespace App\Controller;

use App\Service\AiExtractionException;
use App\Service\AiExtractor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AiController extends AbstractController
{
    private const MAX_MESSAGE_LENGTH = 50000;

    public function __construct(private AiExtractor $aiExtractor)
    {
    }

    #[Route('/api/ai/extract', name: 'api_ai_extract', methods: ['POST'])]
    public function extract(Request $request): JsonResponse
    {
        $data = $request->toArray();

        $text = trim((string) ($data['text'] ?? ''));
        if ('' === $text) {
            return $this->json(['error' => 'A message to analyze is required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if (strlen($text) > self::MAX_MESSAGE_LENGTH) {
            return $this->json(['error' => 'The message is too long.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $intent = 'interview' === ($data['intent'] ?? null) ? 'interview' : 'job';

        try {
            $result = $this->aiExtractor->extract($text, $intent);
        } catch (AiExtractionException $e) {
            $status = $e->isConfigurationProblem()
                ? Response::HTTP_SERVICE_UNAVAILABLE
                : Response::HTTP_BAD_GATEWAY;

            return $this->json(['error' => $e->getMessage()], $status);
        }

        return $this->json(['intent' => $intent] + $result);
    }
}
