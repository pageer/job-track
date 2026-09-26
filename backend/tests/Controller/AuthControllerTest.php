<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\AuthController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class AuthControllerTest extends ControllerTestCase
{
    private const CSRF_TOKEN = 'csrf-token-value';

    public function testLoginReturnsTheUserAndCsrfToken(): void
    {
        $this->loginAs(7, ['ROLE_ADMIN']);
        $controller = new AuthController($this->csrfTokenManager());
        $this->bindController($controller);

        $response = $controller->login();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = $this->decodeJson($response);
        $this->assertSame(self::CSRF_TOKEN, $data['csrfToken']);
        $this->assertSame(7, $data['user']['id']);
        $this->assertSame('user7@example.com', $data['user']['email']);
        $this->assertSame(['ROLE_ADMIN', 'ROLE_USER'], $data['user']['roles']);
    }

    public function testMeReturnsTheCurrentUserAndCsrfToken(): void
    {
        $controller = new AuthController($this->csrfTokenManager());
        $this->bindController($controller);

        $response = $controller->me();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = $this->decodeJson($response);
        $this->assertSame(self::CSRF_TOKEN, $data['csrfToken']);
        $this->assertSame(1, $data['user']['id']);
        $this->assertSame('User 1', $data['user']['name']);
        $this->assertSame(['ROLE_USER'], $data['user']['roles']);
    }

    public function testLogoutClearsTheToken(): void
    {
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->expects($this->once())->method('setToken')->with(null);

        $controller = new AuthController($this->csrfTokenManager());
        $this->bindController($controller);

        $response = $controller->logout($this->jsonRequest('POST', '/api/auth/logout'), $tokenStorage);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertTrue($this->decodeJson($response)['success']);
    }

    public function testLogoutInvalidatesTheSessionWhenPresent(): void
    {
        $tokenStorage = $this->createMock(TokenStorageInterface::class);

        $session = new Session(new MockArraySessionStorage());
        $session->set('any_data', 'value');
        $request = $this->jsonRequest('POST', '/api/auth/logout');
        $request->setSession($session);

        $sessionIdBefore = $session->getId();

        $controller = new AuthController($this->csrfTokenManager());
        $this->bindController($controller);

        $response = $controller->logout($request, $tokenStorage);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertNotSame($sessionIdBefore, $session->getId());
    }

    private function csrfTokenManager(): CsrfTokenManagerInterface
    {
        $manager = $this->createMock(CsrfTokenManagerInterface::class);
        $manager->method('getToken')->willReturn(new CsrfToken('app', self::CSRF_TOKEN));

        return $manager;
    }
}
