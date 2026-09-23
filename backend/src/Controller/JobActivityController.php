<?php

namespace App\Controller;

use App\Entity\JobActivity;
use App\Entity\User;
use App\Repository\JobActivityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/job-activities', name: 'api_job_activities_')]
class JobActivityController extends AbstractController
{
    public function __construct(
        private JobActivityRepository $jobActivityRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json($this->jobActivityRepository->findByUser($user->getId()), 200, [], ['groups' => ['jobActivity.read']]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = $request->toArray();

        $description = trim((string) ($data['description'] ?? ''));
        if ('' === $description) {
            return $this->json(['error' => 'A description is required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $date = $this->parseDate($data['date'] ?? null);
        if (null === $date) {
            return $this->json(['error' => 'A valid date (YYYY-MM-DD) is required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $hoursSpent = $data['hoursSpent'] ?? null;
        if (!is_numeric($hoursSpent) || (float) $hoursSpent <= 0 || (float) $hoursSpent > 24) {
            return $this->json(['error' => 'Hours spent must be a number greater than 0 and at most 24.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $activity = new JobActivity();
        $activity->setUser($user);
        $activity->setDescription($description);
        $activity->setDetails((string) ($data['details'] ?? '') !== '' ? (string) $data['details'] : null);
        $activity->setDate($date);
        $activity->setHoursSpent((float) $hoursSpent);

        $this->entityManager->persist($activity);
        $this->entityManager->flush();

        return $this->json($activity, Response::HTTP_CREATED, [], ['groups' => ['jobActivity.read']]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $activity = $this->findOwnedActivity($id);
        if (null === $activity) {
            return $this->json(['error' => 'Job activity not found.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($activity, 200, [], ['groups' => ['jobActivity.read']]);
    }

    #[Route('/{id}', name: 'update', methods: ['PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $activity = $this->findOwnedActivity($id);
        if (null === $activity) {
            return $this->json(['error' => 'Job activity not found.'], Response::HTTP_NOT_FOUND);
        }

        $data = $request->toArray();

        if (array_key_exists('description', $data)) {
            $description = trim((string) $data['description']);
            if ('' === $description) {
                return $this->json(['error' => 'A description is required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $activity->setDescription($description);
        }

        if (array_key_exists('date', $data)) {
            $date = $this->parseDate($data['date']);
            if (null === $date) {
                return $this->json(['error' => 'The date must be a valid date (YYYY-MM-DD).'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $activity->setDate($date);
        }

        if (array_key_exists('hoursSpent', $data)) {
            $hoursSpent = $data['hoursSpent'];
            if (!is_numeric($hoursSpent) || (float) $hoursSpent <= 0 || (float) $hoursSpent > 24) {
                return $this->json(['error' => 'Hours spent must be a number greater than 0 and at most 24.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $activity->setHoursSpent((float) $hoursSpent);
        }

        if (array_key_exists('details', $data)) {
            $activity->setDetails((string) ($data['details'] ?? '') !== '' ? (string) $data['details'] : null);
        }

        $this->entityManager->flush();

        return $this->json($activity, 200, [], ['groups' => ['jobActivity.read']]);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $activity = $this->findOwnedActivity($id);
        if (null === $activity) {
            return $this->json(['error' => 'Job activity not found.'], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($activity);
        $this->entityManager->flush();

        return $this->json(['success' => true]);
    }

    private function findOwnedActivity(int $id): ?JobActivity
    {
        /** @var User $user */
        $user = $this->getUser();
        $activity = $this->jobActivityRepository->find($id);

        return null !== $activity && $activity->getUser()->getId() === $user->getId() ? $activity : null;
    }

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if (null === $value || '' === $value || !is_string($value)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return false === $date ? null : $date;
    }
}
