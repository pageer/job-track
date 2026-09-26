<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\TodoController;
use App\Entity\JobActivity;
use App\Entity\Todo;
use App\Entity\User;
use App\Repository\TodoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class TodoControllerTest extends ControllerTestCase
{
    public function testIndexReturnsAllTodosForTheUser(): void
    {
        $todo = $this->todo(1, 'Send follow-up', '2026-09-26');
        $repository = $this->createMock(TodoRepository::class);
        $repository->method('findByUser')->with(1)->willReturn([$todo]);

        $controller = new TodoController($repository, $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->index();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = $this->decodeJson($response);
        $this->assertCount(1, $data);
        $this->assertSame('Send follow-up', $data[0]['description']);
    }

    public function testCreatePersistsATodo(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist')->with($this->isInstanceOf(Todo::class));
        $entityManager->expects($this->once())->method('flush');

        $controller = new TodoController($this->createMock(TodoRepository::class), $entityManager);
        $this->bindController($controller);

        $response = $controller->create($this->jsonRequest('POST', '/api/todos', [
            'description' => '  Update resume  ',
            'targetDate' => '2026-09-27',
            'details' => 'Add the new project.',
        ]));

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $data = $this->decodeJson($response);
        $this->assertSame('Update resume', $data['description']);
        $this->assertSame('Add the new project.', $data['details']);
        $this->assertSame('2026-09-27', substr($data['targetDate'], 0, 10));
    }

    public function testCreateRequiresDescription(): void
    {
        $controller = new TodoController($this->createMock(TodoRepository::class), $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->create($this->jsonRequest('POST', '/api/todos', [
            'description' => '   ',
            'targetDate' => '2026-09-27',
        ]));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('A description is required.', $this->decodeJson($response)['error']);
    }

    public function testCreateRequiresAValidTargetDate(): void
    {
        $controller = new TodoController($this->createMock(TodoRepository::class), $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->create($this->jsonRequest('POST', '/api/todos', [
            'description' => 'Call recruiter',
            'targetDate' => 'not-a-date',
        ]));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('A valid target date (YYYY-MM-DD) is required.', $this->decodeJson($response)['error']);
    }

    public function testShowReturnsTheOwnedTodo(): void
    {
        $todo = $this->todo(7, 'Draft thank-you notes', '2026-09-28');
        $repository = $this->createMock(TodoRepository::class);
        $repository->method('find')->with(7)->willReturn($todo);

        $controller = new TodoController($repository, $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->show(7);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('Draft thank-you notes', $this->decodeJson($response)['description']);
    }

    public function testShowReturns404ForAnotherUsersTodo(): void
    {
        $otherUser = $this->createUser(2);
        $todo = $this->todo(7, 'Secret task', '2026-09-28', $otherUser);
        $repository = $this->createMock(TodoRepository::class);
        $repository->method('find')->with(7)->willReturn($todo);

        $controller = new TodoController($repository, $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->show(7);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertSame('Todo not found.', $this->decodeJson($response)['error']);
    }

    public function testUpdateAdjustsDescriptionAndTargetDate(): void
    {
        $todo = $this->todo(7, 'Old description', '2026-09-26');
        $repository = $this->createMock(TodoRepository::class);
        $repository->method('find')->with(7)->willReturn($todo);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $controller = new TodoController($repository, $entityManager);
        $this->bindController($controller);

        $response = $controller->update(7, $this->jsonRequest('PATCH', '/api/todos/7', [
            'description' => 'New description',
            'targetDate' => '2026-10-01',
            'details' => '',
        ]));

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('New description', $todo->getDescription());
        $this->assertSame('2026-10-01', $todo->getTargetDate()->format('Y-m-d'));
        $this->assertNull($todo->getDetails());
    }

    public function testUpdateReturns404ForMissingTodo(): void
    {
        $repository = $this->createMock(TodoRepository::class);
        $repository->method('find')->willReturn(null);

        $controller = new TodoController($repository, $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->update(404, $this->jsonRequest('PATCH', '/api/todos/404', ['description' => 'x']));

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testDeleteRemovesTheTodo(): void
    {
        $todo = $this->todo(7, 'Remove item', '2026-09-26');
        $repository = $this->createMock(TodoRepository::class);
        $repository->method('find')->with(7)->willReturn($todo);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('remove')->with($todo);
        $entityManager->expects($this->once())->method('flush');

        $controller = new TodoController($repository, $entityManager);
        $this->bindController($controller);

        $response = $controller->delete(7);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertTrue($this->decodeJson($response)['success']);
    }

    public function testCompleteCreatesAnActivityAndDeletesTheTodo(): void
    {
        $todo = $this->todo(7, 'Prepare portfolio', '2026-09-26');
        $repository = $this->createMock(TodoRepository::class);
        $repository->method('find')->with(7)->willReturn($todo);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $persistedActivity = null;
        $entityManager->expects($this->once())->method('persist')->with(
            $this->callback(function (mixed $activity) use (&$persistedActivity): bool {
                $persistedActivity = $activity;

                return $activity instanceof JobActivity;
            }),
        );
        $entityManager->expects($this->once())->method('remove')->with($todo);
        $entityManager->expects($this->once())->method('flush');

        $controller = new TodoController($repository, $entityManager);
        $this->bindController($controller);

        $response = $controller->complete(7, $this->jsonRequest('POST', '/api/todos/7/complete', [
            'date' => '2026-09-26',
            'hoursSpent' => 1.5,
        ]));

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $data = $this->decodeJson($response);
        $this->assertSame('Prepare portfolio', $data['description']);
        $this->assertSame(1.5, $data['hoursSpent']);

        $this->assertInstanceOf(JobActivity::class, $persistedActivity);
        $this->assertSame($this->user, $persistedActivity->getUser());
        $this->assertSame('2026-09-26', $persistedActivity->getDate()->format('Y-m-d'));
        $this->assertSame(1.5, $persistedActivity->getHoursSpent());
    }

    public function testCompleteDefaultsToTodayWhenNoDateGiven(): void
    {
        $todo = $this->todo(7, 'Prepare portfolio', '2026-09-26');
        $repository = $this->createMock(TodoRepository::class);
        $repository->method('find')->with(7)->willReturn($todo);

        $persistedActivity = null;
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (mixed $activity) use (&$persistedActivity): void {
            $persistedActivity = $activity;
        });

        $controller = new TodoController($repository, $entityManager);
        $this->bindController($controller);

        $controller->complete(7, $this->jsonRequest('POST', '/api/todos/7/complete', ['hoursSpent' => 0.5]));

        $this->assertInstanceOf(JobActivity::class, $persistedActivity);
        $this->assertSame((new \DateTimeImmutable('today'))->format('Y-m-d'), $persistedActivity->getDate()->format('Y-m-d'));
    }

    public function testCompleteRequiresValidHours(): void
    {
        $todo = $this->todo(7, 'Prepare portfolio', '2026-09-26');
        $repository = $this->createMock(TodoRepository::class);
        $repository->method('find')->with(7)->willReturn($todo);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');

        $controller = new TodoController($repository, $entityManager);
        $this->bindController($controller);

        $response = $controller->complete(7, $this->jsonRequest('POST', '/api/todos/7/complete', [
            'hoursSpent' => 0,
        ]));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('Hours spent must be a number greater than 0 and at most 24.', $this->decodeJson($response)['error']);
    }

    public function testCompleteReturns404ForAnotherUsersTodo(): void
    {
        $otherUser = $this->createUser(2);
        $todo = $this->todo(7, 'Secret task', '2026-09-28', $otherUser);
        $repository = $this->createMock(TodoRepository::class);
        $repository->method('find')->with(7)->willReturn($todo);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');
        $entityManager->expects($this->never())->method('remove');

        $controller = new TodoController($repository, $entityManager);
        $this->bindController($controller);

        $response = $controller->complete(7, $this->jsonRequest('POST', '/api/todos/7/complete', ['hoursSpent' => 1]));

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    private function todo(int $id, string $description, string $targetDate, ?User $user = null): Todo
    {
        $todo = new Todo();
        $this->setId($todo, $id);
        $todo->setUser($user ?? $this->user);
        $todo->setDescription($description);
        $todo->setTargetDate(new \DateTimeImmutable($targetDate));

        return $todo;
    }
}
