<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/api/auth', name: 'api_auth_')]
class AuthController extends AbstractController
{
    public function __construct(private CsrfTokenManagerInterface $csrfTokenManager)
    {
    }

    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(): JsonResponse
    {
        // Only reached when the JsonLoginAuthenticator succeeded.
        $user = $this->getUser();

        return $this->json([
            'user' => $user,
            'csrfToken' => $this->csrfTokenManager->getToken('app')->getValue(),
        ], 200, [], ['groups' => ['user.read']]);
    }

    #[Route('/me', name: 'me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        return $this->json([
            'user' => $this->getUser(),
            'csrfToken' => $this->csrfTokenManager->getToken('app')->getValue(),
        ], 200, [], ['groups' => ['user.read']]);
    }

    #[Route('/logout', name: 'logout', methods: ['POST'])]
    public function logout(Request $request, TokenStorageInterface $tokenStorage): JsonResponse
    {
        $tokenStorage->setToken(null);
        if ($request->hasSession()) {
            $request->getSession()->invalidate();
        }

        return $this->json(['success' => true]);
    }
}
