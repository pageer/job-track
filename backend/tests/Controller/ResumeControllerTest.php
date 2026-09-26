<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\ResumeController;
use App\Entity\Resume;
use App\Entity\User;
use App\Enum\ResumeKind;
use App\Repository\ResumeRepository;
use App\Service\FileUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResumeControllerTest extends ControllerTestCase
{
    public function testIndexReturnsAllResumesForTheUser(): void
    {
        $resume = $this->resume(3, 'Main resume', ResumeKind::File);
        $repository = $this->createMock(ResumeRepository::class);
        $repository->method('findByUser')->with(1)->willReturn([$resume]);

        $controller = new ResumeController($repository, $this->createMock(FileUploader::class), $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->index();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = $this->decodeJson($response);
        $this->assertCount(1, $data);
        $this->assertSame('Main resume', $data[0]['name']);
    }

    public function testCreatePersistsALinkResume(): void
    {
        $persisted = null;
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (mixed $entity) use (&$persisted): void {
            $persisted = $entity;
        });
        $entityManager->expects($this->once())->method('flush');

        $controller = new ResumeController($this->createMock(ResumeRepository::class), $this->createMock(FileUploader::class), $entityManager);
        $this->bindController($controller);

        $response = $controller->create($this->jsonRequest('POST', '/api/resumes', [
            'name' => '  Portfolio  ',
            'kind' => 'link',
            'linkUrl' => 'https://example.com/resume.pdf',
        ]));

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $data = $this->decodeJson($response);
        $this->assertSame('Portfolio', $data['name']);
        $this->assertSame('link', $data['kind']);
        $this->assertSame('https://example.com/resume.pdf', $data['linkUrl']);

        $this->assertInstanceOf(Resume::class, $persisted);
        $this->assertSame($this->user, $persisted->getUser());
        $this->assertSame(ResumeKind::Link, $persisted->getKind());
    }

    public function testCreateRequiresLinkKind(): void
    {
        $controller = new ResumeController($this->createMock(ResumeRepository::class), $this->createMock(FileUploader::class), $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->create($this->jsonRequest('POST', '/api/resumes', [
            'name' => 'Portfolio',
            'linkUrl' => 'https://example.com/resume.pdf',
        ]));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('Either upload a file or provide kind "link" with a linkUrl.', $this->decodeJson($response)['error']);
    }

    public function testCreateRejectsAnInvalidLinkUrl(): void
    {
        $controller = new ResumeController($this->createMock(ResumeRepository::class), $this->createMock(FileUploader::class), $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->create($this->jsonRequest('POST', '/api/resumes', [
            'name' => 'Portfolio',
            'kind' => 'link',
            'linkUrl' => 'not a url',
        ]));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('Please provide a valid URL.', $this->decodeJson($response)['error']);
    }

    public function testCreatePersistsAFileResume(): void
    {
        $fileUploader = $this->createMock(FileUploader::class);
        $fileUploader->method('upload')->willReturn('var/uploads/resumes/1/abc.pdf');

        $persisted = null;
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (mixed $entity) use (&$persisted): void {
            $persisted = $entity;
        });

        $controller = new ResumeController($this->createMock(ResumeRepository::class), $fileUploader, $entityManager);
        $this->bindController($controller);

        $request = $this->fileRequest('resume.pdf');
        $response = $controller->create($request);

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $data = $this->decodeJson($response);
        $this->assertSame('Uploaded resume', $data['name']);
        $this->assertSame('file', $data['kind']);
        $this->assertSame('resume.pdf', $data['fileName']);

        $this->assertInstanceOf(Resume::class, $persisted);
        $this->assertSame(ResumeKind::File, $persisted->getKind());
        $this->assertSame('var/uploads/resumes/1/abc.pdf', $persisted->getFilePath());
    }

    public function testUpdateModifiesNameAndLinkUrl(): void
    {
        $resume = $this->resume(3, 'Old', ResumeKind::Link);
        $resume->setLinkUrl('https://example.com/old.pdf');
        $repository = $this->createMock(ResumeRepository::class);
        $repository->method('find')->with(3)->willReturn($resume);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $controller = new ResumeController($repository, $this->createMock(FileUploader::class), $entityManager);
        $this->bindController($controller);

        $response = $controller->update(3, $this->jsonRequest('PATCH', '/api/resumes/3', [
            'name' => 'New name',
            'linkUrl' => 'https://example.com/new.pdf',
        ]));

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('New name', $resume->getName());
        $this->assertSame('https://example.com/new.pdf', $resume->getLinkUrl());
    }

    public function testUpdateReturns404ForAnotherUsersResume(): void
    {
        $otherUser = $this->createUser(2);
        $resume = $this->resume(3, 'Secret', ResumeKind::File, $otherUser);
        $repository = $this->createMock(ResumeRepository::class);
        $repository->method('find')->with(3)->willReturn($resume);

        $controller = new ResumeController($repository, $this->createMock(FileUploader::class), $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->update(3, $this->jsonRequest('PATCH', '/api/resumes/3', ['name' => 'x']));

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertSame('Resume not found.', $this->decodeJson($response)['error']);
    }

    public function testDeleteRemovesTheFileFromDisk(): void
    {
        $resume = $this->resume(3, 'Obsolete', ResumeKind::File);
        $resume->setFilePath('var/uploads/resumes/1/abc.pdf');
        $repository = $this->createMock(ResumeRepository::class);
        $repository->method('find')->with(3)->willReturn($resume);

        $fileUploader = $this->createMock(FileUploader::class);
        $fileUploader->expects($this->once())->method('remove')->with('var/uploads/resumes/1/abc.pdf');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('remove')->with($resume);

        $controller = new ResumeController($repository, $fileUploader, $entityManager);
        $this->bindController($controller);

        $response = $controller->delete(3);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertTrue($this->decodeJson($response)['success']);
    }

    public function testDownloadServesTheFile(): void
    {
        $resume = $this->resume(3, 'Main', ResumeKind::File);
        $resume->setFilePath('var/uploads/resumes/1/abc.pdf');
        $resume->setFileName('resume.pdf');
        $resume->setMimeType('application/pdf');
        $repository = $this->createMock(ResumeRepository::class);
        $repository->method('find')->with(3)->willReturn($resume);

        $path = $this->tempFile();
        $fileUploader = $this->createMock(FileUploader::class);
        $fileUploader->method('resolve')->willReturn($path);

        $controller = new ResumeController($repository, $fileUploader, $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->download(3);

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function testDownloadReturnsConflictForALinkResume(): void
    {
        $resume = $this->resume(3, 'Main', ResumeKind::Link);
        $resume->setLinkUrl('https://example.com/resume.pdf');
        $repository = $this->createMock(ResumeRepository::class);
        $repository->method('find')->with(3)->willReturn($resume);

        $controller = new ResumeController($repository, $this->createMock(FileUploader::class), $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->download(3);

        $this->assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        $this->assertSame('This resume has no attached file.', $this->decodeJson($response)['error']);
    }

    public function testDownloadReturns500WhenTheFileIsMissing(): void
    {
        $resume = $this->resume(3, 'Main', ResumeKind::File);
        $resume->setFilePath('var/uploads/resumes/1/gone.pdf');
        $repository = $this->createMock(ResumeRepository::class);
        $repository->method('find')->with(3)->willReturn($resume);

        $fileUploader = $this->createMock(FileUploader::class);
        $fileUploader->method('resolve')->willReturn($this->tempFile() . '.missing');

        $controller = new ResumeController($repository, $fileUploader, $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->download(3);

        $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $this->assertSame('The stored file could not be found.', $this->decodeJson($response)['error']);
    }

    private function resume(int $id, string $name, ResumeKind $kind, ?User $user = null): Resume
    {
        $resume = new Resume();
        $this->setId($resume, $id);
        $resume->setUser($user ?? $this->user);
        $resume->setName($name);
        $resume->setKind($kind);

        return $resume;
    }

    private function fileRequest(string $fileName): Request
    {
        $file = new UploadedFile($this->tempFile(), $fileName, 'application/pdf', null, true);
        $request = Request::create('/api/resumes', 'POST', ['name' => 'Uploaded resume']);
        $request->files->set('file', $file);

        return $request;
    }

    private function tempFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'resume');
        file_put_contents($path, 'PDF CONTENT');

        return $path;
    }
}
