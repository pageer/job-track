<?php

namespace App\Controller;

use App\Entity\Company;
use App\Entity\User;
use App\Repository\CompanyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/companies', name: 'api_companies_')]
class CompanyController extends AbstractController
{
    public function __construct(
        private CompanyRepository $companyRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json($this->companyRepository->findByUser($user->getId()), 200, [], ['groups' => ['company.read']]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = $request->toArray();

        $name = trim((string) ($data['name'] ?? ''));
        if ('' === $name) {
            return $this->json(['error' => 'A company name is required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (null !== $this->companyRepository->findByNameForUser($name, $user->getId())) {
            return $this->json(['error' => 'A company with this name already exists.'], Response::HTTP_CONFLICT);
        }

        $company = new Company();
        $company->setUser($user);
        $company->setName($name);

        $this->entityManager->persist($company);
        $this->entityManager->flush();

        return $this->json($company, Response::HTTP_CREATED, [], ['groups' => ['company.read']]);
    }

    #[Route('/{id}', name: 'update', methods: ['PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $company = $this->findOwnedCompany($id);
        if (null === $company) {
            return $this->json(['error' => 'Company not found.'], Response::HTTP_NOT_FOUND);
        }

        $data = $request->toArray();

        if (array_key_exists('name', $data)) {
            $name = trim((string) $data['name']);
            if ('' === $name) {
                return $this->json(['error' => 'A company name is required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            /** @var User $user */
            $user = $this->getUser();
            $existing = $this->companyRepository->findByNameForUser($name, $user->getId());
            if (null !== $existing && $existing->getId() !== $company->getId()) {
                return $this->json(['error' => 'A company with this name already exists.'], Response::HTTP_CONFLICT);
            }

            $company->setName($name);
        }

        $this->entityManager->flush();

        return $this->json($company, 200, [], ['groups' => ['company.read']]);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $company = $this->findOwnedCompany($id);
        if (null === $company) {
            return $this->json(['error' => 'Company not found.'], Response::HTTP_NOT_FOUND);
        }

        if ($company->getJobs()->count() > 0 || $company->getPeople()->count() > 0) {
            return $this->json(['error' => 'This company is linked to jobs or people and cannot be deleted.'], Response::HTTP_CONFLICT);
        }

        $this->entityManager->remove($company);
        $this->entityManager->flush();

        return $this->json(['success' => true]);
    }

    private function findOwnedCompany(int $id): ?Company
    {
        /** @var User $user */
        $user = $this->getUser();
        $company = $this->companyRepository->find($id);

        return null !== $company && $company->getUser()->getId() === $user->getId() ? $company : null;
    }
}
