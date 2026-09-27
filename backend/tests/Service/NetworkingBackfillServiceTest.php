<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Application;
use App\Entity\Company;
use App\Entity\Contact;
use App\Entity\Interview;
use App\Entity\Job;
use App\Entity\JobSearch;
use App\Entity\Person;
use App\Entity\User;
use App\Repository\CompanyRepository;
use App\Repository\ContactRepository;
use App\Repository\InterviewRepository;
use App\Repository\JobRepository;
use App\Repository\PersonRepository;
use App\Service\NetworkingBackfillService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class NetworkingBackfillServiceTest extends TestCase
{
    public function testCreatesCompaniesAndLinksJobs(): void
    {
        $user = $this->user();
        $jobOne = $this->job($user, 'Acme Inc');
        $jobTwo = $this->job($user, 'acme inc');
        $jobRepository = $this->createMock(JobRepository::class);
        $jobRepository->method('findAllWithSearch')->willReturn([$jobOne, $jobTwo]);

        $interviewRepository = $this->createMock(InterviewRepository::class);
        $interviewRepository->method('findAllWithJob')->willReturn([]);

        $companyRepository = $this->createMock(CompanyRepository::class);
        $companyRepository->method('findByNameForUser')->willReturn(null);

        $persisted = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (mixed $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });
        $entityManager->expects($this->once())->method('flush');

        $service = new NetworkingBackfillService(
            $entityManager,
            $jobRepository,
            $interviewRepository,
            $companyRepository,
            $this->createMock(PersonRepository::class),
            $this->createMock(ContactRepository::class),
        );

        $result = $service->backfill();

        $this->assertSame(['companies' => 1, 'jobsLinked' => 2, 'people' => 0, 'peopleSkipped' => 0, 'contacts' => 0, 'contactsSkipped' => 0], $result);
        $this->assertCount(1, $persisted);
        $company = $persisted[0];
        $this->assertInstanceOf(Company::class, $company);
        $this->assertSame('Acme Inc', $company->getName());
        $this->assertSame($user, $company->getUser());
        $this->assertSame($company, $jobOne->getCompanyRef());
        $this->assertSame($company, $jobTwo->getCompanyRef());
    }

    public function testReusesAnExistingCompanyByName(): void
    {
        $user = $this->user();
        $job = $this->job($user, 'Acme Inc');
        $jobRepository = $this->createMock(JobRepository::class);
        $jobRepository->method('findAllWithSearch')->willReturn([$job]);

        $interviewRepository = $this->createMock(InterviewRepository::class);
        $interviewRepository->method('findAllWithJob')->willReturn([]);

        $existing = new Company();
        $this->setId($existing, 7);
        $existing->setUser($user)->setName('Acme Inc');

        $companyRepository = $this->createMock(CompanyRepository::class);
        $companyRepository->method('findByNameForUser')->with('Acme Inc', $user->getId())->willReturn($existing);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');
        $entityManager->expects($this->once())->method('flush');

        $service = new NetworkingBackfillService(
            $entityManager,
            $jobRepository,
            $interviewRepository,
            $companyRepository,
            $this->createMock(PersonRepository::class),
            $this->createMock(ContactRepository::class),
        );

        $result = $service->backfill();

        $this->assertSame(0, $result['companies']);
        $this->assertSame(1, $result['jobsLinked']);
        $this->assertSame($existing, $job->getCompanyRef());
    }

    public function testIsIdempotentForAlreadyLinkedJobs(): void
    {
        $user = $this->user();
        $company = new Company();
        $this->setId($company, 7);
        $company->setUser($user)->setName('Acme Inc');

        $job = $this->job($user, 'Acme Inc');
        $job->setCompanyRef($company);

        $jobRepository = $this->createMock(JobRepository::class);
        $jobRepository->method('findAllWithSearch')->willReturn([$job]);

        $interviewRepository = $this->createMock(InterviewRepository::class);
        $interviewRepository->method('findAllWithJob')->willReturn([]);

        $companyRepository = $this->createMock(CompanyRepository::class);
        $companyRepository->method('findByNameForUser')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (mixed $entity): void {
            $this->fail('No entity should be persisted on a second run: ' . $entity::class);
        });
        $entityManager->method('flush');

        $service = new NetworkingBackfillService(
            $entityManager,
            $jobRepository,
            $interviewRepository,
            $companyRepository,
            $this->createMock(PersonRepository::class),
            $this->createMock(ContactRepository::class),
        );

        $result = $service->backfill();

        $this->assertSame(['companies' => 0, 'jobsLinked' => 0, 'people' => 0, 'peopleSkipped' => 0, 'contacts' => 0, 'contactsSkipped' => 0], $result);
        $this->assertSame($company, $job->getCompanyRef());
    }

    public function testSkipsJobsWithoutAUserOrCompanyName(): void
    {
        $user = $this->user();
        $noCompanyJob = $this->job($user, '   ');

        $noSearchJob = new Job();
        $noSearchJob->setCompany('Acme Inc');

        $jobRepository = $this->createMock(JobRepository::class);
        $jobRepository->method('findAllWithSearch')->willReturn([$noCompanyJob, $noSearchJob]);

        $interviewRepository = $this->createMock(InterviewRepository::class);
        $interviewRepository->method('findAllWithJob')->willReturn([]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');
        $entityManager->expects($this->once())->method('flush');

        $service = new NetworkingBackfillService(
            $entityManager,
            $jobRepository,
            $interviewRepository,
            $this->createMock(CompanyRepository::class),
            $this->createMock(PersonRepository::class),
            $this->createMock(ContactRepository::class),
        );

        $result = $service->backfill();

        $this->assertSame(['companies' => 0, 'jobsLinked' => 0, 'people' => 0, 'peopleSkipped' => 0, 'contacts' => 0, 'contactsSkipped' => 0], $result);
        $this->assertNull($noCompanyJob->getCompanyRef());
        $this->assertNull($noSearchJob->getCompanyRef());
    }

    public function testCreatesPeopleFromInterviewersLinkedToTheJobCompany(): void
    {
        $user = $this->user();
        $job = $this->job($user, 'Acme Inc');
        $interview = $this->interview($job, ['Jane Doe', 'jane doe', '', 'John Smith']);

        $jobRepository = $this->createMock(JobRepository::class);
        $jobRepository->method('findAllWithSearch')->willReturn([$job]);

        $interviewRepository = $this->createMock(InterviewRepository::class);
        $interviewRepository->method('findAllWithJob')->willReturn([$interview]);

        $companyRepository = $this->createMock(CompanyRepository::class);
        $companyRepository->method('findByNameForUser')->willReturn(null);

        $persisted = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (mixed $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $service = new NetworkingBackfillService(
            $entityManager,
            $jobRepository,
            $interviewRepository,
            $companyRepository,
            $this->createMock(PersonRepository::class),
            $this->createMock(ContactRepository::class),
        );

        $result = $service->backfill();

        $this->assertSame(['companies' => 1, 'jobsLinked' => 1, 'people' => 2, 'peopleSkipped' => 0, 'contacts' => 2, 'contactsSkipped' => 0], $result);

        $people = array_values(array_filter($persisted, static fn (mixed $e): bool => $e instanceof Person));
        $this->assertCount(2, $people);
        $this->assertSame('Jane Doe', $people[0]->getName());
        $this->assertSame('John Smith', $people[1]->getName());
        $this->assertSame($job->getCompanyRef(), $people[0]->getCompany());
        $this->assertSame($user, $people[0]->getUser());

        $contacts = array_values(array_filter($persisted, static fn (mixed $e): bool => $e instanceof Contact));
        $this->assertCount(2, $contacts);
        $this->assertSame($people[0], $contacts[0]->getPerson());
        $this->assertSame('2026-09-01', $contacts[0]->getDate()?->format('Y-m-d'));
        $this->assertNull($contacts[0]->getDescription());
        $this->assertFalse($contacts[0]->getNeedsFollowUp());
        $this->assertSame($people[1], $contacts[1]->getPerson());
    }

    public function testDedupesPeopleAcrossInterviews(): void
    {
        $user = $this->user();
        $job = $this->job($user, 'Acme Inc');
        $interviewOne = $this->interview($job, ['Jane Doe']);
        $interviewTwo = $this->interview($job, ['Jane Doe']);

        $jobRepository = $this->createMock(JobRepository::class);
        $jobRepository->method('findAllWithSearch')->willReturn([$job]);

        $interviewRepository = $this->createMock(InterviewRepository::class);
        $interviewRepository->method('findAllWithJob')->willReturn([$interviewOne, $interviewTwo]);

        $companyRepository = $this->createMock(CompanyRepository::class);
        $companyRepository->method('findByNameForUser')->willReturn(null);

        $persisted = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (mixed $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $service = new NetworkingBackfillService(
            $entityManager,
            $jobRepository,
            $interviewRepository,
            $companyRepository,
            $this->createMock(PersonRepository::class),
            $this->createMock(ContactRepository::class),
        );

        $result = $service->backfill();

        $this->assertSame(['companies' => 1, 'jobsLinked' => 1, 'people' => 1, 'peopleSkipped' => 0, 'contacts' => 2, 'contactsSkipped' => 0], $result);
        $people = array_values(array_filter($persisted, static fn (mixed $e): bool => $e instanceof Person));
        $this->assertCount(1, $people);

        $contacts = array_values(array_filter($persisted, static fn (mixed $e): bool => $e instanceof Contact));
        $this->assertCount(2, $contacts);
        $this->assertSame($people[0], $contacts[0]->getPerson());
        $this->assertSame($people[0], $contacts[1]->getPerson());
    }

    public function testSkipsExistingPeople(): void
    {
        $user = $this->user();
        $job = $this->job($user, 'Acme Inc');
        $interview = $this->interview($job, ['Jane Doe']);

        $jobRepository = $this->createMock(JobRepository::class);
        $jobRepository->method('findAllWithSearch')->willReturn([$job]);

        $interviewRepository = $this->createMock(InterviewRepository::class);
        $interviewRepository->method('findAllWithJob')->willReturn([$interview]);

        $companyRepository = $this->createMock(CompanyRepository::class);
        $companyRepository->method('findByNameForUser')->willReturn(null);

        $existing = new Person();
        $this->setId($existing, 5);
        $existing->setUser($user)->setName('Jane Doe');

        $personRepository = $this->createMock(PersonRepository::class);
        $personRepository->method('findByNameAndCompany')->with($user->getId(), 'Jane Doe', null)->willReturn($existing);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $persisted = [];
        $entityManager->method('persist')->willReturnCallback(function (mixed $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });
        $entityManager->method('flush');

        $service = new NetworkingBackfillService(
            $entityManager,
            $jobRepository,
            $interviewRepository,
            $companyRepository,
            $personRepository,
            $this->createMock(ContactRepository::class),
        );

        $result = $service->backfill();

        $this->assertSame(['companies' => 1, 'jobsLinked' => 1, 'people' => 0, 'peopleSkipped' => 1, 'contacts' => 1, 'contactsSkipped' => 0], $result);
        $this->assertCount(2, $persisted);
        $this->assertInstanceOf(Company::class, $persisted[0]);
        $this->assertInstanceOf(Contact::class, $persisted[1]);
    }

    public function testCreatesContactsWithInterviewDateAndNotes(): void
    {
        $user = $this->user();
        $job = $this->job($user, 'Acme Inc');
        $interview = $this->interview($job, ['Jane Doe'], 'Swap the whiteboard round.');

        $jobRepository = $this->createMock(JobRepository::class);
        $jobRepository->method('findAllWithSearch')->willReturn([$job]);

        $interviewRepository = $this->createMock(InterviewRepository::class);
        $interviewRepository->method('findAllWithJob')->willReturn([$interview]);

        $companyRepository = $this->createMock(CompanyRepository::class);
        $companyRepository->method('findByNameForUser')->willReturn(null);

        $persisted = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (mixed $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });
        $entityManager->method('flush');

        $service = new NetworkingBackfillService(
            $entityManager,
            $jobRepository,
            $interviewRepository,
            $companyRepository,
            $this->createMock(PersonRepository::class),
            $this->createMock(ContactRepository::class),
        );

        $result = $service->backfill();

        $this->assertSame(['companies' => 1, 'jobsLinked' => 1, 'people' => 1, 'peopleSkipped' => 0, 'contacts' => 1, 'contactsSkipped' => 0], $result);
        $contacts = array_values(array_filter($persisted, static fn (mixed $e): bool => $e instanceof Contact));
        $this->assertCount(1, $contacts);
        $this->assertSame('2026-09-01', $contacts[0]->getDate()?->format('Y-m-d'));
        $this->assertSame('Swap the whiteboard round.', $contacts[0]->getDescription());
        $this->assertFalse($contacts[0]->getNeedsFollowUp());
    }

    public function testSkipsExistingContacts(): void
    {
        $user = $this->user();
        $job = $this->job($user, 'Acme Inc');
        $interview = $this->interview($job, ['Jane Doe']);

        $jobRepository = $this->createMock(JobRepository::class);
        $jobRepository->method('findAllWithSearch')->willReturn([$job]);

        $interviewRepository = $this->createMock(InterviewRepository::class);
        $interviewRepository->method('findAllWithJob')->willReturn([$interview]);

        $company = new Company();
        $this->setId($company, 7);
        $company->setUser($user)->setName('Acme Inc');

        $companyRepository = $this->createMock(CompanyRepository::class);
        $companyRepository->method('findByNameForUser')->with('Acme Inc', $user->getId())->willReturn($company);

        $existing = new Person();
        $this->setId($existing, 5);
        $existing->setUser($user)->setName('Jane Doe');

        $personRepository = $this->createMock(PersonRepository::class);
        $personRepository->method('findByNameAndCompany')->with($user->getId(), 'Jane Doe', 7)->willReturn($existing);

        $existingContact = new Contact();
        $this->setId($existingContact, 9);
        $existingContact->setPerson($existing);

        $contactRepository = $this->createMock(ContactRepository::class);
        $contactRepository->method('findForPersonAndDate')->willReturn($existingContact);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');
        $entityManager->method('flush');

        $service = new NetworkingBackfillService(
            $entityManager,
            $jobRepository,
            $interviewRepository,
            $companyRepository,
            $personRepository,
            $contactRepository,
        );

        $result = $service->backfill();

        $this->assertSame(['companies' => 0, 'jobsLinked' => 1, 'people' => 0, 'peopleSkipped' => 1, 'contacts' => 0, 'contactsSkipped' => 1], $result);
        $this->assertSame($interview, $existingContact->getInterview());
    }

    private function user(): User
    {
        $user = new User();
        $this->setId($user, 1);

        return $user;
    }

    private function searchFor(User $user): JobSearch
    {
        $search = new JobSearch();
        $search->setUser($user)->setName('Job Search');

        return $search;
    }

    private function job(User $user, string $company): Job
    {
        $job = new Job();
        $job->setJobSearch($this->searchFor($user));
        $job->setTitle('Software Engineer');
        $job->setCompany($company);

        return $job;
    }

    /**
     * @param array<int, string> $interviewers
     */
    private function interview(Job $job, array $interviewers, ?string $notes = null): Interview
    {
        $application = new Application();
        $application->setJob($job);

        $interview = new Interview();
        $interview->setApplication($application);
        $interview->setDate(new \DateTimeImmutable('2026-09-01'));
        $interview->setInterviewers($interviewers);
        $interview->setNotes($notes);

        return $interview;
    }

    private function setId(object $entity, int $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setValue($entity, $id);
    }
}
