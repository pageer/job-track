<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\ApplicationController;
use App\Entity\Application;
use App\Entity\Job;
use App\Entity\JobSearch;
use App\Enum\JobStatus;
use App\Enum\ResumeKind;
use App\Repository\ApplicationRepository;
use App\Repository\JobRepository;
use App\Service\FileUploader;
use App\Service\HtmlSanitizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

final class ApplicationControllerTest extends ControllerTestCase
{
    public function testShowReturnsTheOwnedApplication(): void
    {
        $search = $this->createJobSearch(4);
        $job = $this->createJob(9, $search);
        $application = $this->createApplication(5, $job);
        $job->setApplication($application);

        $jobRepository = $this->createMock(JobRepository::class);
        $jobRepository->method('find')->with(9)->willReturn($job);

        $controller = $this->controller($jobRepository, null);
        $this->bindController($controller);

        $response = $controller->show(9);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = $this->decodeJson($response);
        $this->assertSame(5, $data['id']);
        $this->assertSame('Platform Engineer', $data['jobTitle']);
    }

    public function testShowReturns404WhenNoApplicationExists(): void
    {
        $search = $this->createJobSearch(4);
        $job = $this->createJob(9, $search);
        $jobRepository = $this->createMock(JobRepository::class);
        $jobRepository->method('find')->with(9)->willReturn($job);

        $controller = $this->controller($jobRepository, null);
        $this->bindController($controller);

        $response = $controller->show(9);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertSame('null', (string) $response->getContent());
    }

    public function testShowReturns404ForAnotherUsersJob(): void
    {
        $otherUser = $this->createUser(2);
        $search = $this->createJobSearch(4, $otherUser);
        $job = $this->createJob(9, $search);
        $jobRepository = $this->createMock(JobRepository::class);
        $jobRepository->method('find')->with(9)->willReturn($job);

        $controller = $this->controller($jobRepository, null);
        $this->bindController($controller);

        $response = $controller->show(9);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertSame('Job not found.', $this->decodeJson($response)['error']);
    }

    public function testCreatePersistsAApplicationAndPromotesJobStatus(): void
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

        $controller = $this->controller($jobRepository, null, $entityManager);
        $this->bindController($controller);

        $response = $controller->create(9, $this->jsonRequest('POST', '/api/jobs/9/application', [
            'resumeKind' => 'link',
            'resumeLinkUrl' => 'https://example.com/resume.pdf',
            'coverLetterHtml' => '<p>Cover</p>',
            'notes' => 'Applied via portal',
            'actionDate' => '2026-09-01',
        ]));

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $data = $this->decodeJson($response);
        $this->assertSame('link', $data['resumeKind']);
        $this->assertSame('https://example.com/resume.pdf', $data['resumeLinkUrl']);
        $this->assertSame('Applied via portal', $data['notes']);
        $this->assertSame('2026-09-01', substr($data['actionDate'], 0, 10));

        $this->assertInstanceOf(Application::class, $persisted);
        $this->assertSame($job, $persisted->getJob());
        $this->assertSame(JobStatus::Applied, $job->getStatus());
    }

    public function testCreateReturnsConflictIfApplicationAlreadyExists(): void
    {
        $search = $this->createJobSearch(4);
        $job = $this->createJob(9, $search);
        $application = $this->createApplication(5, $job);
        $job->setApplication($application);

        $jobRepository = $this->createMock(JobRepository::class);
        $jobRepository->method('find')->with(9)->willReturn($job);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');

        $controller = $this->controller($jobRepository, null, $entityManager);
        $this->bindController($controller);

        $response = $controller->create(9, $this->jsonRequest('POST', '/api/jobs/9/application', [
            'resumeKind' => 'link',
            'resumeLinkUrl' => 'https://example.com/resume.pdf',
        ]));

        $this->assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        $this->assertSame('This job already has an application.', $this->decodeJson($response)['error']);
    }

    public function testCreateRejectsAnInvalidResumeUrl(): void
    {
        $search = $this->createJobSearch(4);
        $job = $this->createJob(9, $search);
        $jobRepository = $this->createMock(JobRepository::class);
        $jobRepository->method('find')->with(9)->willReturn($job);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');

        $controller = $this->controller($jobRepository, null, $entityManager);
        $this->bindController($controller);

        $response = $controller->create(9, $this->jsonRequest('POST', '/api/jobs/9/application', [
            'resumeKind' => 'link',
            'resumeLinkUrl' => 'not a url',
        ]));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('A valid resumeLinkUrl is required for link resumes.', $this->decodeJson($response)['error']);
    }

    public function testUpdateModifiesTheApplication(): void
    {
        $search = $this->createJobSearch(4);
        $job = $this->createJob(9, $search);
        $application = $this->createApplication(5, $job);

        $applicationRepository = $this->createMock(ApplicationRepository::class);
        $applicationRepository->method('find')->with(5)->willReturn($application);

        $sanitizer = $this->createMock(HtmlSanitizer::class);
        $sanitizer->method('sanitize')->willReturnCallback(
            static fn (string $html): string => '<strong>' . $html . '</strong>'
        );

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $controller = $this->controller(null, $applicationRepository, $entityManager, $sanitizer);
        $this->bindController($controller);

        $response = $controller->update(5, $this->jsonRequest('PATCH', '/api/applications/5', [
            'resumeKind' => 'link',
            'resumeLinkUrl' => 'https://example.com/new.pdf',
            'coverLetterHtml' => 'Hello',
            'notes' => '',
            'actionDate' => '2026-10-01',
        ]));

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame(ResumeKind::Link, $application->getResumeKind());
        $this->assertSame('https://example.com/new.pdf', $application->getResumeLinkUrl());
        $this->assertSame('<strong>Hello</strong>', $application->getCoverLetterHtml());
        $this->assertNull($application->getNotes());
        $this->assertSame('2026-10-01', $application->getActionDate()->format('Y-m-d'));
    }

    public function testUpdateReturns404ForAnotherUsersApplication(): void
    {
        $otherUser = $this->createUser(2);
        $search = $this->createJobSearch(4, $otherUser);
        $job = $this->createJob(9, $search);
        $application = $this->createApplication(5, $job);
        $applicationRepository = $this->createMock(ApplicationRepository::class);
        $applicationRepository->method('find')->with(5)->willReturn($application);

        $controller = $this->controller(null, $applicationRepository, $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->update(5, $this->jsonRequest('PATCH', '/api/applications/5', ['notes' => 'x']));

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertSame('Application not found.', $this->decodeJson($response)['error']);
    }

    public function testDeleteRemovesAFileResume(): void
    {
        $search = $this->createJobSearch(4);
        $job = $this->createJob(9, $search);
        $application = $this->createApplication(5, $job);
        $application->setResumeKind(ResumeKind::File);
        $application->setResumeFilePath('var/uploads/applications/1/old.pdf');
        $applicationRepository = $this->createMock(ApplicationRepository::class);
        $applicationRepository->method('find')->with(5)->willReturn($application);

        $fileUploader = $this->createMock(FileUploader::class);
        $fileUploader->expects($this->once())->method('remove')->with('var/uploads/applications/1/old.pdf');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('remove')->with($application);

        $controller = $this->controller(null, $applicationRepository, $entityManager, null, $fileUploader);
        $this->bindController($controller);

        $response = $controller->delete(5);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertTrue($this->decodeJson($response)['success']);
    }

    public function testUploadFileAttachesTheUploadedFile(): void
    {
        $search = $this->createJobSearch(4);
        $job = $this->createJob(9, $search);
        $application = $this->createApplication(5, $job);
        $applicationRepository = $this->createMock(ApplicationRepository::class);
        $applicationRepository->method('find')->with(5)->willReturn($application);

        $fileUploader = $this->createMock(FileUploader::class);
        $fileUploader->method('upload')->willReturn('var/uploads/applications/1/abc.pdf');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $controller = $this->controller(null, $applicationRepository, $entityManager, null, $fileUploader);
        $this->bindController($controller);

        $request = $this->jsonRequest('POST', '/api/applications/5/resume-file', []);
        $file = $this->uploadedFile();
        $request->files->set('file', $file);

        $response = $controller->uploadFile(5, $request);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = $this->decodeJson($response);
        $this->assertSame('file', $data['resumeKind']);
        $this->assertSame('resume.pdf', $data['resumeFileName']);
        $this->assertSame($file->getMimeType(), $data['resumeMimeType']);
        $this->assertSame(ResumeKind::File, $application->getResumeKind());
        $this->assertSame('var/uploads/applications/1/abc.pdf', $application->getResumeFilePath());
        $this->assertNull($application->getResumeLinkUrl());
    }

    public function testUploadFileRejectsAMissingFile(): void
    {
        $search = $this->createJobSearch(4);
        $job = $this->createJob(9, $search);
        $application = $this->createApplication(5, $job);
        $applicationRepository = $this->createMock(ApplicationRepository::class);
        $applicationRepository->method('find')->with(5)->willReturn($application);

        $controller = $this->controller(null, $applicationRepository, $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->uploadFile(5, $this->jsonRequest('POST', '/api/applications/5/resume-file', []));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('No file uploaded.', $this->decodeJson($response)['error']);
    }

    public function testDownloadResumeServesTheFile(): void
    {
        $search = $this->createJobSearch(4);
        $job = $this->createJob(9, $search);
        $application = $this->createApplication(5, $job);
        $application->setResumeKind(ResumeKind::File);
        $application->setResumeFilePath('var/uploads/applications/1/abc.pdf');
        $application->setResumeFileName('resume.pdf');
        $application->setResumeMimeType('application/pdf');
        $applicationRepository = $this->createMock(ApplicationRepository::class);
        $applicationRepository->method('find')->with(5)->willReturn($application);

        $path = $this->tempFile();
        $fileUploader = $this->createMock(FileUploader::class);
        $fileUploader->method('resolve')->willReturn($path);

        $controller = $this->controller(null, $applicationRepository, $this->createMock(EntityManagerInterface::class), null, $fileUploader);
        $this->bindController($controller);

        $response = $controller->downloadResume(5);

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function testDownloadResumeReturns404ForAnotherUsersApplication(): void
    {
        $otherUser = $this->createUser(2);
        $search = $this->createJobSearch(4, $otherUser);
        $job = $this->createJob(9, $search);
        $application = $this->createApplication(5, $job);
        $applicationRepository = $this->createMock(ApplicationRepository::class);
        $applicationRepository->method('find')->with(5)->willReturn($application);

        $controller = $this->controller(null, $applicationRepository, $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->downloadResume(5);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    private function controller(
        ?JobRepository $jobRepository,
        ?ApplicationRepository $applicationRepository,
        ?EntityManagerInterface $entityManager = null,
        ?HtmlSanitizer $htmlSanitizer = null,
        ?FileUploader $fileUploader = null,
    ): ApplicationController {
        return new ApplicationController(
            $applicationRepository ?? $this->createMock(ApplicationRepository::class),
            $jobRepository ?? $this->createMock(JobRepository::class),
            $fileUploader ?? $this->createMock(FileUploader::class),
            $htmlSanitizer ?? $this->createMock(HtmlSanitizer::class),
            $entityManager ?? $this->createMock(EntityManagerInterface::class),
        );
    }

    private function uploadedFile(): UploadedFile
    {
        return new UploadedFile($this->tempFile(), 'resume.pdf', 'application/pdf', null, true);
    }

    private function tempFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'resume');
        file_put_contents($path, 'PDF CONTENT');

        return $path;
    }
}
