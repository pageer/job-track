<?php

namespace App\Controller;

use App\Entity\Application;
use App\Entity\Contact;
use App\Entity\Interview;
use App\Entity\Person;
use App\Entity\User;
use App\Repository\ApplicationRepository;
use App\Repository\ContactRepository;
use App\Repository\InterviewRepository;
use App\Repository\PersonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class InterviewController extends AbstractController
{
    public function __construct(
        private InterviewRepository $interviewRepository,
        private ApplicationRepository $applicationRepository,
        private EntityManagerInterface $entityManager,
        private PersonRepository $personRepository,
        private ContactRepository $contactRepository,
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

        $this->entityManager->persist($interview);
        $this->syncNetworkingForInterview($interview);
        $this->entityManager->flush();

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

        $this->syncNetworkingForInterview($interview);
        $this->entityManager->flush();

        return $this->json($interview, 200, [], ['groups' => ['interview.read']]);
    }

    #[Route('/api/interviews/{id}', name: 'api_interviews_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $interview = $this->findOwnedInterview($id);
        if (null === $interview) {
            return $this->json(['error' => 'Interview not found.'], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($interview);
        $this->entityManager->flush();

        return $this->json(['success' => true]);
    }

    /**
     * Finds-or-creates a Person per interviewer (linked to the job's company) and a
     * Contact on the interview date whose description matches the interview notes.
     * Contacts previously derived from the same interview are updated to follow the
     * current date/notes; interviewers no longer listed are left in place as history.
     */
    private function syncNetworkingForInterview(Interview $interview): void
    {
        /** @var User $user */
        $user = $this->getUser();
        $job = $interview->getApplication()?->getJob();
        $company = $job?->getCompanyRef();
        $companyId = $company?->getId();
        $date = new \DateTimeImmutable($interview->getDate()?->format('Y-m-d') ?? '');
        $notes = $interview->getNotes();
        $description = (null === $notes || '' === $notes) ? null : $notes;

        $contactsByPersonId = [];
        $interviewId = $interview->getId();
        if (null !== $interviewId) {
            foreach ($this->contactRepository->findByInterview($interviewId) as $contact) {
                $personId = $contact->getPerson()?->getId();
                if (null !== $personId) {
                    $contactsByPersonId[$personId] = $contact;
                }
            }
        }

        $seen = [];
        foreach ($interview->getInterviewers() as $name) {
            $seenKey = strtolower($name);
            if (isset($seen[$seenKey])) {
                continue;
            }
            $seen[$seenKey] = true;

            $person = $this->personRepository->findByNameAndCompany($user->getId(), $name, $companyId);
            if (null === $person) {
                $person = new Person();
                $person->setUser($user);
                $person->setName($name);
                $person->setCompany($company);
                $this->entityManager->persist($person);
            }

            $contact = $contactsByPersonId[$person->getId()] ?? null;
            if (null === $contact) {
                $contact = $this->contactForPerson($person->getId(), $date, $description);
            }
            if (null === $contact->getId()) {
                $contact->setPerson($person);
                $this->entityManager->persist($contact);
            }
            $contact->setDate($date);
            $contact->setDescription($description);
            $contact->setInterview($interview);
        }
    }

    private function contactForPerson(?int $personId, \DateTimeImmutable $date, ?string $description): Contact
    {
        if (null !== $personId) {
            $existing = $this->contactRepository->findForPersonAndDate($personId, $date, $description);
            if (null !== $existing) {
                return $existing;
            }
        }

        return new Contact();
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
        if (null === $value) {
            return [];
        }

        $names = [];
        foreach (is_array($value) ? $value : [$value] as $item) {
            foreach (explode(',', (string) $item) as $part) {
                $trimmed = trim($part);
                if ('' !== $trimmed) {
                    $names[] = $trimmed;
                }
            }
        }

        return $names;
    }

    private function nullableString(mixed $value): ?string
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return (string) $value;
    }
}
