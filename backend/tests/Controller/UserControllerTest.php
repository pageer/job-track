<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\UserController;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\UserSetupService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class UserControllerTest extends ControllerTestCase
{
    public function testIndexReturnsAllUsersForAnAdmin(): void
    {
        $this->loginAs(1, ['ROLE_ADMIN']);
        $users = [$this->createUser(1, ['ROLE_ADMIN']), $this->createUser(2)];
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('findAll')->willReturn($users);

        $controller = new UserController($userRepository, $this->createMock(UserSetupService::class), $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->index();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = $this->decodeJson($response);
        $this->assertCount(2, $data);
        $this->assertSame('user1@example.com', $data[0]['email']);
    }

    public function testIndexIsDeniedForNonAdmins(): void
    {
        $this->loginAs(1);
        $controller = new UserController($this->createMock(UserRepository::class), $this->createMock(UserSetupService::class), $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller, $this->createAuthorizationChecker(false));

        $this->expectException(AccessDeniedException::class);
        $controller->index();
    }

    public function testCreateCreatesAUserForAnAdmin(): void
    {
        $this->loginAs(1, ['ROLE_ADMIN']);
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn(null);

        $createdUser = $this->createUser(5);
        $createdUser->setEmail('jane@example.com')->setName('Jane');
        $setupService = $this->createMock(UserSetupService::class);
        $setupService->method('createUser')->willReturn($createdUser);

        $controller = new UserController($userRepository, $setupService, $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->create($this->jsonRequest('POST', '/api/users', [
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'password' => 'secret123',
        ]));

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $data = $this->decodeJson($response);
        $this->assertSame(5, $data['user']['id']);
        $this->assertSame('Jane', $data['user']['name']);
    }

    public function testCreateReturns422ForAnInvalidPayload(): void
    {
        $this->loginAs(1, ['ROLE_ADMIN']);
        $controller = new UserController($this->createMock(UserRepository::class), $this->createMock(UserSetupService::class), $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->create($this->jsonRequest('POST', '/api/users', [
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'password' => '',
        ]));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('Name, email and password are required.', $this->decodeJson($response)['error']);
    }

    public function testDeleteRemovesAnotherUser(): void
    {
        $this->loginAs(1, ['ROLE_ADMIN']);
        $target = $this->createUser(2);
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('find')->with(2)->willReturn($target);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('remove')->with($target);
        $entityManager->expects($this->once())->method('flush');

        $controller = new UserController($userRepository, $this->createMock(UserSetupService::class), $entityManager);
        $this->bindController($controller);

        $response = $controller->delete(2);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertTrue($this->decodeJson($response)['success']);
    }

    public function testDeleteCannotRemoveOwnAccount(): void
    {
        $this->loginAs(1, ['ROLE_ADMIN']);
        $currentUser = $this->user;
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('find')->with(1)->willReturn($currentUser);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('remove');

        $controller = new UserController($userRepository, $this->createMock(UserSetupService::class), $entityManager);
        $this->bindController($controller);

        $response = $controller->delete(1);

        $this->assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        $this->assertSame('You cannot delete your own account.', $this->decodeJson($response)['error']);
    }

    public function testDeleteReturns404ForAnUnknownUser(): void
    {
        $this->loginAs(1, ['ROLE_ADMIN']);
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('find')->willReturn(null);

        $controller = new UserController($userRepository, $this->createMock(UserSetupService::class), $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->delete(99);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertSame('User not found.', $this->decodeJson($response)['error']);
    }
}
