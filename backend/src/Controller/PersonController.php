<?php

namespace App\Controller;

use App\Entity\Company;
use App\Entity\Person;
use App\Entity\User;
use App\Repository\CompanyRepository;
use App\Repository\PersonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/people', name: 'api_people_')]
class PersonController extends AbstractController
{
    public function __construct(
        private PersonRepository $personRepository,
        private CompanyRepository $companyRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json($this->personRepository->findByUser($user->getId()), 200, [], ['groups' => ['person.list', 'contact.read']]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $person = $this->findOwnedPerson($id);
        if (null === $person) {
            return $this->json(['error' => 'Person not found.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($person, 200, [], ['groups' => ['person.read', 'contact.read']]);
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

        $email = $this->nullableString($data['email'] ?? null);
        if (null !== $email && false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'Please provide a valid email address.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        ['company' => $company, 'error' => $companyError] = $this->resolveCompany($data, $user);
        if (null !== $companyError) {
            return $this->json(['error' => $companyError], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $person = new Person();
        $person->setUser($user);
        $person->setName($name);
        $person->setCompany($company);
        $person->setEmail($email);
        $person->setLinkedInUrl($this->nullableString($data['linkedInUrl'] ?? null));
        $person->setNotes($this->nullableString($data['notes'] ?? null));

        $this->entityManager->persist($person);
        $this->entityManager->flush();

        return $this->json($person, Response::HTTP_CREATED, [], ['groups' => ['person.list', 'contact.read']]);
    }

    #[Route('/{id}', name: 'update', methods: ['PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $person = $this->findOwnedPerson($id);
        if (null === $person) {
            return $this->json(['error' => 'Person not found.'], Response::HTTP_NOT_FOUND);
        }

        /** @var User $user */
        $user = $this->getUser();
        $data = $request->toArray();

        if (array_key_exists('name', $data)) {
            $name = trim((string) $data['name']);
            if ('' === $name) {
                return $this->json(['error' => 'A name is required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $person->setName($name);
        }

        if (array_key_exists('email', $data)) {
            $email = $this->nullableString($data['email']);
            if (null !== $email && false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->json(['error' => 'Please provide a valid email address.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $person->setEmail($email);
        }

        if (array_key_exists('linkedInUrl', $data)) {
            $person->setLinkedInUrl($this->nullableString($data['linkedInUrl']));
        }

        if (array_key_exists('notes', $data)) {
            $person->setNotes($this->nullableString($data['notes']));
        }

        if (array_key_exists('companyId', $data) || array_key_exists('companyName', $data)) {
            ['company' => $company, 'error' => $companyError] = $this->resolveCompany($data, $user);
            if (null !== $companyError) {
                return $this->json(['error' => $companyError], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $person->setCompany($company);
        }

        $this->entityManager->flush();

        return $this->json($person, 200, [], ['groups' => ['person.read', 'contact.read']]);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $person = $this->findOwnedPerson($id);
        if (null === $person) {
            return $this->json(['error' => 'Person not found.'], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($person);
        $this->entityManager->flush();

        return $this->json(['success' => true]);
    }

    private function findOwnedPerson(int $id): ?Person
    {
        /** @var User $user */
        $user = $this->getUser();
        $person = $this->personRepository->find($id);

        return null !== $person && $person->getUser()->getId() === $user->getId() ? $person : null;
    }

    /**
     * @param array<string, mixed> $data
     * @return array{company: Company|null, error: string|null}
     */
    private function resolveCompany(array $data, User $user): array
    {
        if (array_key_exists('companyId', $data) && null !== $data['companyId'] && '' !== $data['companyId']) {
            $company = $this->companyRepository->find((int) $data['companyId']);
            if (null === $company || $company->getUser()->getId() !== $user->getId()) {
                return ['company' => null, 'error' => 'Company not found.'];
            }

            return ['company' => $company, 'error' => null];
        }

        if (array_key_exists('companyName', $data) && null !== $data['companyName'] && '' !== trim((string) $data['companyName'])) {
            $name = trim((string) $data['companyName']);
            $company = $this->companyRepository->findByNameForUser($name, $user->getId());
            if (null === $company) {
                $company = new Company();
                $company->setUser($user);
                $company->setName($name);
                $this->entityManager->persist($company);
            }

            return ['company' => $company, 'error' => null];
        }

        return ['company' => null, 'error' => null];
    }

    private function nullableString(mixed $value): ?string
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return (string) $value;
    }
}
