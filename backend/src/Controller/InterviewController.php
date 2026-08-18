<?php

namespace App\Controller;

use App\Entity\Application;
use App\Entity\Interview;
use App\Entity\User;
use App\Repository\ApplicationRepository;
use App\Repository\InterviewRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class InterviewController extends AbstractController
{
    public function __construct(
        private InterviewRepository $interviewRepository,
        private ApplicationRepository $applicationRepository
    ) {
    }

    #[Route('/api/applications/{applicationId}/interviews', name: 'api_interviews_index', methods: ['GET'])]
    public function index(int $applicationId): JsonResponse
    {
        $application = $this->findOwnedApplication($applicationId);
        if (null === $application) {
            return $this->json(['error' => 'Application not found.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($application->getInterviews(), 200, [], ['groups' => ['interview.read']]);
    }

    #[Route('/api/applications/{applicationId}/interviews', name: 'api_interviews_create', methods: ['POST'])]
    public function create(int $applicationId, Request $request): JsonResponse
    {
        $application = $this->findOwnedApplication($applicationId);
        if (null === $application) {
            return $this->json(['error' => 'Application not found.'], Response::HTTP_NOT_FOUND);
        }

        $data = $request->toArray();

        $date = $this->parseDate($data['date'] ?? null);
        if (null === $date) {
            return $this->json(['error' => 'A valid interview date is required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $interview = new Interview();
        $interview->setApplication($application);
        $interview->setDate($date);
        $interview->setInterviewers($this->parseInterviewers($data['interviewers'] ?? []));
        $interview->setNotes($this->nullableString($data['notes'] ?? null));

        $this->interviewRepository->getEntityManager()->persist($interview);
        $this->interviewRepository->getEntityManager()->flush();

        return $this->json($interview, Response::HTTP_CREATED, [], ['groups' => ['interview.read']]);
    }

    #[Route('/api/interviews/{id}', name: 'api_interviews_update', methods: ['PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $interview = $this->findOwnedInterview($id);
        if (null === $interview) {
            return $this->json(['error' => 'Interview not found.'], Response::HTTP_NOT_FOUND);
        }

        $data = $request->toArray();

        if (array_key_exists('date', $data)) {
            $date = $this->parseDate($data['date']);
            if (null === $date) {
                return $this->json(['error' => 'The date must be a valid date.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $interview->setDate($date);
        }

        if (array_key_exists('interviewers', $data)) {
            $interview->setInterviewers($this->parseInterviewers($data['interviewers']));
        }

        if (array_key_exists('notes', $data)) {
            $interview->setNotes($this->nullableString($data['notes']));
        }

        $this->interviewRepository->getEntityManager()->flush();

        return $this->json($interview, 200, [], ['groups' => ['interview.read']]);
    }

    #[Route('/api/interviews/{id}', name: 'api_interviews_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $interview = $this->findOwnedInterview($id);
        if (null === $interview) {
            return $this->json(['error' => 'Interview not found.'], Response::HTTP_NOT_FOUND);
        }

        $this->interviewRepository->getEntityManager()->remove($interview);
        $this->interviewRepository->getEntityManager()->flush();

        return $this->json(['success' => true]);
    }

    private function findOwnedApplication(int $applicationId): ?Application
    {
        /** @var User $user */
        $user = $this->getUser();
        $application = $this->applicationRepository->find($applicationId);

        return null !== $application && $application->getJob()?->getJobSearch()?->getUser()?->getId() === $user->getId()
            ? $application
            : null;
    }

    private function findOwnedInterview(int $id): ?Interview
    {
        /** @var User $user */
        $user = $this->getUser();
        $interview = $this->interviewRepository->find($id);

        return null !== $interview && $interview->getApplication()?->getJob()?->getJobSearch()?->getUser()?->getId() === $user->getId()
            ? $interview
            : null;
    }

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if (null === $value || !is_string($value) || '' === trim($value)) {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * @return list<string>
     */
    private function parseInterviewers(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $item): string => trim((string) $item),
            $value
        ), static fn (string $item): bool => '' !== $item));
    }

    private function nullableString(mixed $value): ?string
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return (string) $value;
    }
}
