<?php

namespace App\Controller;

use App\Entity\CoverLetter;
use App\Entity\User;
use App\Repository\CoverLetterRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/cover-letters', name: 'api_cover_letters_')]
class CoverLetterController extends AbstractController
{
    public function __construct(private CoverLetterRepository $coverLetterRepository)
    {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json($this->coverLetterRepository->findByUser($user->getId()), 200, [], ['groups' => ['coverLetter.read']]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = $request->toArray();

        $name = trim((string) ($data['name'] ?? ''));
        if ('' === $name) {
            return $this->json(['error' => 'A name is required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $letter = new CoverLetter();
        $letter->setUser($user);
        $letter->setName($name);
        $letter->setBody((string) ($data['body'] ?? ''));

        $this->coverLetterRepository->getEntityManager()->persist($letter);
        $this->coverLetterRepository->getEntityManager()->flush();

        return $this->json($letter, Response::HTTP_CREATED, [], ['groups' => ['coverLetter.read']]);
    }

    #[Route('/{id}', name: 'update', methods: ['PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $letter = $this->findOwnedLetter($id);
        if (null === $letter) {
            return $this->json(['error' => 'Cover letter not found.'], Response::HTTP_NOT_FOUND);
        }

        $data = $request->toArray();

        if (array_key_exists('name', $data)) {
            $name = trim((string) $data['name']);
            if ('' === $name) {
                return $this->json(['error' => 'A name is required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $letter->setName($name);
        }

        if (array_key_exists('body', $data)) {
            $letter->setBody((string) $data['body']);
        }

        $this->coverLetterRepository->getEntityManager()->flush();

        return $this->json($letter, 200, [], ['groups' => ['coverLetter.read']]);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $letter = $this->findOwnedLetter($id);
        if (null === $letter) {
            return $this->json(['error' => 'Cover letter not found.'], Response::HTTP_NOT_FOUND);
        }

        $this->coverLetterRepository->getEntityManager()->remove($letter);
        $this->coverLetterRepository->getEntityManager()->flush();

        return $this->json(['success' => true]);
    }

    private function findOwnedLetter(int $id): ?CoverLetter
    {
        /** @var User $user */
        $user = $this->getUser();
        $letter = $this->coverLetterRepository->find($id);

        return null !== $letter && $letter->getUser()->getId() === $user->getId() ? $letter : null;
    }
}
