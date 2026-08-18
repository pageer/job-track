<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SpaController extends AbstractController
{
    #[Route('/{path}', name: 'spa_fallback', requirements: ['path' => '(?!api/).*'], defaults: ['path' => null], methods: ['GET'])]
    public function index(): Response
    {
        $file = $this->getParameter('kernel.project_dir') . '/public/build/index.html';

        if (!is_file($file)) {
            return new Response(
                'Frontend assets not found. Run the frontend build (see README) or use the Vite dev server on port 5173.',
                Response::HTTP_SERVICE_UNAVAILABLE
            );
        }

        return new Response(
            file_get_contents($file),
            Response::HTTP_OK,
            ['Content-Type' => 'text/html; charset=UTF-8']
        );
    }
}
