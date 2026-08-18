<?php

namespace App\Controller;

use App\Entity\JobSearch;
use App\Entity\User;
use App\Repository\JobSearchRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/job-searches', name: 'api_job_searches_')]
class JobSearchController extends AbstractController
{
    public function __construct(private JobSearchRepository $jobSearchRepository)
    {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json($this->jobSearchRepository->findByUser($user->getId()), 200, [], ['groups' => ['jobSearch.read']]);
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

        $startDate = $this->parseDate($data['startDate'] ?? null);
        if (null === $startDate) {
            return $this->json(['error' => 'A valid startDate (YYYY-MM-DD) is required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $endDate = isset($data['endDate']) && '' !== $data['endDate']
            ? $this->parseDate($data['endDate'])
            : null;
        if (array_key_exists('endDate', $data) && '' !== (string) ($data['endDate'] ?? '') && null === $endDate) {
            return $this->json(['error' => 'The endDate must be a valid date (YYYY-MM-DD).'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $search = new JobSearch();
        $search->setUser($user);
        $search->setName($name);
        $search->setStartDate($startDate);
        $search->setEndDate($endDate);

        $this->jobSearchRepository->getEntityManager()->persist($search);
        $this->jobSearchRepository->getEntityManager()->flush();

        return $this->json($search, Response::HTTP_CREATED, [], ['groups' => ['jobSearch.read']]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $search = $this->findOwnedSearch($id);
        if (null === $search) {
            return $this->json(['error' => 'Job search not found.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($search, 200, [], ['groups' => ['jobSearch.read', 'jobSearch.detail', 'job.list']]);
    }

    #[Route('/{id}', name: 'update', methods: ['PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $search = $this->findOwnedSearch($id);
        if (null === $search) {
            return $this->json(['error' => 'Job search not found.'], Response::HTTP_NOT_FOUND);
        }

        $data = $request->toArray();

        if (array_key_exists('name', $data)) {
            $name = trim((string) $data['name']);
            if ('' === $name) {
                return $this->json(['error' => 'A name is required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $search->setName($name);
        }

        if (array_key_exists('startDate', $data)) {
            $startDate = $this->parseDate($data['startDate']);
            if (null === $startDate) {
                return $this->json(['error' => 'The startDate must be a valid date (YYYY-MM-DD).'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $search->setStartDate($startDate);
        }

        if (array_key_exists('endDate', $data)) {
            $endDate = '' !== (string) ($data['endDate'] ?? '') ? $this->parseDate($data['endDate']) : null;
            if (null !== $data['endDate'] && '' !== (string) $data['endDate'] && null === $endDate) {
                return $this->json(['error' => 'The endDate must be a valid date (YYYY-MM-DD).'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $search->setEndDate($endDate);
        }

        $this->jobSearchRepository->getEntityManager()->flush();

        return $this->json($search, 200, [], ['groups' => ['jobSearch.read']]);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $search = $this->findOwnedSearch($id);
        if (null === $search) {
            return $this->json(['error' => 'Job search not found.'], Response::HTTP_NOT_FOUND);
        }

        $this->jobSearchRepository->getEntityManager()->remove($search);
        $this->jobSearchRepository->getEntityManager()->flush();

        return $this->json(['success' => true]);
    }

    private function findOwnedSearch(int $id): ?JobSearch
    {
        /** @var User $user */
        $user = $this->getUser();
        $search = $this->jobSearchRepository->find($id);

        return null !== $search && $search->getUser()->getId() === $user->getId() ? $search : null;
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
