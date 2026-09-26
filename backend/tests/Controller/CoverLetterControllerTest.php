<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\CoverLetterController;
use App\Entity\CoverLetter;
use App\Entity\User;
use App\Repository\CoverLetterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

final class CoverLetterControllerTest extends ControllerTestCase
{
    public function testIndexReturnsAllLettersForTheUser(): void
    {
        $letter = $this->letter(2, 'Acme cover letter');
        $repository = $this->createMock(CoverLetterRepository::class);
        $repository->method('findByUser')->with(1)->willReturn([$letter]);

        $controller = new CoverLetterController($repository, $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->index();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = $this->decodeJson($response);
        $this->assertCount(1, $data);
        $this->assertSame('Acme cover letter', $data[0]['name']);
    }

    public function testCreatePersistsALetter(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist')->with($this->isInstanceOf(CoverLetter::class));
        $entityManager->expects($this->once())->method('flush');

        $controller = new CoverLetterController($this->createMock(CoverLetterRepository::class), $entityManager);
        $this->bindController($controller);

        $response = $controller->create($this->jsonRequest('POST', '/api/cover-letters', [
            'name' => '  Hiring manager  ',
            'body' => 'Dear Sir or Madam...',
        ]));

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $data = $this->decodeJson($response);
        $this->assertSame('Hiring manager', $data['name']);
        $this->assertSame('Dear Sir or Madam...', $data['body']);
    }

    public function testCreateRequiresName(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');

        $controller = new CoverLetterController($this->createMock(CoverLetterRepository::class), $entityManager);
        $this->bindController($controller);

        $response = $controller->create($this->jsonRequest('POST', '/api/cover-letters', ['name' => '']));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('A name is required.', $this->decodeJson($response)['error']);
    }

    public function testUpdateModifiesNameAndBody(): void
    {
        $letter = $this->letter(2, 'Old');
        $repository = $this->createMock(CoverLetterRepository::class);
        $repository->method('find')->with(2)->willReturn($letter);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $controller = new CoverLetterController($repository, $entityManager);
        $this->bindController($controller);

        $response = $controller->update(2, $this->jsonRequest('PATCH', '/api/cover-letters/2', [
            'name' => 'New',
            'body' => 'Updated body',
        ]));

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('New', $letter->getName());
        $this->assertSame('Updated body', $letter->getBody());
    }

    public function testUpdateReturns404ForAnotherUsersLetter(): void
    {
        $otherUser = $this->createUser(2);
        $letter = $this->letter(2, 'Secret', $otherUser);
        $repository = $this->createMock(CoverLetterRepository::class);
        $repository->method('find')->with(2)->willReturn($letter);

        $controller = new CoverLetterController($repository, $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->update(2, $this->jsonRequest('PATCH', '/api/cover-letters/2', ['name' => 'x']));

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertSame('Cover letter not found.', $this->decodeJson($response)['error']);
    }

    public function testDeleteRemovesTheLetter(): void
    {
        $letter = $this->letter(2, 'Obsolete');
        $repository = $this->createMock(CoverLetterRepository::class);
        $repository->method('find')->with(2)->willReturn($letter);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('remove')->with($letter);
        $entityManager->expects($this->once())->method('flush');

        $controller = new CoverLetterController($repository, $entityManager);
        $this->bindController($controller);

        $response = $controller->delete(2);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertTrue($this->decodeJson($response)['success']);
    }

    private function letter(int $id, string $name, ?User $user = null): CoverLetter
    {
        $letter = new CoverLetter();
        $this->setId($letter, $id);
        $letter->setUser($user ?? $this->user);
        $letter->setName($name);
        $letter->setBody('Default body');

        return $letter;
    }
}
