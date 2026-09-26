<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\JobSearchController;
use App\Entity\JobSearch;
use App\Entity\User;
use App\Repository\JobSearchRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

final class JobSearchControllerTest extends ControllerTestCase
{
    public function testIndexReturnsAllSearchesForTheUser(): void
    {
        $search = $this->search(4, 'Summer 2026');
        $repository = $this->createMock(JobSearchRepository::class);
        $repository->method('findByUser')->with(1)->willReturn([$search]);

        $controller = new JobSearchController($repository, $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->index();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = $this->decodeJson($response);
        $this->assertCount(1, $data);
        $this->assertSame('Summer 2026', $data[0]['name']);
    }

    public function testCreatePersistsASearch(): void
    {
        $persisted = null;
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (mixed $entity) use (&$persisted): void {
            $persisted = $entity;
        });
        $entityManager->expects($this->once())->method('flush');

        $controller = new JobSearchController($this->createMock(JobSearchRepository::class), $entityManager);
        $this->bindController($controller);

        $response = $controller->create($this->jsonRequest('POST', '/api/job-searches', [
            'name' => '  Backend roles  ',
            'startDate' => '2026-09-01',
            'endDate' => '2026-12-31',
        ]));

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $data = $this->decodeJson($response);
        $this->assertSame('Backend roles', $data['name']);
        $this->assertSame('2026-09-01', substr($data['startDate'], 0, 10));

        $this->assertInstanceOf(JobSearch::class, $persisted);
        $this->assertSame($this->user, $persisted->getUser());
        $this->assertSame('2026-12-31', $persisted->getEndDate()->format('Y-m-d'));
    }

    public function testCreateRequiresName(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');

        $controller = new JobSearchController($this->createMock(JobSearchRepository::class), $entityManager);
        $this->bindController($controller);

        $response = $controller->create($this->jsonRequest('POST', '/api/job-searches', [
            'name' => '   ',
            'startDate' => '2026-09-01',
        ]));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('A name is required.', $this->decodeJson($response)['error']);
    }

    public function testCreateRequiresAValidStartDate(): void
    {
        $controller = new JobSearchController($this->createMock(JobSearchRepository::class), $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->create($this->jsonRequest('POST', '/api/job-searches', [
            'name' => 'Roles',
            'startDate' => 'next Monday',
        ]));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('A valid startDate (YYYY-MM-DD) is required.', $this->decodeJson($response)['error']);
    }

    public function testCreateRejectsAnInvalidEndDate(): void
    {
        $controller = new JobSearchController($this->createMock(JobSearchRepository::class), $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->create($this->jsonRequest('POST', '/api/job-searches', [
            'name' => 'Roles',
            'startDate' => '2026-09-01',
            'endDate' => 'sometimes',
        ]));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('The endDate must be a valid date (YYYY-MM-DD).', $this->decodeJson($response)['error']);
    }

    public function testShowReturnsTheOwnedSearchWithJobs(): void
    {
        $search = $this->search(4, 'Summer 2026');
        $repository = $this->createMock(JobSearchRepository::class);
        $repository->method('find')->with(4)->willReturn($search);

        $controller = new JobSearchController($repository, $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->show(4);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = $this->decodeJson($response);
        $this->assertSame('Summer 2026', $data['name']);
        $this->assertArrayHasKey('jobs', $data);
        $this->assertSame(0, $data['jobCount']);
    }

    public function testShowReturns404ForAnotherUsersSearch(): void
    {
        $otherUser = $this->createUser(2);
        $search = $this->search(4, 'Secret', $otherUser);
        $repository = $this->createMock(JobSearchRepository::class);
        $repository->method('find')->with(4)->willReturn($search);

        $controller = new JobSearchController($repository, $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->show(4);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertSame('Job search not found.', $this->decodeJson($response)['error']);
    }

    public function testUpdateModifiesTheSearch(): void
    {
        $search = $this->search(4, 'Old name');
        $repository = $this->createMock(JobSearchRepository::class);
        $repository->method('find')->with(4)->willReturn($search);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $controller = new JobSearchController($repository, $entityManager);
        $this->bindController($controller);

        $response = $controller->update(4, $this->jsonRequest('PATCH', '/api/job-searches/4', [
            'name' => 'New name',
            'endDate' => '',
        ]));

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('New name', $search->getName());
        $this->assertNull($search->getEndDate());
    }

    public function testUpdateRejectsBlankName(): void
    {
        $search = $this->search(4, 'Old name');
        $repository = $this->createMock(JobSearchRepository::class);
        $repository->method('find')->with(4)->willReturn($search);

        $controller = new JobSearchController($repository, $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->update(4, $this->jsonRequest('PATCH', '/api/job-searches/4', ['name' => '  ']));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    public function testDeleteRemovesTheSearch(): void
    {
        $search = $this->search(4, 'Old search');
        $repository = $this->createMock(JobSearchRepository::class);
        $repository->method('find')->with(4)->willReturn($search);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('remove')->with($search);
        $entityManager->expects($this->once())->method('flush');

        $controller = new JobSearchController($repository, $entityManager);
        $this->bindController($controller);

        $response = $controller->delete(4);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertTrue($this->decodeJson($response)['success']);
    }

    private function search(int $id, string $name, ?User $user = null): JobSearch
    {
        $search = new JobSearch();
        $this->setId($search, $id);
        $search->setUser($user ?? $this->user);
        $search->setName($name);
        $search->setStartDate(new \DateTimeImmutable('2026-09-01'));

        return $search;
    }
}
