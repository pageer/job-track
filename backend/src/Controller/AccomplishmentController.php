<?php

namespace App\Controller;

use App\Entity\Accomplishment;
use App\Entity\User;
use App\Repository\AccomplishmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/accomplishments', name: 'api_accomplishments_')]
class AccomplishmentController extends AbstractController
{
    public function __construct(
        private AccomplishmentRepository $accomplishmentRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json(
            $this->accomplishmentRepository->findByUser($user->getId()),
            200,
            [],
            ['groups' => ['accomplishment.read']],
        );
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = $request->toArray();

        $company = trim((string) ($data['company'] ?? ''));
        if ('' === $company) {
            return $this->json(['error' => 'A company name is required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $description = trim((string) ($data['description'] ?? ''));
        if ('' === $description) {
            return $this->json(['error' => 'A description is required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $date = $this->parseDate($data['date'] ?? null);
        if (null === $date) {
            return $this->json(['error' => 'A valid date (YYYY-MM-DD) is required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $accomplishment = new Accomplishment();
        $accomplishment->setUser($user);
        $accomplishment->setCompany($company);
        $accomplishment->setDescription($description);
        $accomplishment->setDate($date);

        $this->entityManager->persist($accomplishment);
        $this->entityManager->flush();

        return $this->json($accomplishment, Response::HTTP_CREATED, [], ['groups' => ['accomplishment.read']]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $accomplishment = $this->findOwnedAccomplishment($id);
        if (null === $accomplishment) {
            return $this->json(['error' => 'Accomplishment not found.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($accomplishment, 200, [], ['groups' => ['accomplishment.read']]);
    }

    #[Route('/{id}', name: 'update', methods: ['PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $accomplishment = $this->findOwnedAccomplishment($id);
        if (null === $accomplishment) {
            return $this->json(['error' => 'Accomplishment not found.'], Response::HTTP_NOT_FOUND);
        }

        $data = $request->toArray();

        if (array_key_exists('company', $data)) {
            $company = trim((string) $data['company']);
            if ('' === $company) {
                return $this->json(['error' => 'A company name is required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $accomplishment->setCompany($company);
        }

        if (array_key_exists('description', $data)) {
            $description = trim((string) $data['description']);
            if ('' === $description) {
                return $this->json(['error' => 'A description is required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $accomplishment->setDescription($description);
        }

        if (array_key_exists('date', $data)) {
            $date = $this->parseDate($data['date']);
            if (null === $date) {
                return $this->json(['error' => 'A valid date (YYYY-MM-DD) is required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $accomplishment->setDate($date);
        }

        $this->entityManager->flush();

        return $this->json($accomplishment, 200, [], ['groups' => ['accomplishment.read']]);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $accomplishment = $this->findOwnedAccomplishment($id);
        if (null === $accomplishment) {
            return $this->json(['error' => 'Accomplishment not found.'], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($accomplishment);
        $this->entityManager->flush();

        return $this->json(['success' => true]);
    }

    private function findOwnedAccomplishment(int $id): ?Accomplishment
    {
        /** @var User $user */
        $user = $this->getUser();
        $accomplishment = $this->accomplishmentRepository->find($id);

        return null !== $accomplishment && $accomplishment->getUser()->getId() === $user->getId() ? $accomplishment : null;
    }

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if (null === $value || '' === $value || !is_string($value)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return false === $date || $date->format('Y-m-d') !== $value ? null : $date;
    }
}
