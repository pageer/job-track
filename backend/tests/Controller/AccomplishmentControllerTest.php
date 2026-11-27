<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\AccomplishmentController;
use App\Entity\Accomplishment;
use App\Entity\User;
use App\Repository\AccomplishmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

final class AccomplishmentControllerTest extends ControllerTestCase
{
    public function testIndexReturnsAllAccomplishmentsForTheUser(): void
    {
        $accomplishment = $this->accomplishment(1, '2026-03-01', 'Acme Inc', 'Shipped the billing rewrite.');
        $repository = $this->createMock(AccomplishmentRepository::class);
        $repository->method('findByUser')->with(1)->willReturn([$accomplishment]);

        $controller = new AccomplishmentController($repository, $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->index();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = $this->decodeJson($response);
        $this->assertCount(1, $data);
        $this->assertSame('Acme Inc', $data[0]['company']);
        $this->assertSame('Shipped the billing rewrite.', $data[0]['description']);
        $this->assertSame('2026-03-01', substr((string) $data[0]['date'], 0, 10));
    }

    public function testCreatePersistsAnAccomplishment(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist')->with($this->isInstanceOf(Accomplishment::class));
        $entityManager->expects($this->once())->method('flush');

        $controller = new AccomplishmentController($this->createMock(AccomplishmentRepository::class), $entityManager);
        $this->bindController($controller);

        $response = $controller->create($this->jsonRequest('POST', '/api/accomplishments', [
            'date' => '2026-03-01',
            'company' => '  Acme Inc  ',
            'description' => '  Shipped the billing rewrite.  ',
        ]));

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $data = $this->decodeJson($response);
        $this->assertSame('Acme Inc', $data['company']);
        $this->assertSame('Shipped the billing rewrite.', $data['description']);
        $this->assertSame('2026-03-01', substr((string) $data['date'], 0, 10));
    }

    public function testCreateRequiresACompanyName(): void
    {
        $controller = new AccomplishmentController($this->createMock(AccomplishmentRepository::class), $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->create($this->jsonRequest('POST', '/api/accomplishments', [
            'date' => '2026-03-01',
            'company' => '   ',
            'description' => 'Shipped the billing rewrite.',
        ]));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('A company name is required.', $this->decodeJson($response)['error']);
    }

    public function testCreateRequiresADescription(): void
    {
        $controller = new AccomplishmentController($this->createMock(AccomplishmentRepository::class), $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->create($this->jsonRequest('POST', '/api/accomplishments', [
            'date' => '2026-03-01',
            'company' => 'Acme Inc',
            'description' => '   ',
        ]));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('A description is required.', $this->decodeJson($response)['error']);
    }

    public function testCreateRequiresAValidDate(): void
    {
        $controller = new AccomplishmentController($this->createMock(AccomplishmentRepository::class), $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        foreach (['not-a-date', '2026-02-30', '2026-3-01', '', null] as $date) {
            $response = $controller->create($this->jsonRequest('POST', '/api/accomplishments', [
                'date' => $date,
                'company' => 'Acme Inc',
                'description' => 'Shipped the billing rewrite.',
            ]));

            $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode(), 'Date ' . var_export($date, true));
            $this->assertSame('A valid date (YYYY-MM-DD) is required.', $this->decodeJson($response)['error']);
        }
    }

    public function testShowReturnsTheOwnedAccomplishment(): void
    {
        $accomplishment = $this->accomplishment(7, '2026-03-01', 'Acme Inc', 'Shipped the billing rewrite.');
        $repository = $this->createMock(AccomplishmentRepository::class);
        $repository->method('find')->with(7)->willReturn($accomplishment);

        $controller = new AccomplishmentController($repository, $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->show(7);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('Acme Inc', $this->decodeJson($response)['company']);
    }

    public function testShowReturns404ForAnotherUsersAccomplishment(): void
    {
        $otherUser = $this->createUser(2);
        $accomplishment = $this->accomplishment(7, '2026-03-01', 'Acme Inc', 'Secret', $otherUser);
        $repository = $this->createMock(AccomplishmentRepository::class);
        $repository->method('find')->with(7)->willReturn($accomplishment);

        $controller = new AccomplishmentController($repository, $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->show(7);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertSame('Accomplishment not found.', $this->decodeJson($response)['error']);
    }

    public function testUpdateAdjustsFields(): void
    {
        $accomplishment = $this->accomplishment(7, '2026-03-01', 'Acme Inc', 'Old description');
        $repository = $this->createMock(AccomplishmentRepository::class);
        $repository->method('find')->with(7)->willReturn($accomplishment);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $controller = new AccomplishmentController($repository, $entityManager);
        $this->bindController($controller);

        $response = $controller->update(7, $this->jsonRequest('PATCH', '/api/accomplishments/7', [
            'date' => '2026-04-01',
            'company' => 'Globex',
            'description' => 'New description',
        ]));

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('2026-04-01', $accomplishment->getDate()?->format('Y-m-d'));
        $this->assertSame('Globex', $accomplishment->getCompany());
        $this->assertSame('New description', $accomplishment->getDescription());
    }

    public function testUpdateRejectsBlankFields(): void
    {
        $accomplishment = $this->accomplishment(7, '2026-03-01', 'Acme Inc', 'Description');
        $repository = $this->createMock(AccomplishmentRepository::class);
        $repository->method('find')->with(7)->willReturn($accomplishment);

        $controller = new AccomplishmentController($repository, $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->update(7, $this->jsonRequest('PATCH', '/api/accomplishments/7', ['company' => '  ']));
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

        $response = $controller->update(7, $this->jsonRequest('PATCH', '/api/accomplishments/7', ['description' => '']));
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

        $response = $controller->update(7, $this->jsonRequest('PATCH', '/api/accomplishments/7', ['date' => 'nope']));
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    public function testUpdateReturns404ForAnotherUsersAccomplishment(): void
    {
        $otherUser = $this->createUser(2);
        $accomplishment = $this->accomplishment(7, '2026-03-01', 'Acme Inc', 'Secret', $otherUser);
        $repository = $this->createMock(AccomplishmentRepository::class);
        $repository->method('find')->with(7)->willReturn($accomplishment);

        $controller = new AccomplishmentController($repository, $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->update(7, $this->jsonRequest('PATCH', '/api/accomplishments/7', ['company' => 'Globex']));

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertSame('Acme Inc', $accomplishment->getCompany());
    }

    public function testDeleteRemovesTheAccomplishment(): void
    {
        $accomplishment = $this->accomplishment(7, '2026-03-01', 'Acme Inc', 'Shipped the billing rewrite.');
        $repository = $this->createMock(AccomplishmentRepository::class);
        $repository->method('find')->with(7)->willReturn($accomplishment);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('remove')->with($accomplishment);
        $entityManager->expects($this->once())->method('flush');

        $controller = new AccomplishmentController($repository, $entityManager);
        $this->bindController($controller);

        $response = $controller->delete(7);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertTrue($this->decodeJson($response)['success']);
    }

    public function testDeleteReturns404ForAnotherUsersAccomplishment(): void
    {
        $otherUser = $this->createUser(2);
        $accomplishment = $this->accomplishment(7, '2026-03-01', 'Acme Inc', 'Secret', $otherUser);
        $repository = $this->createMock(AccomplishmentRepository::class);
        $repository->method('find')->with(7)->willReturn($accomplishment);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('remove');

        $controller = new AccomplishmentController($repository, $entityManager);
        $this->bindController($controller);

        $response = $controller->delete(7);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    private function accomplishment(int $id, string $date, string $company, string $description, ?User $user = null): Accomplishment
    {
        $accomplishment = new Accomplishment();
        $this->setId($accomplishment, $id);
        $accomplishment->setUser($user ?? $this->user);
        $accomplishment->setDate(new \DateTimeImmutable($date));
        $accomplishment->setCompany($company);
        $accomplishment->setDescription($description);

        return $accomplishment;
    }
}
