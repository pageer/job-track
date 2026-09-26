<?php

namespace App\Controller;

use App\Entity\JobActivity;
use App\Entity\Todo;
use App\Entity\User;
use App\Repository\TodoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/todos', name: 'api_todos_')]
class TodoController extends AbstractController
{
    public function __construct(
        private TodoRepository $todoRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json($this->todoRepository->findByUser($user->getId()), 200, [], ['groups' => ['todo.read']]);
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

        $targetDate = $this->parseDate($data['targetDate'] ?? null);
        if (null === $targetDate) {
            return $this->json(['error' => 'A valid target date (YYYY-MM-DD) is required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $todo = new Todo();
        $todo->setUser($user);
        $todo->setDescription($description);
        $todo->setDetails((string) ($data['details'] ?? '') !== '' ? (string) $data['details'] : null);
        $todo->setTargetDate($targetDate);

        $this->entityManager->persist($todo);
        $this->entityManager->flush();

        return $this->json($todo, Response::HTTP_CREATED, [], ['groups' => ['todo.read']]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $todo = $this->findOwnedTodo($id);
        if (null === $todo) {
            return $this->json(['error' => 'Todo not found.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($todo, 200, [], ['groups' => ['todo.read']]);
    }

    #[Route('/{id}', name: 'update', methods: ['PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $todo = $this->findOwnedTodo($id);
        if (null === $todo) {
            return $this->json(['error' => 'Todo not found.'], Response::HTTP_NOT_FOUND);
        }

        $data = $request->toArray();

        if (array_key_exists('description', $data)) {
            $description = trim((string) $data['description']);
            if ('' === $description) {
                return $this->json(['error' => 'A description is required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $todo->setDescription($description);
        }

        if (array_key_exists('targetDate', $data)) {
            $targetDate = $this->parseDate($data['targetDate']);
            if (null === $targetDate) {
                return $this->json(['error' => 'The target date must be a valid date (YYYY-MM-DD).'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $todo->setTargetDate($targetDate);
        }

        if (array_key_exists('details', $data)) {
            $todo->setDetails((string) ($data['details'] ?? '') !== '' ? (string) $data['details'] : null);
        }

        $this->entityManager->flush();

        return $this->json($todo, 200, [], ['groups' => ['todo.read']]);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $todo = $this->findOwnedTodo($id);
        if (null === $todo) {
            return $this->json(['error' => 'Todo not found.'], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($todo);
        $this->entityManager->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/{id}/complete', name: 'complete', methods: ['POST'])]
    public function complete(int $id, Request $request): JsonResponse
    {
        $todo = $this->findOwnedTodo($id);
        if (null === $todo) {
            return $this->json(['error' => 'Todo not found.'], Response::HTTP_NOT_FOUND);
        }

        /** @var User $user */
        $user = $this->getUser();
        $data = $request->toArray();

        $hoursSpent = $data['hoursSpent'] ?? null;
        if (!is_numeric($hoursSpent) || (float) $hoursSpent <= 0 || (float) $hoursSpent > 24) {
            return $this->json(['error' => 'Hours spent must be a number greater than 0 and at most 24.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $description = trim((string) ($data['description'] ?? ''));
        if ('' === $description) {
            $description = $todo->getDescription();
        }

        $details = array_key_exists('details', $data)
            ? ((string) ($data['details'] ?? '') !== '' ? (string) $data['details'] : null)
            : $todo->getDetails();

        $date = new \DateTimeImmutable('today');
        if (isset($data['date']) && '' !== $data['date']) {
            $date = $this->parseDate($data['date']);
            if (null === $date) {
                return $this->json(['error' => 'The date must be a valid date (YYYY-MM-DD).'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        $activity = new JobActivity();
        $activity->setUser($user);
        $activity->setDescription($description);
        $activity->setDetails($details);
        $activity->setDate($date);
        $activity->setHoursSpent((float) $hoursSpent);

        $this->entityManager->remove($todo);
        $this->entityManager->persist($activity);
        $this->entityManager->flush();

        return $this->json($activity, Response::HTTP_CREATED, [], ['groups' => ['jobActivity.read']]);
    }

    private function findOwnedTodo(int $id): ?Todo
    {
        /** @var User $user */
        $user = $this->getUser();
        $todo = $this->todoRepository->find($id);

        return null !== $todo && $todo->getUser()->getId() === $user->getId() ? $todo : null;
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
