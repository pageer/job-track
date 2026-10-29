<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\OneDriveController;
use App\Entity\OneDriveToken;
use App\Entity\User;
use App\Entity\UserSettings;
use App\Repository\OneDriveTokenRepository;
use App\Repository\UserSettingsRepository;
use App\Service\OneDriveAuthException;
use App\Service\OneDriveClient;
use App\Service\OneDriveException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class OneDriveControllerTest extends ControllerTestCase
{
    public function testStatusReflectsTheConnectionState(): void
    {
        $client = $this->client(['isConfigured' => true, 'folderPath' => 'Resumes']);
        $tokenRepository = $this->tokenRepositoryReturning($this->token(1, 'me@example.com', 'Me'));

        $controller = $this->controller($client, $tokenRepository, $this->createMock(EntityManagerInterface::class), $this->logger());
        $this->bindController($controller);

        $response = $controller->status();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = $this->decodeJson($response);
        $this->assertTrue($data['clientConfigured']);
        $this->assertTrue($data['connected']);
        $this->assertSame('me@example.com', $data['accountEmail']);
        $this->assertSame('Me', $data['accountDisplayName']);
        $this->assertSame('Resumes', $data['folderPath']);
        $this->assertSame(['Resumes'], $data['folders']);
    }

    public function testStatusUsesTheUsersConfiguredFolders(): void
    {
        $settings = new UserSettings();
        $settings->setUser($this->user);
        $settings->setOneDriveFolders(['Job Search', 'Old Resumes']);

        $client = $this->client(['isConfigured' => true, 'folderPath' => 'Resumes']);
        $controller = $this->controller(
            $client,
            $this->tokenRepositoryReturning(null),
            null,
            null,
            $this->settingsRepositoryReturning($settings),
        );
        $this->bindController($controller);

        $data = $this->decodeJson($controller->status());

        $this->assertSame('Job Search', $data['folderPath']);
        $this->assertSame(['Job Search', 'Old Resumes'], $data['folders']);
    }

    public function testUpdateSettingsCreatesSettingsForTheUser(): void
    {
        $persisted = null;
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (mixed $entity) use (&$persisted): void {
            $persisted = $entity;
        });
        $entityManager->expects($this->once())->method('flush');

        $controller = $this->controller(
            $this->client(['isConfigured' => true]),
            null,
            $entityManager,
            null,
            $this->settingsRepositoryReturning(null),
        );
        $this->bindController($controller);

        $response = $controller->updateSettings($this->jsonRequest('PUT', '/api/onedrive/settings', [
            'folders' => [' Resumes ', '/Old Resumes/', 'resumes', ''],
        ]));

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame(['Resumes', 'Old Resumes'], $this->decodeJson($response)['folders']);
        $this->assertInstanceOf(UserSettings::class, $persisted);
        $this->assertSame($this->user, $persisted->getUser());
        $this->assertSame(['Resumes', 'Old Resumes'], $persisted->getOneDriveFolders());
    }

    public function testUpdateSettingsReplacesTheExistingFolderList(): void
    {
        $settings = new UserSettings();
        $settings->setUser($this->user);
        $settings->setOneDriveFolders(['Old']);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');
        $entityManager->expects($this->once())->method('flush');

        $controller = $this->controller(
            $this->client(['isConfigured' => true]),
            null,
            $entityManager,
            null,
            $this->settingsRepositoryReturning($settings),
        );
        $this->bindController($controller);

        $response = $controller->updateSettings($this->jsonRequest('PUT', '/api/onedrive/settings', [
            'folders' => ['New'],
        ]));

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame(['New'], $settings->getOneDriveFolders());
        $this->assertSame(['New'], $this->decodeJson($response)['folders']);
    }

    public function testUpdateSettingsRequiresAFolderArray(): void
    {
        $controller = $this->controller($this->client(['isConfigured' => true]));
        $this->bindController($controller);

        $response = $controller->updateSettings($this->jsonRequest('PUT', '/api/onedrive/settings', [
            'folders' => 'Resumes',
        ]));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('folders must be an array.', $this->decodeJson($response)['error']);
    }

    public function testStatusShowsDisconnectedWhenNoTokenExists(): void
    {
        $client = $this->client(['isConfigured' => true, 'folderPath' => 'Resumes']);
        $tokenRepository = $this->tokenRepositoryReturning(null);

        $controller = $this->controller($client, $tokenRepository, $this->createMock(EntityManagerInterface::class), $this->logger());
        $this->bindController($controller);

        $response = $controller->status();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = $this->decodeJson($response);
        $this->assertFalse($data['connected']);
        $this->assertNull($data['accountEmail']);
    }

    public function testAuthUrlStoresPkceAndStateInTheSession(): void
    {
        $client = $this->client(['isConfigured' => true]);
        $client->method('createCodeVerifier')->willReturn('verifier');
        $client->method('createCodeChallenge')->willReturn('challenge');
        $client->method('buildAuthorizationUrl')->willReturn('https://login.live.com/oauth');

        $controller = $this->controller($client, $this->createMock(OneDriveTokenRepository::class), $this->createMock(EntityManagerInterface::class), $this->logger());
        $this->bindController($controller);

        $session = $this->session();
        $request = Request::create('https://example.com/api/onedrive/auth-url', 'GET');
        $request->setSession($session);

        $response = $controller->authUrl($request);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('https://login.live.com/oauth', $this->decodeJson($response)['url']);
        $this->assertSame('verifier', $session->get('onedrive_pkce'));
        $this->assertNotEmpty($session->get('onedrive_state'));
    }

    public function testAuthUrlRejectsAnUnconfiguredClient(): void
    {
        $client = $this->client(['isConfigured' => false]);

        $controller = $this->controller($client, $this->createMock(OneDriveTokenRepository::class), $this->createMock(EntityManagerInterface::class), $this->logger());
        $this->bindController($controller);

        $request = Request::create('/api/onedrive/auth-url', 'GET');
        $request->setSession($this->session());

        $response = $controller->authUrl($request);

        $this->assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        $this->assertSame('OneDrive is not configured.', $this->decodeJson($response)['error']);
    }

    public function testAuthUrlStoresTheRequestedReturnTo(): void
    {
        $client = $this->client(['isConfigured' => true]);
        $client->method('createCodeVerifier')->willReturn('verifier');
        $client->method('createCodeChallenge')->willReturn('challenge');
        $client->method('buildAuthorizationUrl')->willReturn('https://login.live.com/oauth');

        $controller = $this->controller($client, $this->createMock(OneDriveTokenRepository::class), $this->createMock(EntityManagerInterface::class), $this->logger());
        $this->bindController($controller);

        $session = $this->session();
        $request = Request::create('https://example.com/api/onedrive/auth-url?returnTo=%2Fjobs%2F42', 'GET');
        $request->setSession($session);

        $controller->authUrl($request);

        $this->assertSame('/jobs/42', $session->get('onedrive_return_to'));
    }

    public function testAuthUrlRejectsAnExternalReturnTo(): void
    {
        $client = $this->client(['isConfigured' => true]);
        $client->method('createCodeVerifier')->willReturn('verifier');
        $client->method('createCodeChallenge')->willReturn('challenge');
        $client->method('buildAuthorizationUrl')->willReturn('https://login.live.com/oauth');

        $controller = $this->controller($client, $this->createMock(OneDriveTokenRepository::class), $this->createMock(EntityManagerInterface::class), $this->logger());
        $this->bindController($controller);

        $session = $this->session();
        $session->set('onedrive_return_to', '/stale');
        $request = Request::create('https://example.com/api/onedrive/auth-url?returnTo=' . rawurlencode('//evil.example/steal'), 'GET');
        $request->setSession($session);

        $controller->authUrl($request);

        $this->assertNull($session->get('onedrive_return_to'));
    }

    public function testCallbackRedirectsToErrorWhenNotConfigured(): void
    {
        $client = $this->client(['isConfigured' => false]);
        $logger = $this->logger();

        $controller = $this->controller($client, $this->createMock(OneDriveTokenRepository::class), $this->createMock(EntityManagerInterface::class), $logger);
        $this->bindController($controller);

        $response = $controller->callback($this->callbackRequest([]));

        $this->assertSame('/resumes?onedrive=error&reason=not_configured', $response->getTargetUrl());
    }

    public function testCallbackRedirectsOnStateMismatch(): void
    {
        $client = $this->client(['isConfigured' => true]);
        $controller = $this->controller($client, $this->createMock(OneDriveTokenRepository::class), $this->createMock(EntityManagerInterface::class), $this->logger());
        $this->bindController($controller);

        $request = $this->callbackRequest(['state' => 'wrong-state', 'code' => 'code']);
        $session = $request->getSession();
        $session->set('onedrive_state', 'expected-state');

        $response = $controller->callback($request);

        $this->assertSame('/resumes?onedrive=error&reason=state_mismatch', $response->getTargetUrl());
    }

    public function testCallbackRequiresAnAuthorizationCode(): void
    {
        $client = $this->client(['isConfigured' => true]);
        $controller = $this->controller($client, $this->createMock(OneDriveTokenRepository::class), $this->createMock(EntityManagerInterface::class), $this->logger());
        $this->bindController($controller);

        $request = $this->callbackRequest(['state' => 'expected-state']);
        $request->getSession()->set('onedrive_state', 'expected-state');

        $response = $controller->callback($request);

        $this->assertSame('/resumes?onedrive=error&reason=missing_code', $response->getTargetUrl());
    }

    public function testCallbackPersistsTheTokenAndRedirectsToConnected(): void
    {
        $client = $this->client(['isConfigured' => true]);
        $client->method('exchangeCode')->willReturn([
            'accessToken' => 'access-token',
            'refreshToken' => 'refresh-token',
            'expiresIn' => 3600,
        ]);
        $client->method('getAccountInfo')->willReturn([
            'email' => 'me@example.com',
            'displayName' => 'Me',
        ]);

        $persisted = null;
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (mixed $entity) use (&$persisted): void {
            $persisted = $entity;
        });
        $entityManager->expects($this->once())->method('flush');

        $controller = $this->controller($client, $this->createMock(OneDriveTokenRepository::class), $entityManager, $this->logger());
        $this->bindController($controller);

        $request = $this->callbackRequest(['state' => 'expected-state', 'code' => 'code123']);
        $session = $request->getSession();
        $session->set('onedrive_state', 'expected-state');
        $session->set('onedrive_pkce', 'verifier');

        $response = $controller->callback($request);

        $this->assertSame('/resumes?onedrive=connected', $response->getTargetUrl());
        $this->assertInstanceOf(OneDriveToken::class, $persisted);
        $this->assertSame($this->user, $persisted->getUser());
        $this->assertSame('access-token', $persisted->getAccessToken());
        $this->assertSame('me@example.com', $persisted->getAccountEmail());
        $this->assertNull($session->get('onedrive_state'));
    }

    public function testCallbackRedirectsBackToTheOriginatingPageOnSuccess(): void
    {
        $client = $this->client(['isConfigured' => true]);
        $client->method('exchangeCode')->willReturn([
            'accessToken' => 'access-token',
            'refreshToken' => 'refresh-token',
            'expiresIn' => 3600,
        ]);
        $client->method('getAccountInfo')->willReturn([
            'email' => 'me@example.com',
            'displayName' => 'Me',
        ]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $controller = $this->controller($client, $this->createMock(OneDriveTokenRepository::class), $entityManager, $this->logger());
        $this->bindController($controller);

        $request = $this->callbackRequest(['state' => 'expected-state', 'code' => 'code123']);
        $session = $request->getSession();
        $session->set('onedrive_state', 'expected-state');
        $session->set('onedrive_pkce', 'verifier');
        $session->set('onedrive_return_to', '/jobs/42');

        $response = $controller->callback($request);

        $this->assertSame('/jobs/42?onedrive=connected', $response->getTargetUrl());
        $this->assertNull($session->get('onedrive_return_to'));
    }

    public function testCallbackAppendsTheFlagToAReturnToThatAlreadyHasAQuery(): void
    {
        $client = $this->client(['isConfigured' => true]);
        $controller = $this->controller($client, $this->createMock(OneDriveTokenRepository::class), $this->createMock(EntityManagerInterface::class), $this->logger());
        $this->bindController($controller);

        $request = $this->callbackRequest(['state' => 'wrong-state', 'code' => 'code']);
        $session = $request->getSession();
        $session->set('onedrive_state', 'expected-state');
        $session->set('onedrive_return_to', '/jobs/42?tab=application');

        $response = $controller->callback($request);

        $this->assertSame('/jobs/42?tab=application&onedrive=error&reason=state_mismatch', $response->getTargetUrl());
    }

    public function testCallbackUpdatesAnExistingToken(): void
    {
        $client = $this->client(['isConfigured' => true]);
        $client->method('exchangeCode')->willReturn([
            'accessToken' => 'new-token',
            'refreshToken' => 'new-refresh',
            'expiresIn' => 3600,
        ]);
        $client->method('getAccountInfo')->willReturn(['email' => 'old@example.com', 'displayName' => 'Old']);

        $token = $this->token(1);
        $tokenRepository = $this->tokenRepositoryReturning($token);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');
        $entityManager->expects($this->once())->method('flush');

        $controller = $this->controller($client, $tokenRepository, $entityManager, $this->logger());
        $this->bindController($controller);

        $request = $this->callbackRequest(['state' => 'expected-state', 'code' => 'code123']);
        $session = $request->getSession();
        $session->set('onedrive_state', 'expected-state');
        $session->set('onedrive_pkce', 'verifier');

        $response = $controller->callback($request);

        $this->assertSame('/resumes?onedrive=connected', $response->getTargetUrl());
        $this->assertSame('new-token', $token->getAccessToken());
        $this->assertSame('old@example.com', $token->getAccountEmail());
    }

    public function testCallbackRedirectsOnUpstreamFailure(): void
    {
        $client = $this->client(['isConfigured' => true]);
        $client->method('exchangeCode')->willThrowException(new OneDriveException('Boom'));
        $logger = $this->logger();

        $controller = $this->controller($client, $this->createMock(OneDriveTokenRepository::class), $this->createMock(EntityManagerInterface::class), $logger);
        $this->bindController($controller);

        $request = $this->callbackRequest(['state' => 'expected-state', 'code' => 'code123']);
        $session = $request->getSession();
        $session->set('onedrive_state', 'expected-state');
        $session->set('onedrive_pkce', 'verifier');

        $response = $controller->callback($request);

        $this->assertSame('/resumes?onedrive=error&reason=exchange%3ABoom', $response->getTargetUrl());
    }

    public function testFilesReturnsTheFileList(): void
    {
        $token = $this->token(1);
        $token->setAccessToken('access-token');
        $token->setExpiresAt((new \DateTimeImmutable())->modify('+1 hour'));

        $client = $this->client(['isConfigured' => true, 'folderPath' => 'Resumes']);
        $client->method('listFiles')->willReturn([
            ['id' => 'a', 'name' => 'resume.pdf', 'webUrl' => 'https://onedrive.com/a'],
        ]);

        $controller = $this->controller($client, $this->tokenRepositoryReturning($token), $this->createMock(EntityManagerInterface::class), $this->logger());
        $this->bindController($controller);

        $response = $controller->files($this->filesRequest());

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = $this->decodeJson($response);
        $this->assertCount(1, $data['files']);
        $this->assertSame('resume.pdf', $data['files'][0]['name']);
        $this->assertSame('Resumes', $data['folderPath']);
    }

    public function testFilesUsesTheUsersConfiguredFolders(): void
    {
        $token = $this->token(1);
        $token->setAccessToken('access-token');
        $token->setExpiresAt((new \DateTimeImmutable())->modify('+1 hour'));

        $settings = new UserSettings();
        $settings->setUser($this->user);
        $settings->setOneDriveFolders(['Custom']);

        $client = $this->client(['isConfigured' => true, 'folderPath' => 'Resumes']);
        $client->expects($this->once())
            ->method('listFiles')
            ->with('access-token', 'Custom')
            ->willReturn([]);

        $controller = $this->controller(
            $client,
            $this->tokenRepositoryReturning($token),
            null,
            null,
            $this->settingsRepositoryReturning($settings),
        );
        $this->bindController($controller);

        $data = $this->decodeJson($controller->files($this->filesRequest()));

        $this->assertSame('Custom', $data['folderPath']);
        $this->assertSame(['Custom'], $data['folders']);
    }

    public function testFilesRefreshesAnExpiredTokenBeforeListing(): void
    {
        $token = $this->token(1);
        $token->setAccessToken('old-token');
        $token->setExpiresAt((new \DateTimeImmutable())->modify('-1 hour'));
        $token->setRefreshToken('refresh-token');

        $client = $this->client(['isConfigured' => true, 'folderPath' => 'Resumes']);
        $client->method('refreshAccess')->willReturn([
            'accessToken' => 'fresh-token',
            'refreshToken' => 'fresh-refresh',
            'expiresIn' => 3600,
        ]);
        $client->method('listFiles')->with('fresh-token')->willReturn([]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $controller = $this->controller($client, $this->tokenRepositoryReturning($token), $entityManager, $this->logger());
        $this->bindController($controller);

        $response = $controller->files($this->filesRequest());

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('fresh-token', $token->getAccessToken());
        $this->assertSame('fresh-refresh', $token->getRefreshToken());
    }

    public function testFilesReturnsConflictWithoutAConnection(): void
    {
        $client = $this->client(['isConfigured' => true]);
        $controller = $this->controller($client, $this->tokenRepositoryReturning(null), $this->createMock(EntityManagerInterface::class), $this->logger());
        $this->bindController($controller);

        $response = $controller->files($this->filesRequest());

        $this->assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        $this->assertSame('Connect your OneDrive account to browse files.', $this->decodeJson($response)['error']);
    }

    public function testFilesIsDisabledWhenNotConfigured(): void
    {
        $client = $this->client(['isConfigured' => false]);
        $controller = $this->controller($client, $this->createMock(OneDriveTokenRepository::class), $this->createMock(EntityManagerInterface::class), $this->logger());
        $this->bindController($controller);

        $response = $controller->files($this->filesRequest());

        $this->assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
    }

    public function testFilesExpiresTheConnectionWhenTokenRefreshFails(): void
    {
        $token = $this->token(1);
        $token->setAccessToken('old-token');
        $token->setExpiresAt((new \DateTimeImmutable())->modify('-1 hour'));
        $token->setRefreshToken('refresh-token');

        $client = $this->client(['isConfigured' => true, 'folderPath' => 'Resumes']);
        $client->method('refreshAccess')->willThrowException(new OneDriveAuthException('Expired.'));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('remove')->with($token);
        $entityManager->expects($this->once())->method('flush');

        $controller = $this->controller($client, $this->tokenRepositoryReturning($token), $entityManager, $this->logger());
        $this->bindController($controller);

        $response = $controller->files($this->filesRequest());

        $this->assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        $this->assertSame('Your OneDrive connection has expired. Please connect again.', $this->decodeJson($response)['error']);
    }

    public function testFilesReturnsBadGatewayOnUpstreamErrors(): void
    {
        $token = $this->token(1);
        $token->setAccessToken('access-token');
        $token->setExpiresAt((new \DateTimeImmutable())->modify('+1 hour'));

        $client = $this->client(['isConfigured' => true, 'folderPath' => 'Resumes']);
        $client->method('listFiles')->willThrowException(new OneDriveException('Upstream down.'));

        $controller = $this->controller($client, $this->tokenRepositoryReturning($token), $this->createMock(EntityManagerInterface::class), $this->logger());
        $this->bindController($controller);

        $response = $controller->files($this->filesRequest());

        $this->assertSame(Response::HTTP_BAD_GATEWAY, $response->getStatusCode());
        $this->assertSame('Upstream down.', $this->decodeJson($response)['error']);
    }

    public function testDisconnectRemovesTheToken(): void
    {
        $token = $this->token(1);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('remove')->with($token);
        $entityManager->expects($this->once())->method('flush');

        $controller = $this->controller($this->createMock(OneDriveClient::class), $this->tokenRepositoryReturning($token), $entityManager, $this->logger());
        $this->bindController($controller);

        $response = $controller->disconnect();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertTrue($this->decodeJson($response)['success']);
    }

    private function controller(
        OneDriveClient $client,
        ?OneDriveTokenRepository $tokenRepository = null,
        ?EntityManagerInterface $entityManager = null,
        ?LoggerInterface $logger = null,
        ?UserSettingsRepository $settingsRepository = null,
    ): OneDriveController {
        return new OneDriveController(
            $client,
            $tokenRepository ?? $this->createMock(OneDriveTokenRepository::class),
            $settingsRepository ?? $this->settingsRepositoryReturning(null),
            $entityManager ?? $this->createMock(EntityManagerInterface::class),
            $logger ?? $this->logger(),
        );
    }

    private function settingsRepositoryReturning(?UserSettings $settings): UserSettingsRepository
    {
        $repository = $this->createMock(UserSettingsRepository::class);
        $repository->method('findForUser')->willReturn($settings);

        return $repository;
    }

    /**
     * @param array<string, mixed> $behaviors
     * @return OneDriveClient&MockObject
     */
    private function client(array $behaviors): OneDriveClient
    {
        $client = $this->createMock(OneDriveClient::class);
        $client->method('isConfigured')->willReturn($behaviors['isConfigured'] ?? false);
        if (array_key_exists('folderPath', $behaviors)) {
            $client->method('getFolderPaths')->willReturn([$behaviors['folderPath']]);
        }
        $client->method('getRedirectUri')->willReturn('');

        return $client;
    }

    private function tokenRepositoryReturning(?OneDriveToken $token): OneDriveTokenRepository
    {
        $repository = $this->createMock(OneDriveTokenRepository::class);
        $repository->method('findForUser')->willReturn($token);

        return $repository;
    }

    private function token(int $id, ?string $email = null, ?string $displayName = null): OneDriveToken
    {
        $token = new OneDriveToken();
        $this->setId($token, $id);
        $token->setUser($this->user);
        $token->setAccessToken('access-token');
        $token->setRefreshToken('refresh-token');
        $token->setExpiresAt((new \DateTimeImmutable())->modify('+1 hour'));
        $token->setAccountEmail($email);
        $token->setAccountDisplayName($displayName);

        return $token;
    }

    /**
     * @param array<string, string> $query
     */
    private function filesRequest(array $query = []): Request
    {
        $params = '';
        if ([] !== $query) {
            $params = '?' . http_build_query($query);
        }

        return Request::create('https://example.com/api/onedrive/files' . $params, 'GET');
    }

    /**
     * @param array<string, string> $query
     */
    private function callbackRequest(array $query): Request
    {
        $params = '';
        if ([] !== $query) {
            $params = '?' . http_build_query($query);
        }

        $request = Request::create('https://example.com/api/onedrive/callback' . $params, 'GET');
        $request->setSession($this->session());

        return $request;
    }

    private function session(): Session
    {
        return new Session(new MockArraySessionStorage());
    }

    private function logger(): LoggerInterface
    {
        return $this->createMock(LoggerInterface::class);
    }
}
