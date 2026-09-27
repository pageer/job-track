<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\InterviewController;
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
use Symfony\Component\HttpFoundation\Response;

final class InterviewControllerTest extends ControllerTestCase
{
    public function testIndexReturnsInterviewsForAnOwnedApplication(): void
    {
        $search = $this->createJobSearch(4);
        $job = $this->createJob(9, $search);
        $application = $this->createApplication(5, $job);
        $application->addInterview($this->interview(1, '2026-09-20T14:00:00', ['Alice', 'Bob']));

        $applicationRepository = $this->createMock(ApplicationRepository::class);
        $applicationRepository->method('find')->with(5)->willReturn($application);

        $controller = new InterviewController($this->createMock(InterviewRepository::class), $applicationRepository, $this->createMock(EntityManagerInterface::class), $this->createMock(PersonRepository::class), $this->createMock(ContactRepository::class));
        $this->bindController($controller);

        $response = $controller->index(5);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = $this->decodeJson($response);
        $this->assertCount(1, $data);
        $this->assertSame('2026-09-20T14:00:00', substr($data[0]['date'], 0, 19));
        $this->assertSame(['Alice', 'Bob'], $data[0]['interviewers']);
    }

    public function testIndexReturns404ForAnotherUsersApplication(): void
    {
        $otherUser = $this->createUser(2);
        $search = $this->createJobSearch(4, $otherUser);
        $job = $this->createJob(9, $search);
        $application = $this->createApplication(5, $job);
        $applicationRepository = $this->createMock(ApplicationRepository::class);
        $applicationRepository->method('find')->with(5)->willReturn($application);

        $controller = new InterviewController($this->createMock(InterviewRepository::class), $applicationRepository, $this->createMock(EntityManagerInterface::class), $this->createMock(PersonRepository::class), $this->createMock(ContactRepository::class));
        $this->bindController($controller);

        $response = $controller->index(5);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertSame('Application not found.', $this->decodeJson($response)['error']);
    }

    public function testCreatePersistsAnInterviewAndCreatesPeopleAndContacts(): void
    {
        $search = $this->createJobSearch(4);
        $job = $this->createJob(9, $search);
        $application = $this->createApplication(5, $job);
        $applicationRepository = $this->createMock(ApplicationRepository::class);
        $applicationRepository->method('find')->with(5)->willReturn($application);

        $persisted = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (mixed $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });
        $entityManager->expects($this->once())->method('flush');

        $controller = new InterviewController($this->createMock(InterviewRepository::class), $applicationRepository, $entityManager, $this->createMock(PersonRepository::class), $this->createMock(ContactRepository::class));
        $this->bindController($controller);

        $response = $controller->create(5, $this->jsonRequest('POST', '/api/applications/5/interviews', [
            'date' => '2026-10-01T10:30:00',
            'interviewers' => ['  Alice  ', '', ' Bob '],
            'notes' => 'Technical interview',
        ]));

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $data = $this->decodeJson($response);
        $this->assertSame('2026-10-01T10:30:00', substr($data['date'], 0, 19));
        $this->assertSame(['Alice', 'Bob'], $data['interviewers']);
        $this->assertSame('Technical interview', $data['notes']);

        $interviews = $this->persistedOfType($persisted, Interview::class);
        $this->assertCount(1, $interviews);
        $this->assertSame($application, $interviews[0]->getApplication());

        $people = $this->persistedOfType($persisted, Person::class);
        $this->assertCount(2, $people);
        $this->assertSame('Alice', $people[0]->getName());
        $this->assertSame('Bob', $people[1]->getName());
        $this->assertSame($this->user, $people[0]->getUser());
        $this->assertNull($people[0]->getCompany());

        $contacts = $this->persistedOfType($persisted, Contact::class);
        $this->assertCount(2, $contacts);
        $this->assertSame($people[0], $contacts[0]->getPerson());
        $this->assertSame('2026-10-01', $contacts[0]->getDate()?->format('Y-m-d'));
        $this->assertSame('Technical interview', $contacts[0]->getDescription());
        $this->assertFalse($contacts[0]->getNeedsFollowUp());
        $this->assertSame($people[1], $contacts[1]->getPerson());
    }

    public function testCreateAcceptsCommaSeparatedInterviewers(): void
    {
        $search = $this->createJobSearch(4);
        $job = $this->createJob(9, $search);
        $application = $this->createApplication(5, $job);
        $applicationRepository = $this->createMock(ApplicationRepository::class);
        $applicationRepository->method('find')->with(5)->willReturn($application);

        $persisted = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (mixed $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });
        $entityManager->method('flush');

        $controller = new InterviewController($this->createMock(InterviewRepository::class), $applicationRepository, $entityManager, $this->createMock(PersonRepository::class), $this->createMock(ContactRepository::class));
        $this->bindController($controller);

        $response = $controller->create(5, $this->jsonRequest('POST', '/api/applications/5/interviews', [
            'date' => '2026-10-01T10:30:00',
            'interviewers' => ' Alice, Bob ',
            'notes' => null,
        ]));

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $this->assertSame(['Alice', 'Bob'], $this->decodeJson($response)['interviewers']);

        $people = $this->persistedOfType($persisted, Person::class);
        $this->assertCount(2, $people);
        $contacts = $this->persistedOfType($persisted, Contact::class);
        $this->assertCount(2, $contacts);
        $this->assertNull($contacts[0]->getDescription());
    }

    public function testCreateReusesExistingPeopleAndContacts(): void
    {
        $search = $this->createJobSearch(4);
        $job = $this->createJob(9, $search);
        $company = $this->createCompany(3);
        $job->setCompanyRef($company);
        $application = $this->createApplication(5, $job);
        $applicationRepository = $this->createMock(ApplicationRepository::class);
        $applicationRepository->method('find')->with(5)->willReturn($application);

        $existingPerson = $this->createPerson(8, company: $company, name: 'Alice');
        $personRepository = $this->createMock(PersonRepository::class);
        $personRepository->method('findByNameAndCompany')->with($this->user->getId(), 'Alice', 3)->willReturn($existingPerson);

        $existingContact = new Contact();
        $this->setId($existingContact, 7);
        $existingContact->setPerson($existingPerson);

        $contactRepository = $this->createMock(ContactRepository::class);
        $contactRepository->method('findForPersonAndDate')->willReturn($existingContact);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist');
        $entityManager->expects($this->once())->method('flush');

        $controller = new InterviewController($this->createMock(InterviewRepository::class), $applicationRepository, $entityManager, $personRepository, $contactRepository);
        $this->bindController($controller);

        $response = $controller->create(5, $this->jsonRequest('POST', '/api/applications/5/interviews', [
            'date' => '2026-10-01T10:30:00',
            'interviewers' => ['Alice'],
            'notes' => 'Technical interview',
        ]));

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
    }

    public function testCreateRequiresAValidDate(): void
    {
        $search = $this->createJobSearch(4);
        $job = $this->createJob(9, $search);
        $application = $this->createApplication(5, $job);
        $applicationRepository = $this->createMock(ApplicationRepository::class);
        $applicationRepository->method('find')->with(5)->willReturn($application);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');

        $controller = new InterviewController($this->createMock(InterviewRepository::class), $applicationRepository, $entityManager, $this->createMock(PersonRepository::class), $this->createMock(ContactRepository::class));
        $this->bindController($controller);

        $response = $controller->create(5, $this->jsonRequest('POST', '/api/applications/5/interviews', [
            'date' => '',
            'interviewers' => [],
        ]));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('A valid interview date is required.', $this->decodeJson($response)['error']);
    }

    public function testUpdateModifiesTheInterview(): void
    {
        $search = $this->createJobSearch(4);
        $job = $this->createJob(9, $search);
        $application = $this->createApplication(5, $job);
        $interview = $this->interview(1, '2026-09-20T14:00:00', ['Alice'], $application);
        $interviewRepository = $this->createMock(InterviewRepository::class);
        $interviewRepository->method('find')->with(1)->willReturn($interview);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $controller = new InterviewController($interviewRepository, $this->createMock(ApplicationRepository::class), $entityManager, $this->createMock(PersonRepository::class), $this->createMock(ContactRepository::class));
        $this->bindController($controller);

        $response = $controller->update(1, $this->jsonRequest('PATCH', '/api/interviews/1', [
            'date' => '2026-10-05T09:00:00',
            'interviewers' => [],
            'notes' => '',
        ]));

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('2026-10-05', $interview->getDate()->format('Y-m-d'));
        $this->assertSame([], $interview->getInterviewers());
        $this->assertNull($interview->getNotes());
    }

    public function testUpdateSyncsPeopleAndContactsWhenInterviewersAreAdded(): void
    {
        $search = $this->createJobSearch(4);
        $job = $this->createJob(9, $search);
        $application = $this->createApplication(5, $job);
        $interview = $this->interview(1, '2026-10-01T10:30:00', [], $application);
        $interviewRepository = $this->createMock(InterviewRepository::class);
        $interviewRepository->method('find')->with(1)->willReturn($interview);

        $persisted = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (mixed $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });
        $entityManager->expects($this->once())->method('flush');

        $controller = new InterviewController($interviewRepository, $this->createMock(ApplicationRepository::class), $entityManager, $this->createMock(PersonRepository::class), $this->createMock(ContactRepository::class));
        $this->bindController($controller);

        $response = $controller->update(1, $this->jsonRequest('PATCH', '/api/interviews/1', [
            'interviewers' => ['Alice'],
            'notes' => 'Technical interview',
        ]));

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $people = $this->persistedOfType($persisted, Person::class);
        $this->assertCount(1, $people);
        $this->assertSame('Alice', $people[0]->getName());
        $this->assertSame($this->user, $people[0]->getUser());

        $contacts = $this->persistedOfType($persisted, Contact::class);
        $this->assertCount(1, $contacts);
        $this->assertSame($people[0], $contacts[0]->getPerson());
        $this->assertSame('2026-10-01', $contacts[0]->getDate()?->format('Y-m-d'));
        $this->assertSame('Technical interview', $contacts[0]->getDescription());
        $this->assertSame($interview, $contacts[0]->getInterview());
    }

    public function testUpdateRefreshesExistingInterviewContacts(): void
    {
        $search = $this->createJobSearch(4);
        $job = $this->createJob(9, $search);
        $application = $this->createApplication(5, $job);
        $interview = $this->interview(1, '2026-10-01T10:30:00', ['Alice'], $application);
        $interviewRepository = $this->createMock(InterviewRepository::class);
        $interviewRepository->method('find')->with(1)->willReturn($interview);

        $person = $this->createPerson(8, name: 'Alice');
        $personRepository = $this->createMock(PersonRepository::class);
        $personRepository->method('findByNameAndCompany')->with($this->user->getId(), 'Alice', null)->willReturn($person);

        $contact = $this->createContact(9, $person, '2026-10-01');
        $contact->setDescription('Old notes');
        $contact->setInterview($interview);
        $contactRepository = $this->createMock(ContactRepository::class);
        $contactRepository->method('findByInterview')->with(1)->willReturn([$contact]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');
        $entityManager->expects($this->once())->method('flush');

        $controller = new InterviewController($interviewRepository, $this->createMock(ApplicationRepository::class), $entityManager, $personRepository, $contactRepository);
        $this->bindController($controller);

        $response = $controller->update(1, $this->jsonRequest('PATCH', '/api/interviews/1', [
            'date' => '2026-10-05T14:00:00',
            'notes' => 'New notes',
        ]));

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('2026-10-05', $contact->getDate()?->format('Y-m-d'));
        $this->assertSame('New notes', $contact->getDescription());
        $this->assertSame($interview, $contact->getInterview());
        $this->assertSame($person, $contact->getPerson());
        $this->assertSame('2026-10-05', $interview->getDate()?->format('Y-m-d'));
        $this->assertSame('New notes', $interview->getNotes());
    }

    public function testUpdateReturns404ForAnotherUsersInterview(): void
    {
        $otherUser = $this->createUser(2);
        $search = $this->createJobSearch(4, $otherUser);
        $job = $this->createJob(9, $search);
        $application = $this->createApplication(5, $job);
        $interview = $this->interview(1, '2026-09-20T14:00:00', ['Alice'], $application);
        $interviewRepository = $this->createMock(InterviewRepository::class);
        $interviewRepository->method('find')->with(1)->willReturn($interview);

        $controller = new InterviewController($interviewRepository, $this->createMock(ApplicationRepository::class), $this->createMock(EntityManagerInterface::class), $this->createMock(PersonRepository::class), $this->createMock(ContactRepository::class));
        $this->bindController($controller);

        $response = $controller->update(1, $this->jsonRequest('PATCH', '/api/interviews/1', ['date' => '2026-10-01']));

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertSame('Interview not found.', $this->decodeJson($response)['error']);
    }

    public function testDeleteRemovesTheInterview(): void
    {
        $search = $this->createJobSearch(4);
        $job = $this->createJob(9, $search);
        $application = $this->createApplication(5, $job);
        $interview = $this->interview(1, '2026-09-20T14:00:00', [], $application);
        $interviewRepository = $this->createMock(InterviewRepository::class);
        $interviewRepository->method('find')->with(1)->willReturn($interview);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('remove')->with($interview);
        $entityManager->expects($this->once())->method('flush');

        $controller = new InterviewController($interviewRepository, $this->createMock(ApplicationRepository::class), $entityManager, $this->createMock(PersonRepository::class), $this->createMock(ContactRepository::class));
        $this->bindController($controller);

        $response = $controller->delete(1);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertTrue($this->decodeJson($response)['success']);
    }

    /**
     * @param list<string> $interviewers
     */
    private function interview(int $id, string $date, array $interviewers, ?Application $application = null): Interview
    {
        $interview = new Interview();
        $this->setId($interview, $id);
        $interview->setDate(new \DateTimeImmutable($date));
        $interview->setInterviewers($interviewers);
        if (null !== $application) {
            $interview->setApplication($application);
        }

        return $interview;
    }

    /**
     * @param array<int, mixed> $persisted
     *
     * @return array<int, object>
     */
    private function persistedOfType(array $persisted, string $class): array
    {
        return array_values(array_filter($persisted, static fn (mixed $entity): bool => $entity instanceof $class));
    }
}
