<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\ContactController;
use App\Entity\Contact;
use App\Entity\Person;
use App\Repository\ContactRepository;
use App\Repository\PersonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

final class ContactControllerTest extends ControllerTestCase
{
    public function testIndexReturnsContactsOfAnOwnedPerson(): void
    {
        $person = $this->createPerson(5, name: 'Jane Doe');
        $person->addContact($this->createContact(8, $person, '2026-09-01', needsFollowUp: true));

        $personRepository = $this->createMock(PersonRepository::class);
        $personRepository->method('find')->with(5)->willReturn($person);

        $controller = new ContactController($this->createMock(ContactRepository::class), $personRepository, $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->index(5);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = $this->decodeJson($response);
        $this->assertCount(1, $data);
        $this->assertSame('2026-09-01', substr($data[0]['date'], 0, 10));
        $this->assertTrue($data[0]['needsFollowUp']);
        $this->assertSame(5, $data[0]['personId']);
    }

    public function testIndexReturns404ForAnotherUsersPerson(): void
    {
        $otherUser = $this->createUser(2);
        $person = $this->createPerson(5, $otherUser);
        $personRepository = $this->createMock(PersonRepository::class);
        $personRepository->method('find')->with(5)->willReturn($person);

        $controller = new ContactController($this->createMock(ContactRepository::class), $personRepository, $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->index(5);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertSame('Person not found.', $this->decodeJson($response)['error']);
    }

    public function testCreatePersistsContact(): void
    {
        $person = $this->createPerson(5, name: 'Jane Doe');
        $personRepository = $this->createMock(PersonRepository::class);
        $personRepository->method('find')->with(5)->willReturn($person);

        $persisted = null;
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (mixed $entity) use (&$persisted): void {
            $persisted = $entity;
        });
        $entityManager->expects($this->once())->method('flush');

        $controller = new ContactController($this->createMock(ContactRepository::class), $personRepository, $entityManager);
        $this->bindController($controller);

        $response = $controller->create(5, $this->jsonRequest('POST', '/api/people/5/contacts', [
            'date' => '2026-09-27',
            'description' => '  Coffee chat  ',
            'needsFollowUp' => true,
        ]));

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $this->assertInstanceOf(Contact::class, $persisted);
        $this->assertSame('2026-09-27', $persisted->getDate()->format('Y-m-d'));
        $this->assertSame('  Coffee chat  ', $persisted->getDescription());
        $this->assertTrue($persisted->getNeedsFollowUp());
        $this->assertSame($person, $persisted->getPerson());
    }

    public function testCreateDefaultsToNoFollowUp(): void
    {
        $person = $this->createPerson(5, name: 'Jane Doe');
        $personRepository = $this->createMock(PersonRepository::class);
        $personRepository->method('find')->with(5)->willReturn($person);

        $persisted = null;
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (mixed $entity) use (&$persisted): void {
            $persisted = $entity;
        });

        $controller = new ContactController($this->createMock(ContactRepository::class), $personRepository, $entityManager);
        $this->bindController($controller);

        $controller->create(5, $this->jsonRequest('POST', '/api/people/5/contacts', ['date' => '2026-09-27']));

        $this->assertInstanceOf(Contact::class, $persisted);
        $this->assertFalse($persisted->getNeedsFollowUp());
        $this->assertNull($persisted->getDescription());
    }

    public function testCreateRequiresAValidDate(): void
    {
        $person = $this->createPerson(5, name: 'Jane Doe');
        $personRepository = $this->createMock(PersonRepository::class);
        $personRepository->method('find')->with(5)->willReturn($person);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');

        $controller = new ContactController($this->createMock(ContactRepository::class), $personRepository, $entityManager);
        $this->bindController($controller);

        $response = $controller->create(5, $this->jsonRequest('POST', '/api/people/5/contacts', ['date' => 'not-a-date']));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('A valid date (YYYY-MM-DD) is required.', $this->decodeJson($response)['error']);
    }

    public function testCreateReturns404ForAnotherUsersPerson(): void
    {
        $otherUser = $this->createUser(2);
        $person = $this->createPerson(5, $otherUser);
        $personRepository = $this->createMock(PersonRepository::class);
        $personRepository->method('find')->with(5)->willReturn($person);

        $controller = new ContactController($this->createMock(ContactRepository::class), $personRepository, $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->create(5, $this->jsonRequest('POST', '/api/people/5/contacts', ['date' => '2026-09-27']));

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testUpdateTogglesFollowUpAndDescription(): void
    {
        $person = $this->createPerson(5, name: 'Jane Doe');
        $contact = $this->createContact(8, $person, '2026-09-01');
        $contactRepository = $this->createMock(ContactRepository::class);
        $contactRepository->method('find')->with(8)->willReturn($contact);

        $personRepository = $this->createMock(PersonRepository::class);
        $personRepository->method('find')->with(5)->willReturn($person);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $controller = new ContactController($contactRepository, $personRepository, $entityManager);
        $this->bindController($controller);

        $response = $controller->update(5, 8, $this->jsonRequest('PATCH', '/api/people/5/contacts/8', [
            'date' => '2026-10-01',
            'description' => '  Sent resume  ',
            'needsFollowUp' => true,
        ]));

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('2026-10-01', $contact->getDate()->format('Y-m-d'));
        $this->assertSame('  Sent resume  ', $contact->getDescription());
        $this->assertTrue($contact->getNeedsFollowUp());
    }

    public function testUpdateRejectsAnInvalidDate(): void
    {
        $person = $this->createPerson(5, name: 'Jane Doe');
        $contact = $this->createContact(8, $person, '2026-09-01');
        $contactRepository = $this->createMock(ContactRepository::class);
        $contactRepository->method('find')->with(8)->willReturn($contact);

        $personRepository = $this->createMock(PersonRepository::class);
        $personRepository->method('find')->with(5)->willReturn($person);

        $controller = new ContactController($contactRepository, $personRepository, $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->update(5, 8, $this->jsonRequest('PATCH', '/api/people/5/contacts/8', ['date' => 'nope']));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('The date must be a valid date (YYYY-MM-DD).', $this->decodeJson($response)['error']);
    }

    public function testUpdateReturns404WhenContactBelongsToAnotherPersonOrUser(): void
    {
        $person = $this->createPerson(5, name: 'Jane Doe');
        $contact = $this->createContact(8, $person, '2026-09-01');
        $contactRepository = $this->createMock(ContactRepository::class);
        $contactRepository->method('find')->with(8)->willReturn($contact);

        $personRepository = $this->createMock(PersonRepository::class);
        $personRepository->method('find')->willReturn(null);

        $controller = new ContactController($contactRepository, $personRepository, $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->update(9, 8, $this->jsonRequest('PATCH', '/api/people/9/contacts/8', ['description' => 'Nope']));

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertSame('Contact not found.', $this->decodeJson($response)['error']);
    }

    public function testDeleteRemovesContact(): void
    {
        $person = $this->createPerson(5, name: 'Jane Doe');
        $contact = $this->createContact(8, $person, '2026-09-01');
        $contactRepository = $this->createMock(ContactRepository::class);
        $contactRepository->method('find')->with(8)->willReturn($contact);

        $personRepository = $this->createMock(PersonRepository::class);
        $personRepository->method('find')->with(5)->willReturn($person);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('remove')->with($contact);
        $entityManager->expects($this->once())->method('flush');

        $controller = new ContactController($contactRepository, $personRepository, $entityManager);
        $this->bindController($controller);

        $response = $controller->delete(5, 8);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertTrue($this->decodeJson($response)['success']);
    }

    public function testDeleteReturns404WithoutRemoving(): void
    {
        $contactRepository = $this->createMock(ContactRepository::class);
        $contactRepository->method('find')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('remove');

        $controller = new ContactController($contactRepository, $this->createMock(PersonRepository::class), $entityManager);
        $this->bindController($controller);

        $response = $controller->delete(5, 8);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }
}
