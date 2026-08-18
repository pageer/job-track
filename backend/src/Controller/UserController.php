<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Service\UserSetupService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/users', name: 'api_users_')]
class UserController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private UserSetupService $userSetupService
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->json($this->userRepository->findAll(), 200, [], ['groups' => ['user.read']]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

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

        $user = $this->userSetupService->createUser($name, $email, $password, ['ROLE_USER']);

        return $this->json(['user' => $user], Response::HTTP_CREATED, [], ['groups' => ['user.read']]);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $user = $this->userRepository->find($id);
        if (null === $user) {
            return $this->json(['error' => 'User not found.'], Response::HTTP_NOT_FOUND);
        }

        if ($user === $this->getUser()) {
            return $this->json(['error' => 'You cannot delete your own account.'], Response::HTTP_CONFLICT);
        }

        $this->userRepository->getEntityManager()->remove($user);
        $this->userRepository->getEntityManager()->flush();

        return $this->json(['success' => true]);
    }
}
