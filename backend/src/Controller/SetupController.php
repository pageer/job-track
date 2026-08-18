<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Service\UserSetupService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/api/setup', name: 'api_setup_')]
class SetupController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private UserSetupService $userSetupService,
        private CsrfTokenManagerInterface $csrfTokenManager
    ) {
    }

    #[Route('/status', name: 'status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        return $this->json([
            'needsSetup' => 0 === $this->userRepository->countAll(),
            'csrfToken' => $this->csrfTokenManager->getToken('app')->getValue(),
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        if (0 !== $this->userRepository->countAll()) {
            return $this->json(['error' => 'Setup has already been completed.'], Response::HTTP_CONFLICT);
        }

        $data = $request->toArray();

        $name = trim((string) ($data['name'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if ('' === $name || '' === $email || '' === $password) {
            return $this->json(['error' => 'Name, email and password are required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (strlen($password) < 8) {
            return $this->json(['error' => 'Password must be at least 8 characters long.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (null === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'Please provide a valid email address.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (null !== $this->userRepository->findOneBy(['email' => $email])) {
            return $this->json(['error' => 'A user with this email already exists.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = $this->userSetupService->createUser($name, $email, $password, ['ROLE_ADMIN']);

        return $this->json(['user' => $user], Response::HTTP_CREATED, [], ['groups' => ['user.read']]);
    }
}
