<?php

namespace App\Service;

use App\Entity\Company;
use App\Entity\Contact;
use App\Entity\Interview;
use App\Entity\Job;
use App\Entity\Person;
use App\Entity\User;
use App\Repository\CompanyRepository;
use App\Repository\ContactRepository;
use App\Repository\InterviewRepository;
use App\Repository\JobRepository;
use App\Repository\PersonRepository;
use Doctrine\ORM\EntityManagerInterface;

class NetworkingBackfillService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private JobRepository $jobRepository,
        private InterviewRepository $interviewRepository,
        private CompanyRepository $companyRepository,
        private PersonRepository $personRepository,
        private ContactRepository $contactRepository,
    ) {
    }

    /**
     * Creates a Company for every distinct (user, job company), a Person for every
     * interviewer (linked to their job's company), and a Contact for each interviewer
     * on the day of their interview. Idempotent: matching entries already in the
     * database are skipped.
     *
     * @return array{
     *     companies: int,
     *     jobsLinked: int,
     *     people: int,
     *     peopleSkipped: int,
     *     contacts: int,
     *     contactsSkipped: int
     * }
     */
    public function backfill(): array
    {
        $companies = 0;
        $jobsLinked = 0;
        $people = 0;
        $peopleSkipped = 0;
        $contacts = 0;
        $contactsSkipped = 0;

        /** @var array<string, Company> $companyByKey */
        $companyByKey = [];
        /** @var array<string, Person> $personByKey */
        $personByKey = [];

        /** @var Job[] $jobs */
        $jobs = $this->jobRepository->findAllWithSearch();
        foreach ($jobs as $job) {
            $user = $job->getJobSearch()?->getUser();
            $companyName = trim($job->getCompany() ?? '');
            if (null === $user || '' === $companyName) {
                continue;
            }

            $key = $this->companyKey($user->getId(), $companyName);
            if (null !== $job->getCompanyRef()) {
                $companyByKey[$key] = $job->getCompanyRef();
                continue;
            }
            if (null === ($companyByKey[$key] ?? null)) {
                $companyByKey[$key] = $this->companyFor($user, $companyName, $companies);
            }
            $job->setCompanyRef($companyByKey[$key]);
            ++$jobsLinked;
        }

        /** @var Interview[] $interviews */
        $interviews = $this->interviewRepository->findAllWithJob();
        foreach ($interviews as $interview) {
            $user = $interview->getApplication()?->getJob()?->getJobSearch()?->getUser();
            $job = $interview->getApplication()?->getJob();
            if (null === $user || null === $job || null === $interview->getDate()) {
                continue;
            }

            $company = $this->companyForJob($job, $companyByKey, $companies);
            $companyId = $company?->getId();

            $seen = [];
            foreach ($interview->getInterviewers() as $interviewer) {
                $name = trim($interviewer);
                $seenKey = strtolower($name);
                if ('' === $name || isset($seen[$seenKey])) {
                    continue;
                }
                $seen[$seenKey] = true;

                $person = $this->personForInterviewer(
                    $user,
                    $name,
                    $companyId,
                    $company,
                    $personByKey,
                    $people,
                    $peopleSkipped,
                );

                $this->contactForInterview($interview, $person, $contacts, $contactsSkipped);
            }
        }

        $this->entityManager->flush();

        return [
            'companies' => $companies,
            'jobsLinked' => $jobsLinked,
            'people' => $people,
            'peopleSkipped' => $peopleSkipped,
            'contacts' => $contacts,
            'contactsSkipped' => $contactsSkipped,
        ];
    }

    /**
     * @param int $created incremented when a company is actually created
     */
    private function companyFor(User $user, string $name, int &$created): Company
    {
        $existing = $this->companyRepository->findByNameForUser($name, $user->getId());
        if (null !== $existing) {
            return $existing;
        }

        $company = new Company();
        $company->setUser($user);
        $company->setName($name);
        $this->entityManager->persist($company);
        ++$created;

        return $company;
    }

    /**
     * @param array<string, Company> $companyByKey
     * @param int                   $created incremented when a company is actually created
     */
    private function companyForJob(Job $job, array $companyByKey, int &$created): ?Company
    {
        if (null !== $job->getCompanyRef()) {
            return $job->getCompanyRef();
        }

        $user = $job->getJobSearch()?->getUser();
        $companyName = trim($job->getCompany() ?? '');
        if (null === $user || '' === $companyName) {
            return null;
        }

        $key = $this->companyKey($user->getId(), $companyName);

        return $companyByKey[$key] ?? $this->companyFor($user, $companyName, $created);
    }

    private function companyKey(int $userId, string $name): string
    {
        return $userId . ':' . strtolower($name);
    }

    /**
     * @param array<string, Person> $personByKey
     */
    private function personForInterviewer(
        User $user,
        string $name,
        ?int $companyId,
        ?Company $company,
        array &$personByKey,
        int &$created,
        int &$skipped,
    ): Person {
        $key = $user->getId() . ':' . strtolower($name) . ':' . ($companyId ?? 'none');
        if (null !== ($personByKey[$key] ?? null)) {
            return $personByKey[$key];
        }

        $person = $this->personRepository->findByNameAndCompany($user->getId(), $name, $companyId);
        if (null === $person) {
            $person = new Person();
            $person->setUser($user);
            $person->setName($name);
            $person->setCompany($company);
            $this->entityManager->persist($person);
            ++$created;
        } else {
            ++$skipped;
        }
        $personByKey[$key] = $person;

        return $person;
    }

    private function contactForInterview(Interview $interview, Person $person, int &$created, int &$skipped): void
    {
        $date = new \DateTimeImmutable($interview->getDate()?->format('Y-m-d') ?? '');
        $notes = $interview->getNotes();
        $description = (null === $notes || '' === $notes) ? null : $notes;

        $contact = $this->contactForPerson($person->getId(), $date, $description);
        if (null === $contact->getId()) {
            $contact->setPerson($person);
            $contact->setDate($date);
            $contact->setDescription($description);
            $contact->setInterview($interview);
            $this->entityManager->persist($contact);
            ++$created;

            return;
        }

        $contact->setInterview($interview);
        ++$skipped;
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
}
