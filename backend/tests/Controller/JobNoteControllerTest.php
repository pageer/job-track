<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\JobNoteController;
use App\Entity\Job;
use App\Entity\JobNote;
use App\Entity\User;
use App\Repository\JobNoteRepository;
use App\Repository\JobRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

final class JobNoteControllerTest extends ControllerTestCase
{
    public function testIndexReturnsNotesForAnOwnedJob(): void
    {
        $search = $this->createJobSearch(4);
        $job = $this->createJob(9, $search);
        $job->addNote($this->note(1, 'First interview went well'));

        $jobRepository = $this->createMock(JobRepository::class);
        $jobRepository->method('find')->with(9)->willReturn($job);

        $controller = new JobNoteController($this->createMock(JobNoteRepository::class), $jobRepository, $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->index(9);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = $this->decodeJson($response);
        $this->assertCount(1, $data);
        $this->assertSame('First interview went well', $data[0]['content']);
    }

    public function testIndexReturns404ForAnotherUsersJob(): void
    {
        $otherUser = $this->createUser(2);
        $search = $this->createJobSearch(4, $otherUser);
        $job = $this->createJob(9, $search);
        $jobRepository = $this->createMock(JobRepository::class);
        $jobRepository->method('find')->with(9)->willReturn($job);

        $controller = new JobNoteController($this->createMock(JobNoteRepository::class), $jobRepository, $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->index(9);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertSame('Job not found.', $this->decodeJson($response)['error']);
    }

    public function testCreatePersistsANote(): void
    {
        $search = $this->createJobSearch(4);
        $job = $this->createJob(9, $search);
        $jobRepository = $this->createMock(JobRepository::class);
        $jobRepository->method('find')->with(9)->willReturn($job);

        $persisted = null;
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (mixed $entity) use (&$persisted): void {
            $persisted = $entity;
        });
        $entityManager->expects($this->once())->method('flush');

        $controller = new JobNoteController($this->createMock(JobNoteRepository::class), $jobRepository, $entityManager);
        $this->bindController($controller);

        $response = $controller->create(9, $this->jsonRequest('POST', '/api/jobs/9/notes', [
            'content' => '  Sent thank-you email.  ',
        ]));

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $this->assertSame('Sent thank-you email.', $this->decodeJson($response)['content']);

        $this->assertInstanceOf(JobNote::class, $persisted);
        $this->assertSame($job, $persisted->getJob());
        $this->assertSame('Sent thank-you email.', $persisted->getContent());
    }

    public function testCreateRequiresContent(): void
    {
        $search = $this->createJobSearch(4);
        $job = $this->createJob(9, $search);
        $jobRepository = $this->createMock(JobRepository::class);
        $jobRepository->method('find')->with(9)->willReturn($job);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');

        $controller = new JobNoteController($this->createMock(JobNoteRepository::class), $jobRepository, $entityManager);
        $this->bindController($controller);

        $response = $controller->create(9, $this->jsonRequest('POST', '/api/jobs/9/notes', ['content' => '  ']));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('A note is required.', $this->decodeJson($response)['error']);
    }

    public function testUpdateReplacesTheContent(): void
    {
        $search = $this->createJobSearch(4);
        $job = $this->createJob(9, $search);
        $note = $this->note(1, 'Old content', $job);
        $noteRepository = $this->createMock(JobNoteRepository::class);
        $noteRepository->method('find')->with(1)->willReturn($note);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $controller = new JobNoteController($noteRepository, $this->createMock(JobRepository::class), $entityManager);
        $this->bindController($controller);

        $response = $controller->update(1, $this->jsonRequest('PATCH', '/api/job-notes/1', ['content' => 'Updated']));

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('Updated', $note->getContent());
    }

    public function testUpdateReturns404ForAnotherUsersNote(): void
    {
        $otherUser = $this->createUser(2);
        $search = $this->createJobSearch(4, $otherUser);
        $job = $this->createJob(9, $search);
        $note = $this->note(1, 'Secret', $job);
        $noteRepository = $this->createMock(JobNoteRepository::class);
        $noteRepository->method('find')->with(1)->willReturn($note);

        $controller = new JobNoteController($noteRepository, $this->createMock(JobRepository::class), $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->update(1, $this->jsonRequest('PATCH', '/api/job-notes/1', ['content' => 'x']));

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertSame('Note not found.', $this->decodeJson($response)['error']);
    }

    public function testDeleteRemovesTheNote(): void
    {
        $search = $this->createJobSearch(4);
        $job = $this->createJob(9, $search);
        $note = $this->note(1, 'Obsolete', $job);
        $noteRepository = $this->createMock(JobNoteRepository::class);
        $noteRepository->method('find')->with(1)->willReturn($note);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('remove')->with($note);
        $entityManager->expects($this->once())->method('flush');

        $controller = new JobNoteController($noteRepository, $this->createMock(JobRepository::class), $entityManager);
        $this->bindController($controller);

        $response = $controller->delete(1);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertTrue($this->decodeJson($response)['success']);
    }

    private function note(int $id, string $content, ?Job $job = null): JobNote
    {
        $note = new JobNote();
        $this->setId($note, $id);
        $note->setContent($content);
        if (null !== $job) {
            $note->setJob($job);
        }

        return $note;
    }
}
