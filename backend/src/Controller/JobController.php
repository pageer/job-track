<?php

namespace App\Controller;

use App\Entity\Job;
use App\Entity\JobSearch;
use App\Entity\User;
use App\Enum\JobStatus;
use App\Repository\JobRepository;
use App\Repository\JobSearchRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class JobController extends AbstractController
{
    public function __construct(
        private JobRepository $jobRepository,
        private JobSearchRepository $jobSearchRepository
    ) {
    }

    #[Route('/api/job-searches/{jobSearchId}/jobs', name: 'api_jobs_index', methods: ['GET'])]
    public function index(int $jobSearchId): JsonResponse
    {
        $search = $this->findOwnedSearch($jobSearchId);
        if (null === $search) {
            return $this->json(['error' => 'Job search not found.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->jobRepository->findByJobSearch($search->getId()), 200, [], ['groups' => ['job.list']]);
    }

    #[Route('/api/job-searches/{jobSearchId}/jobs', name: 'api_jobs_create', methods: ['POST'])]
    public function create(int $jobSearchId, Request $request): JsonResponse
    {
        $search = $this->findOwnedSearch($jobSearchId);
        if (null === $search) {
            return $this->json(['error' => 'Job search not found.'], Response::HTTP_NOT_FOUND);
        }

        $data = $request->toArray();

        $title = trim((string) ($data['title'] ?? ''));
        $company = trim((string) ($data['company'] ?? ''));
        if ('' === $title || '' === $company) {
            return $this->json(['error' => 'A title and company are required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $status = JobStatus::Investigating;
        if (array_key_exists('status', $data) && null !== $data['status']) {
            $status = $this->parseStatus($data['status']);
            if (null === $status) {
                return $this->json(['error' => sprintf('Invalid status. Allowed values: %s.', implode(', ', JobStatus::values()))], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        $job = new Job();
        $job->setJobSearch($search);
        $job->setTitle($title);
        $job->setCompany($company);
        $job->setStatus($status);
        $job->setDescriptionHtml($this->nullableString($data['descriptionHtml'] ?? null));
        $job->setDescriptionUrl($this->nullableString($data['descriptionUrl'] ?? null));

        $this->jobRepository->getEntityManager()->persist($job);
        $this->jobRepository->getEntityManager()->flush();

        return $this->json($job, Response::HTTP_CREATED, [], ['groups' => ['job.list']]);
    }

    #[Route('/api/jobs/{id}', name: 'api_jobs_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $job = $this->findOwnedJob($id);
        if (null === $job) {
            return $this->json(['error' => 'Job not found.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($job, 200, [], ['groups' => ['job.read', 'application.read', 'interview.read']]);
    }

    #[Route('/api/jobs/{id}', name: 'api_jobs_update', methods: ['PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $job = $this->findOwnedJob($id);
        if (null === $job) {
            return $this->json(['error' => 'Job not found.'], Response::HTTP_NOT_FOUND);
        }

        $data = $request->toArray();

        if (array_key_exists('title', $data)) {
            $title = trim((string) $data['title']);
            if ('' === $title) {
                return $this->json(['error' => 'A title is required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $job->setTitle($title);
        }

        if (array_key_exists('company', $data)) {
            $company = trim((string) $data['company']);
            if ('' === $company) {
                return $this->json(['error' => 'A company is required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $job->setCompany($company);
        }

        if (array_key_exists('status', $data)) {
            $status = $this->parseStatus($data['status']);
            if (null === $status) {
                return $this->json(['error' => sprintf('Invalid status. Allowed values: %s.', implode(', ', JobStatus::values()))], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $job->setStatus($status);
        }

        if (array_key_exists('descriptionHtml', $data)) {
            $job->setDescriptionHtml($this->nullableString($data['descriptionHtml']));
        }

        if (array_key_exists('descriptionUrl', $data)) {
            $job->setDescriptionUrl($this->nullableString($data['descriptionUrl']));
        }

        $this->jobRepository->getEntityManager()->flush();

        return $this->json($job, 200, [], ['groups' => ['job.read', 'application.read', 'interview.read']]);
    }

    #[Route('/api/jobs/{id}', name: 'api_jobs_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $job = $this->findOwnedJob($id);
        if (null === $job) {
            return $this->json(['error' => 'Job not found.'], Response::HTTP_NOT_FOUND);
        }

        $this->jobRepository->getEntityManager()->remove($job);
        $this->jobRepository->getEntityManager()->flush();

        return $this->json(['success' => true]);
    }

    private function findOwnedSearch(int $jobSearchId): ?JobSearch
    {
        /** @var User $user */
        $user = $this->getUser();
        $search = $this->jobSearchRepository->find($jobSearchId);

        return null !== $search && $search->getUser()->getId() === $user->getId() ? $search : null;
    }

    private function findOwnedJob(int $id): ?Job
    {
        /** @var User $user */
        $user = $this->getUser();
        $job = $this->jobRepository->find($id);

        return null !== $job && $job->getJobSearch()?->getUser()?->getId() === $user->getId() ? $job : null;
    }

    private function parseStatus(mixed $value): ?JobStatus
    {
        if (!is_string($value)) {
            return null;
        }

        return JobStatus::tryFrom($value);
    }

    private function nullableString(mixed $value): ?string
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return (string) $value;
    }
}
