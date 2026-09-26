<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\SetupController;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\UserSetupService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class SetupControllerTest extends ControllerTestCase
{
    public function testStatusReportsSetupNeeded(): void
    {
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('countAll')->willReturn(0);

        $controller = new SetupController($userRepository, $this->createMock(UserSetupService::class), $this->csrfTokenManager());
        $this->bindController($controller);

        $response = $controller->status();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = $this->decodeJson($response);
        $this->assertTrue($data['needsSetup']);
        $this->assertSame('setup-token', $data['csrfToken']);
    }

    public function testStatusReportsSetupCompleted(): void
    {
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('countAll')->willReturn(1);

        $controller = new SetupController($userRepository, $this->createMock(UserSetupService::class), $this->csrfTokenManager());
        $this->bindController($controller);

        $response = $controller->status();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertFalse($this->decodeJson($response)['needsSetup']);
    }

    public function testCreateCreatesTheAdminUser(): void
    {
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('countAll')->willReturn(0);
        $userRepository->method('findOneBy')->willReturn(null);

        $createdUser = $this->createUser(1, ['ROLE_ADMIN']);
        $createdUser->setEmail('admin@example.com')->setName('Admin');
        $setupService = $this->createMock(UserSetupService::class);
        $setupService->method('createUser')->willReturnCallback(
            static fn (string $name, string $email, string $password, array $roles): User => $createdUser
        );

        $controller = new SetupController($userRepository, $setupService, $this->csrfTokenManager());
        $this->bindController($controller);

        $response = $controller->create($this->jsonRequest('POST', '/api/setup', [
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ]));

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $data = $this->decodeJson($response);
        $this->assertSame(1, $data['user']['id']);
        $this->assertSame('admin@example.com', $data['user']['email']);
    }

    public function testCreateReturnsConflictWhenAlreadySetUp(): void
    {
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('countAll')->willReturn(1);

        $setupService = $this->createMock(UserSetupService::class);
        $setupService->expects($this->never())->method('createUser');

        $controller = new SetupController($userRepository, $setupService, $this->csrfTokenManager());
        $this->bindController($controller);

        $response = $controller->create($this->jsonRequest('POST', '/api/setup', [
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ]));

        $this->assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        $this->assertSame('Setup has already been completed.', $this->decodeJson($response)['error']);
    }

    public function testCreateRequiresAllFields(): void
    {
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('countAll')->willReturn(0);

        $setupService = $this->createMock(UserSetupService::class);
        $setupService->expects($this->never())->method('createUser');

        $controller = new SetupController($userRepository, $setupService, $this->csrfTokenManager());
        $this->bindController($controller);

        $response = $controller->create($this->jsonRequest('POST', '/api/setup', [
            'name' => 'Admin',
            'email' => '',
            'password' => 'secret123',
        ]));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('Name, email and password are required.', $this->decodeJson($response)['error']);
    }

    public function testCreateRequiresAPasswordOfAtLeastEightCharacters(): void
    {
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('countAll')->willReturn(0);

        $setupService = $this->createMock(UserSetupService::class);
        $setupService->expects($this->never())->method('createUser');

        $controller = new SetupController($userRepository, $setupService, $this->csrfTokenManager());
        $this->bindController($controller);

        $response = $controller->create($this->jsonRequest('POST', '/api/setup', [
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'short',
        ]));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('Password must be at least 8 characters long.', $this->decodeJson($response)['error']);
    }

    public function testCreateRejectsAnInvalidEmail(): void
    {
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('countAll')->willReturn(0);

        $controller = new SetupController($userRepository, $this->createMock(UserSetupService::class), $this->csrfTokenManager());
        $this->bindController($controller);

        $response = $controller->create($this->jsonRequest('POST', '/api/setup', [
            'name' => 'Admin',
            'email' => 'not an email',
            'password' => 'secret123',
        ]));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('Please provide a valid email address.', $this->decodeJson($response)['error']);
    }

    public function testCreateRejectsAnExistingEmail(): void
    {
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('countAll')->willReturn(0);
        $userRepository->method('findOneBy')->willReturn($this->createUser(2));

        $setupService = $this->createMock(UserSetupService::class);
        $setupService->expects($this->never())->method('createUser');

        $controller = new SetupController($userRepository, $setupService, $this->csrfTokenManager());
        $this->bindController($controller);

        $response = $controller->create($this->jsonRequest('POST', '/api/setup', [
            'name' => 'Admin',
            'email' => 'existing@example.com',
            'password' => 'secret123',
        ]));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('A user with this email already exists.', $this->decodeJson($response)['error']);
    }

    private function csrfTokenManager(): CsrfTokenManagerInterface
    {
        $manager = $this->createMock(CsrfTokenManagerInterface::class);
        $manager->method('getToken')->willReturn(new CsrfToken('app', 'setup-token'));

        return $manager;
    }
}
