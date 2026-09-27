<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\PersonController;
use App\Entity\Company;
use App\Entity\Person;
use App\Repository\CompanyRepository;
use App\Repository\PersonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

final class PersonControllerTest extends ControllerTestCase
{
    public function testIndexReturnsOwnedPeopleWithLastContact(): void
    {
        $company = $this->createCompany(3, name: 'Acme Inc');
        $person = $this->createPerson(5, company: $company, name: 'Jane Doe');
        $person->addContact($this->createContact(8, $person, '2026-09-10', needsFollowUp: true));

        $personRepository = $this->createMock(PersonRepository::class);
        $personRepository->method('findByUser')->with($this->user->getId())->willReturn([$person]);

        $controller = new PersonController($personRepository, $this->createMock(CompanyRepository::class), $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->index();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = $this->decodeJson($response);
        $this->assertCount(1, $data);
        $this->assertSame('Jane Doe', $data[0]['name']);
        $this->assertSame(3, $data[0]['companyId']);
        $this->assertSame('Acme Inc', $data[0]['companyName']);
        $this->assertTrue($data[0]['needsFollowUp']);
        $this->assertSame('2026-09-10', substr($data[0]['lastContact']['date'], 0, 10));
        $this->assertTrue($data[0]['lastContact']['needsFollowUp']);
    }

    public function testIndexReturnsPeopleWithoutACompany(): void
    {
        $person = $this->createPerson(5, name: 'John Smith');
        $personRepository = $this->createMock(PersonRepository::class);
        $personRepository->method('findByUser')->with($this->user->getId())->willReturn([$person]);

        $controller = new PersonController($personRepository, $this->createMock(CompanyRepository::class), $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->index();

        $data = $this->decodeJson($response);
        $this->assertNull($data[0]['companyId']);
        $this->assertNull($data[0]['companyName']);
        $this->assertFalse($data[0]['needsFollowUp']);
        $this->assertNull($data[0]['lastContact']);
    }

    public function testShowReturnsThePersonDetailWithContacts(): void
    {
        $person = $this->createPerson(5, name: 'Jane Doe');
        $person->addContact($this->createContact(8, $person, '2026-09-01'));
        $person->setNotes('Met at a conference.');

        $personRepository = $this->createMock(PersonRepository::class);
        $personRepository->method('find')->with(5)->willReturn($person);

        $controller = new PersonController($personRepository, $this->createMock(CompanyRepository::class), $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->show(5);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = $this->decodeJson($response);
        $this->assertSame('Met at a conference.', $data['notes']);
        $this->assertCount(1, $data['contacts']);
        $this->assertSame('2026-09-01', substr($data['contacts'][0]['date'], 0, 10));
        $this->assertSame(5, $data['contacts'][0]['personId']);
    }

    public function testShowReturns404ForAnotherUsersPerson(): void
    {
        $otherUser = $this->createUser(2);
        $person = $this->createPerson(5, $otherUser);
        $personRepository = $this->createMock(PersonRepository::class);
        $personRepository->method('find')->with(5)->willReturn($person);

        $controller = new PersonController($personRepository, $this->createMock(CompanyRepository::class), $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->show(5);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertSame('Person not found.', $this->decodeJson($response)['error']);
    }

    public function testCreatePersistsPersonWithAnOwnedCompany(): void
    {
        $company = $this->createCompany(3, name: 'Acme Inc');
        $companyRepository = $this->createMock(CompanyRepository::class);
        $companyRepository->method('find')->with(3)->willReturn($company);

        $persisted = null;
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (mixed $entity) use (&$persisted): void {
            $persisted = $entity;
        });
        $entityManager->expects($this->once())->method('flush');

        $controller = new PersonController($this->createMock(PersonRepository::class), $companyRepository, $entityManager);
        $this->bindController($controller);

        $response = $controller->create($this->jsonRequest('POST', '/api/people', [
            'name' => '  Jane Doe  ',
            'companyId' => 3,
            'email' => 'jane@example.com',
            'linkedInUrl' => 'https://linkedin.com/in/jane',
            'notes' => 'Met at a conference.',
        ]));

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $data = $this->decodeJson($response);
        $this->assertSame('Jane Doe', $data['name']);
        $this->assertSame(3, $data['companyId']);

        $this->assertInstanceOf(Person::class, $persisted);
        $this->assertSame($company, $persisted->getCompany());
        $this->assertSame('jane@example.com', $persisted->getEmail());
        $this->assertSame('https://linkedin.com/in/jane', $persisted->getLinkedInUrl());
    }

    public function testCreatePersistsPersonWithoutACompany(): void
    {
        $persisted = null;
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (mixed $entity) use (&$persisted): void {
            $persisted = $entity;
        });
        $entityManager->expects($this->once())->method('flush');

        $companyRepository = $this->createMock(CompanyRepository::class);
        $companyRepository->method('find')->willReturn(null);

        $controller = new PersonController($this->createMock(PersonRepository::class), $companyRepository, $entityManager);
        $this->bindController($controller);

        $response = $controller->create($this->jsonRequest('POST', '/api/people', ['name' => 'John Smith']));

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $this->assertInstanceOf(Person::class, $persisted);
        $this->assertNull($persisted->getCompany());
        $this->assertNull($persisted->getEmail());
        $this->assertNull($persisted->getNotes());
    }

    public function testCreateCreatesAndLinksACompanyByName(): void
    {
        $persisted = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (mixed $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });
        $entityManager->expects($this->once())->method('flush');

        $companyRepository = $this->createMock(CompanyRepository::class);
        $companyRepository->method('findByNameForUser')->willReturn(null);

        $controller = new PersonController($this->createMock(PersonRepository::class), $companyRepository, $entityManager);
        $this->bindController($controller);

        $response = $controller->create($this->jsonRequest('POST', '/api/people', [
            'name' => 'Jane Doe',
            'companyName' => 'Acme Inc',
        ]));

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $company = $persisted[0];
        $this->assertInstanceOf(Company::class, $company);
        $this->assertSame('Acme Inc', $company->getName());
        $person = $persisted[1];
        $this->assertInstanceOf(Person::class, $person);
        $this->assertSame($company, $person->getCompany());
    }

    public function testCreateRejectsABlankName(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');

        $controller = new PersonController($this->createMock(PersonRepository::class), $this->createMock(CompanyRepository::class), $entityManager);
        $this->bindController($controller);

        $response = $controller->create($this->jsonRequest('POST', '/api/people', ['name' => '  ']));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('A name is required.', $this->decodeJson($response)['error']);
    }

    public function testCreateRejectsAnInvalidEmail(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');

        $controller = new PersonController($this->createMock(PersonRepository::class), $this->createMock(CompanyRepository::class), $entityManager);
        $this->bindController($controller);

        $response = $controller->create($this->jsonRequest('POST', '/api/people', [
            'name' => 'Jane Doe',
            'email' => 'not-an-email',
        ]));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('Please provide a valid email address.', $this->decodeJson($response)['error']);
    }

    public function testCreateRejectsACompanyFromAnotherUser(): void
    {
        $otherUser = $this->createUser(2);
        $company = $this->createCompany(3, $otherUser);
        $companyRepository = $this->createMock(CompanyRepository::class);
        $companyRepository->method('find')->with(3)->willReturn($company);

        $controller = new PersonController($this->createMock(PersonRepository::class), $companyRepository, $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->create($this->jsonRequest('POST', '/api/people', [
            'name' => 'Jane Doe',
            'companyId' => 3,
        ]));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('Company not found.', $this->decodeJson($response)['error']);
    }

    public function testUpdateModifiesPersonFields(): void
    {
        $person = $this->createPerson(5, name: 'Jane Doe');
        $personRepository = $this->createMock(PersonRepository::class);
        $personRepository->method('find')->with(5)->willReturn($person);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $controller = new PersonController($personRepository, $this->createMock(CompanyRepository::class), $entityManager);
        $this->bindController($controller);

        $response = $controller->update(5, $this->jsonRequest('PATCH', '/api/people/5', [
            'name' => 'Jane Smith',
            'email' => 'jane.smith@example.com',
            'linkedInUrl' => '',
            'companyName' => 'Globex',
        ]));

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('Jane Smith', $person->getName());
        $this->assertSame('jane.smith@example.com', $person->getEmail());
        $this->assertNull($person->getLinkedInUrl());
        $this->assertSame('Globex', $person->getCompany()?->getName());
    }

    public function testUpdateReturns404ForAnotherUsersPerson(): void
    {
        $otherUser = $this->createUser(2);
        $person = $this->createPerson(5, $otherUser);
        $personRepository = $this->createMock(PersonRepository::class);
        $personRepository->method('find')->with(5)->willReturn($person);

        $controller = new PersonController($personRepository, $this->createMock(CompanyRepository::class), $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->update(5, $this->jsonRequest('PATCH', '/api/people/5', ['name' => 'Jane']));

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertSame('Person not found.', $this->decodeJson($response)['error']);
    }

    public function testDeleteRemovesPerson(): void
    {
        $person = $this->createPerson(5, name: 'Jane Doe');
        $personRepository = $this->createMock(PersonRepository::class);
        $personRepository->method('find')->with(5)->willReturn($person);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('remove')->with($person);
        $entityManager->expects($this->once())->method('flush');

        $controller = new PersonController($personRepository, $this->createMock(CompanyRepository::class), $entityManager);
        $this->bindController($controller);

        $response = $controller->delete(5);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertTrue($this->decodeJson($response)['success']);
    }
}
