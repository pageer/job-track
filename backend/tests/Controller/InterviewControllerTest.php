<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\InterviewController;
use App\Entity\Application;
use App\Entity\Interview;
use App\Entity\User;
use App\Repository\ApplicationRepository;
use App\Repository\InterviewRepository;
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

        $controller = new InterviewController($this->createMock(InterviewRepository::class), $applicationRepository, $this->createMock(EntityManagerInterface::class));
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

        $controller = new InterviewController($this->createMock(InterviewRepository::class), $applicationRepository, $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->index(5);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertSame('Application not found.', $this->decodeJson($response)['error']);
    }

    public function testCreatePersistsAnInterview(): void
    {
        $search = $this->createJobSearch(4);
        $job = $this->createJob(9, $search);
        $application = $this->createApplication(5, $job);
        $applicationRepository = $this->createMock(ApplicationRepository::class);
        $applicationRepository->method('find')->with(5)->willReturn($application);

        $persisted = null;
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (mixed $entity) use (&$persisted): void {
            $persisted = $entity;
        });
        $entityManager->expects($this->once())->method('flush');

        $controller = new InterviewController($this->createMock(InterviewRepository::class), $applicationRepository, $entityManager);
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

        $this->assertInstanceOf(Interview::class, $persisted);
        $this->assertSame($application, $persisted->getApplication());
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

        $controller = new InterviewController($this->createMock(InterviewRepository::class), $applicationRepository, $entityManager);
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

        $controller = new InterviewController($interviewRepository, $this->createMock(ApplicationRepository::class), $entityManager);
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

    public function testUpdateReturns404ForAnotherUsersInterview(): void
    {
        $otherUser = $this->createUser(2);
        $search = $this->createJobSearch(4, $otherUser);
        $job = $this->createJob(9, $search);
        $application = $this->createApplication(5, $job);
        $interview = $this->interview(1, '2026-09-20T14:00:00', ['Alice'], $application);
        $interviewRepository = $this->createMock(InterviewRepository::class);
        $interviewRepository->method('find')->with(1)->willReturn($interview);

        $controller = new InterviewController($interviewRepository, $this->createMock(ApplicationRepository::class), $this->createMock(EntityManagerInterface::class));
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

        $controller = new InterviewController($interviewRepository, $this->createMock(ApplicationRepository::class), $entityManager);
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
}
