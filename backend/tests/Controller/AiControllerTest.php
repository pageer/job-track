<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\AiController;
use App\Service\AiExtractionException;
use App\Service\AiExtractor;
use Symfony\Component\HttpFoundation\Response;

final class AiControllerTest extends ControllerTestCase
{
    public function testExtractAnalyzesTheMessageWithJobIntent(): void
    {
        $extractor = $this->createMock(AiExtractor::class);
        $extractor->method('extract')->willReturn([
            'job' => ['title' => 'Backend Engineer'],
            'interview' => null,
            'summary' => 'Applied for a role.',
        ]);

        $controller = new AiController($extractor);
        $this->bindController($controller);

        $response = $controller->extract($this->jsonRequest('POST', '/api/ai/extract', [
            'text' => '  I applied for a Backend Engineer role at Acme.  ',
        ]));

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = $this->decodeJson($response);
        $this->assertSame('job', $data['intent']);
        $this->assertSame('Backend Engineer', $data['job']['title']);
        $this->assertSame('Applied for a role.', $data['summary']);
    }

    public function testExtractUnlocksInterviewIntent(): void
    {
        $extractor = $this->createMock(AiExtractor::class);
        $extractor->expects($this->once())->method('extract')->with('Interview tomorrow at 3pm.', 'interview')->willReturn([
            'job' => null,
            'interview' => ['date' => '2026-10-01T15:00:00'],
            'summary' => null,
        ]);

        $controller = new AiController($extractor);
        $this->bindController($controller);

        $response = $controller->extract($this->jsonRequest('POST', '/api/ai/extract', [
            'text' => 'Interview tomorrow at 3pm.',
            'intent' => 'interview',
        ]));

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = $this->decodeJson($response);
        $this->assertSame('interview', $data['intent']);
        $this->assertSame('2026-10-01T15:00:00', $data['interview']['date']);
    }

    public function testExtractRequiresText(): void
    {
        $extractor = $this->createMock(AiExtractor::class);
        $extractor->expects($this->never())->method('extract');

        $controller = new AiController($extractor);
        $this->bindController($controller);

        $response = $controller->extract($this->jsonRequest('POST', '/api/ai/extract', ['text' => '   ']));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('A message to analyze is required.', $this->decodeJson($response)['error']);
    }

    public function testExtractRejectsOverlyLongMessages(): void
    {
        $extractor = $this->createMock(AiExtractor::class);
        $extractor->expects($this->never())->method('extract');

        $controller = new AiController($extractor);
        $this->bindController($controller);

        $response = $controller->extract($this->jsonRequest('POST', '/api/ai/extract', [
            'text' => str_repeat('a', 50001),
        ]));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('The message is too long.', $this->decodeJson($response)['error']);
    }

    public function testExtractReturns503ForConfigurationProblems(): void
    {
        $extractor = $this->createMock(AiExtractor::class);
        $extractor->method('extract')->willThrowException(new AiExtractionException('Not configured.', true));

        $controller = new AiController($extractor);
        $this->bindController($controller);

        $response = $controller->extract($this->jsonRequest('POST', '/api/ai/extract', ['text' => 'Hello']));

        $this->assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        $this->assertSame('Not configured.', $this->decodeJson($response)['error']);
    }

    public function testExtractReturns502ForUpstreamErrors(): void
    {
        $extractor = $this->createMock(AiExtractor::class);
        $extractor->method('extract')->willThrowException(new AiExtractionException('Upstream failed.'));

        $controller = new AiController($extractor);
        $this->bindController($controller);

        $response = $controller->extract($this->jsonRequest('POST', '/api/ai/extract', ['text' => 'Hello']));

        $this->assertSame(Response::HTTP_BAD_GATEWAY, $response->getStatusCode());
        $this->assertSame('Upstream failed.', $this->decodeJson($response)['error']);
    }
}
