<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Job;
use App\Entity\JobNote;
use App\Entity\User;
use App\Repository\JobNoteRepository;
use App\Repository\JobRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class JobNoteController extends AbstractController
{
    public function __construct(
        private JobNoteRepository $jobNoteRepository,
        private JobRepository $jobRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/jobs/{jobId}/notes', name: 'api_job_notes_index', methods: ['GET'])]
    public function index(int $jobId): JsonResponse
    {
        $job = $this->findOwnedJob($jobId);
        if (null === $job) {
            return $this->json(['error' => 'Job not found.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($job->getNotes(), 200, [], ['groups' => ['jobNote.read']]);
    }

    #[Route('/api/jobs/{jobId}/notes', name: 'api_job_notes_create', methods: ['POST'])]
    public function create(int $jobId, Request $request): JsonResponse
    {
        $job = $this->findOwnedJob($jobId);
        if (null === $job) {
            return $this->json(['error' => 'Job not found.'], Response::HTTP_NOT_FOUND);
        }

        $content = $this->contentFrom($request->toArray());
        if (null === $content) {
            return $this->json(['error' => 'A note is required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $note = new JobNote();
        $note->setJob($job);
        $note->setContent($content);

        $this->entityManager->persist($note);
        $this->entityManager->flush();

        return $this->json($note, Response::HTTP_CREATED, [], ['groups' => ['jobNote.read']]);
    }

    #[Route('/api/job-notes/{id}', name: 'api_job_notes_update', methods: ['PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $note = $this->findOwnedNote($id);
        if (null === $note) {
            return $this->json(['error' => 'Note not found.'], Response::HTTP_NOT_FOUND);
        }

        $content = $this->contentFrom($request->toArray());
        if (null === $content) {
            return $this->json(['error' => 'A note is required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $note->setContent($content);
        $this->entityManager->flush();

        return $this->json($note, 200, [], ['groups' => ['jobNote.read']]);
    }

    #[Route('/api/job-notes/{id}', name: 'api_job_notes_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $note = $this->findOwnedNote($id);
        if (null === $note) {
            return $this->json(['error' => 'Note not found.'], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($note);
        $this->entityManager->flush();

        return $this->json(['success' => true]);
    }

    private function findOwnedJob(int $jobId): ?Job
    {
        /** @var User $user */
        $user = $this->getUser();
        $job = $this->jobRepository->find($jobId);

        return null !== $job && $job->getJobSearch()?->getUser()?->getId() === $user->getId() ? $job : null;
    }

    private function findOwnedNote(int $id): ?JobNote
    {
        /** @var User $user */
        $user = $this->getUser();
        $note = $this->jobNoteRepository->find($id);

        return null !== $note && $note->getJob()?->getJobSearch()?->getUser()?->getId() === $user->getId() ? $note : null;
    }

    /** @param array<string, mixed> $data */
    private function contentFrom(array $data): ?string
    {
        $content = trim((string) ($data['content'] ?? ''));

        return '' === $content ? null : $content;
    }
}
