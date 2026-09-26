<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\SpaController;
use Symfony\Component\HttpFoundation\Response;

final class SpaControllerTest extends ControllerTestCase
{
    public function testIndexServesTheFrontendBuild(): void
    {
        $projectDir = $this->tempProjectDir();
        $buildDir = $projectDir . '/public/build';
        if (!is_dir($buildDir)) {
            mkdir($buildDir, 0777, true);
        }
        file_put_contents($buildDir . '/index.html', '<!doctype html><html>App</html>');

        $controller = new SpaController();
        $this->bindController($controller, null, ['kernel.project_dir' => $projectDir]);

        $response = $controller->index();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('<!doctype html><html>App</html>', $response->getContent());
        $this->assertSame('text/html; charset=UTF-8', $response->headers->get('Content-Type'));
    }

    public function testIndexReturns503WhenTheBuildIsMissing(): void
    {
        $controller = new SpaController();
        $this->bindController($controller, null, ['kernel.project_dir' => $this->tempProjectDir()]);

        $response = $controller->index();

        $this->assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        $this->assertStringContainsString('Frontend assets not found.', (string) $response->getContent());
    }

    private function tempProjectDir(): string
    {
        $directory = tempnam(sys_get_temp_dir(), 'spa');
        unlink($directory);
        mkdir($directory, 0777, true);

        return $directory;
    }
}
