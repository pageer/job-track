<?php

namespace App\Controller;

use App\Entity\Contact;
use App\Entity\Person;
use App\Entity\User;
use App\Repository\ContactRepository;
use App\Repository\PersonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ContactController extends AbstractController
{
    public function __construct(
        private ContactRepository $contactRepository,
        private PersonRepository $personRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/people/{personId}/contacts', name: 'api_contacts_index', methods: ['GET'])]
    public function index(int $personId): JsonResponse
    {
        $person = $this->findOwnedPerson($personId);
        if (null === $person) {
            return $this->json(['error' => 'Person not found.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($person->getContacts(), 200, [], ['groups' => ['contact.read']]);
    }

    #[Route('/api/people/{personId}/contacts', name: 'api_contacts_create', methods: ['POST'])]
    public function create(int $personId, Request $request): JsonResponse
    {
        $person = $this->findOwnedPerson($personId);
        if (null === $person) {
            return $this->json(['error' => 'Person not found.'], Response::HTTP_NOT_FOUND);
        }

        $data = $request->toArray();

        $date = $this->parseDate($data['date'] ?? null);
        if (null === $date) {
            return $this->json(['error' => 'A valid date (YYYY-MM-DD) is required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $contact = new Contact();
        $contact->setPerson($person);
        $contact->setDate($date);
        $contact->setDescription($this->nullableString($data['description'] ?? null));
        $contact->setNeedsFollowUp(true === ($data['needsFollowUp'] ?? false));

        $this->entityManager->persist($contact);
        $this->entityManager->flush();

        return $this->json($contact, Response::HTTP_CREATED, [], ['groups' => ['contact.read']]);
    }

    #[Route('/api/people/{personId}/contacts/{id}', name: 'api_contacts_update', methods: ['PATCH'])]
    public function update(int $personId, int $id, Request $request): JsonResponse
    {
        $contact = $this->findOwnedContact($personId, $id);
        if (null === $contact) {
            return $this->json(['error' => 'Contact not found.'], Response::HTTP_NOT_FOUND);
        }

        $data = $request->toArray();

        if (array_key_exists('date', $data)) {
            $date = $this->parseDate($data['date']);
            if (null === $date) {
                return $this->json(['error' => 'The date must be a valid date (YYYY-MM-DD).'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $contact->setDate($date);
        }

        if (array_key_exists('description', $data)) {
            $contact->setDescription($this->nullableString($data['description']));
        }

        if (array_key_exists('needsFollowUp', $data)) {
            $contact->setNeedsFollowUp(true === $data['needsFollowUp']);
        }

        $this->entityManager->flush();

        return $this->json($contact, 200, [], ['groups' => ['contact.read']]);
    }

    #[Route('/api/people/{personId}/contacts/{id}', name: 'api_contacts_delete', methods: ['DELETE'])]
    public function delete(int $personId, int $id): JsonResponse
    {
        $contact = $this->findOwnedContact($personId, $id);
        if (null === $contact) {
            return $this->json(['error' => 'Contact not found.'], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($contact);
        $this->entityManager->flush();

        return $this->json(['success' => true]);
    }

    private function findOwnedPerson(int $personId): ?Person
    {
        /** @var User $user */
        $user = $this->getUser();
        $person = $this->personRepository->find($personId);

        return null !== $person && $person->getUser()->getId() === $user->getId() ? $person : null;
    }

    private function findOwnedContact(int $personId, int $id): ?Contact
    {
        /** @var User $user */
        $user = $this->getUser();
        $contact = $this->contactRepository->find($id);

        return null !== $contact
            && $contact->getPerson()?->getId() === $personId
            && $contact->getPerson()?->getUser()?->getId() === $user->getId()
            ? $contact
            : null;
    }

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if (null === $value || '' === $value || !is_string($value)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return false === $date ? null : $date;
    }

    private function nullableString(mixed $value): ?string
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return (string) $value;
    }
}
