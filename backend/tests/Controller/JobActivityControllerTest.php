<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\JobActivityController;
use App\Entity\JobActivity;
use App\Entity\User;
use App\Repository\JobActivityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

final class JobActivityControllerTest extends ControllerTestCase
{
    public function testIndexReturnsAllActivitiesForTheUser(): void
    {
        $activity = $this->activity(3, 'Wrote cover letter', '02:00');
        $repository = $this->createMock(JobActivityRepository::class);
        $repository->method('findByUser')->with(1)->willReturn([$activity]);

        $controller = new JobActivityController($repository, $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->index();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = $this->decodeJson($response);
        $this->assertCount(1, $data);
        $this->assertSame('Wrote cover letter', $data[0]['description']);
        $this->assertSame(2.0, (float) $data[0]['hoursSpent']);
    }

    public function testCreatePersistsAnActivity(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist')->with($this->isInstanceOf(JobActivity::class));
        $entityManager->expects($this->once())->method('flush');

        $controller = new JobActivityController($this->createMock(JobActivityRepository::class), $entityManager);
        $this->bindController($controller);

        $response = $controller->create($this->jsonRequest('POST', '/api/job-activities', [
            'description' => '  Phone screen  ',
            'date' => '2026-09-25',
            'hoursSpent' => 0.5,
            'details' => 'Went well.',
        ]));

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $data = $this->decodeJson($response);
        $this->assertSame('Phone screen', $data['description']);
        $this->assertSame('Went well.', $data['details']);
        $this->assertSame('2026-09-25', substr($data['date'], 0, 10));
        $this->assertSame(0.5, $data['hoursSpent']);
    }

    public function testCreateRequiresDescription(): void
    {
        $controller = new JobActivityController($this->createMock(JobActivityRepository::class), $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->create($this->jsonRequest('POST', '/api/job-activities', [
            'description' => '',
            'date' => '2026-09-25',
            'hoursSpent' => 1,
        ]));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('A description is required.', $this->decodeJson($response)['error']);
    }

    public function testCreateRequiresAValidDate(): void
    {
        $controller = new JobActivityController($this->createMock(JobActivityRepository::class), $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->create($this->jsonRequest('POST', '/api/job-activities', [
            'description' => 'Networking',
            'date' => 'yesterday',
            'hoursSpent' => 1,
        ]));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('A valid date (YYYY-MM-DD) is required.', $this->decodeJson($response)['error']);
    }

    public function testCreateRejectsOutOfRangeHours(): void
    {
        $controller = new JobActivityController($this->createMock(JobActivityRepository::class), $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->create($this->jsonRequest('POST', '/api/job-activities', [
            'description' => 'Networking',
            'date' => '2026-09-25',
            'hoursSpent' => 25,
        ]));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('Hours spent must be a number greater than 0 and at most 24.', $this->decodeJson($response)['error']);
    }

    public function testShowReturnsTheOwnedActivity(): void
    {
        $activity = $this->activity(3, 'Networking event');
        $repository = $this->createMock(JobActivityRepository::class);
        $repository->method('find')->with(3)->willReturn($activity);

        $controller = new JobActivityController($repository, $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->show(3);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('Networking event', $this->decodeJson($response)['description']);
    }

    public function testShowReturns404ForAnotherUsersActivity(): void
    {
        $otherUser = $this->createUser(2);
        $activity = $this->activity(3, 'Secret', null, $otherUser);
        $repository = $this->createMock(JobActivityRepository::class);
        $repository->method('find')->with(3)->willReturn($activity);

        $controller = new JobActivityController($repository, $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->show(3);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertSame('Job activity not found.', $this->decodeJson($response)['error']);
    }

    public function testUpdateModifiesFields(): void
    {
        $activity = $this->activity(3, 'Old', '01:00');
        $repository = $this->createMock(JobActivityRepository::class);
        $repository->method('find')->with(3)->willReturn($activity);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $controller = new JobActivityController($repository, $entityManager);
        $this->bindController($controller);

        $response = $controller->update(3, $this->jsonRequest('PATCH', '/api/job-activities/3', [
            'description' => 'New',
            'date' => '2026-10-01',
            'hoursSpent' => 3,
            'details' => null,
        ]));

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('New', $activity->getDescription());
        $this->assertSame('2026-10-01', $activity->getDate()->format('Y-m-d'));
        $this->assertSame(3.0, $activity->getHoursSpent());
        $this->assertNull($activity->getDetails());
    }

    public function testUpdateRejectsInvalidHours(): void
    {
        $activity = $this->activity(3, 'Old');
        $repository = $this->createMock(JobActivityRepository::class);
        $repository->method('find')->with(3)->willReturn($activity);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('flush');

        $controller = new JobActivityController($repository, $entityManager);
        $this->bindController($controller);

        $response = $controller->update(3, $this->jsonRequest('PATCH', '/api/job-activities/3', ['hoursSpent' => -1]));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    public function testDeleteRemovesTheActivity(): void
    {
        $activity = $this->activity(3, 'Cleanup');
        $repository = $this->createMock(JobActivityRepository::class);
        $repository->method('find')->with(3)->willReturn($activity);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('remove')->with($activity);
        $entityManager->expects($this->once())->method('flush');

        $controller = new JobActivityController($repository, $entityManager);
        $this->bindController($controller);

        $response = $controller->delete(3);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertTrue($this->decodeJson($response)['success']);
    }

    public function testDeleteReturns404ForMissingActivity(): void
    {
        $repository = $this->createMock(JobActivityRepository::class);
        $repository->method('find')->willReturn(null);

        $controller = new JobActivityController($repository, $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->delete(404);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    private function activity(int $id, string $description, ?string $hours = '01:00', ?User $user = null): JobActivity
    {
        $activity = new JobActivity();
        $this->setId($activity, $id);
        $activity->setUser($user ?? $this->user);
        $activity->setDescription($description);
        $activity->setDate(new \DateTimeImmutable('2026-09-20'));
        $activity->setHoursSpent(null === $hours ? 1.0 : (float) $hours);

        return $activity;
    }
}
