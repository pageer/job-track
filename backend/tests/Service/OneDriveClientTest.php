<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\OneDriveAuthException;
use App\Service\OneDriveClient;
use App\Service\OneDriveException;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class OneDriveClientTest extends TestCase
{
    public function testIsConfiguredReflectsClientId(): void
    {
        $client = $this->clientWithMock($this->createMock(HttpClientInterface::class), '');
        $this->assertFalse($client->isConfigured());

        $client = $this->clientWithMock($this->createMock(HttpClientInterface::class), 'my-client-id');
        $this->assertTrue($client->isConfigured());
    }

    public function testBuildAuthorizationUrlIncludesPkceAndState(): void
    {
        $client = $this->clientWithMock($this->createMock(HttpClientInterface::class), 'my-client-id');

        $url = $client->buildAuthorizationUrl(
            'http://127.0.0.1:8000/api/onedrive/callback',
            'state-abc',
            'challenge-123',
        );

        $this->assertStringContainsString('https://login.microsoftonline.com/consumers/oauth2/v2.0/authorize', $url);
        $this->assertStringContainsString('client_id=my-client-id', $url);
        $this->assertStringContainsString('redirect_uri=http%3A%2F%2F127.0.0.1%3A8000%2Fapi%2Fonedrive%2Fcallback', $url);
        $this->assertStringContainsString('response_type=code', $url);
        $this->assertStringContainsString('code_challenge=challenge-123', $url);
        $this->assertStringContainsString('code_challenge_method=S256', $url);
        $this->assertStringContainsString('state=state-abc', $url);
    }

    public function testCreateCodeChallengeIsUrlSafe(): void
    {
        $client = $this->clientWithMock($this->createMock(HttpClientInterface::class), 'my-client-id');

        $verifier = $client->createCodeVerifier();
        $challenge = $client->createCodeChallenge($verifier);

        $this->assertSame(43, strlen($challenge));
        $this->assertSame(0, preg_match('/[^A-Za-z0-9_-]/', $challenge));
        // Deterministic: same verifier always yields the same challenge.
        $this->assertSame($challenge, $client->createCodeChallenge($verifier));
    }

    public function testExchangeCodeReturnsTokens(): void
    {
        $response = $this->mockResponse(200, json_encode([
            'access_token' => 'access-1',
            'refresh_token' => 'refresh-1',
            'expires_in' => 3600,
        ], JSON_THROW_ON_ERROR));

        $client = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willReturn($response);

        $result = $this->clientWithMock($client, 'my-client-id')
            ->exchangeCode('http://127.0.0.1:8000/api/onedrive/callback', 'the-code', 'the-verifier');

        $this->assertSame('access-1', $result['accessToken']);
        $this->assertSame('refresh-1', $result['refreshToken']);
        $this->assertSame(3600, $result['expiresIn']);
    }

    public function testRefreshUsesRotatedRefreshToken(): void
    {
        $response = $this->mockResponse(200, json_encode([
            'access_token' => 'access-2',
            'refresh_token' => 'refresh-rotated',
            'expires_in' => 7200,
        ], JSON_THROW_ON_ERROR));

        $client = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willReturn($response);

        $result = $this->clientWithMock($client, 'my-client-id')->refreshAccess('refresh-old');

        $this->assertSame('access-2', $result['accessToken']);
        $this->assertSame('refresh-rotated', $result['refreshToken']);
        $this->assertSame(7200, $result['expiresIn']);
    }

    public function testRefreshKeepsOldRefreshTokenWhenNotRotated(): void
    {
        $response = $this->mockResponse(200, json_encode([
            'access_token' => 'access-3',
            'expires_in' => 3600,
        ], JSON_THROW_ON_ERROR));

        $client = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willReturn($response);

        $result = $this->clientWithMock($client, 'my-client-id')->refreshAccess('refresh-old');

        $this->assertSame('refresh-old', $result['refreshToken']);
    }

    public function testListFilesFiltersFoldersSortsAndMapsFields(): void
    {
        $response = $this->mockResponse(200, json_encode([
            'value' => [
                ['id' => 'B', 'name' => 'beta.pdf', 'size' => 200, 'webUrl' => 'https://1drv.ms/beta', 'file' => ['mimeType' => 'application/pdf']],
                ['id' => 'C', 'name' => 'Alpha.docx', 'size' => 100, 'webUrl' => 'https://1drv.ms/alpha', 'file' => ['mimeType' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']],
                ['id' => 'F', 'name' => 'A Folder', 'folder' => ['childCount' => 1]],
            ],
        ], JSON_THROW_ON_ERROR));

        $client = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willReturn($response);

        $files = $this->clientWithMock($client, 'my-client-id')->listFiles('access-token');

        $this->assertCount(2, $files);
        $this->assertSame('Alpha.docx', $files[0]['name']);
        $this->assertSame('beta.pdf', $files[1]['name']);
        $this->assertSame('https://1drv.ms/beta', $files[1]['webUrl']);
        $this->assertSame('application/pdf', $files[1]['mimeType']);
    }

    public function testListFilesUsesConfiguredFolderPath(): void
    {
        $capturedUrl = null;
        $client = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willReturnCallback(function (string $method, string $url, array $options = []) use (&$capturedUrl): ResponseInterface {
            $capturedUrl = $url;

            return $this->mockResponse(200, '{"value":[]}');
        });

        $onedrive = $this->clientWithMock($client, 'my-client-id');
        $onedrive->listFiles('access-token');

        $this->assertIsString($capturedUrl);
        $this->assertStringContainsString('/me/drive/root:/Resumes:/children', $capturedUrl);
        $this->assertStringContainsString('$select=id,name,size,file,lastModifiedDateTime,webUrl', $capturedUrl);
    }

    public function testListFilesUsesRootWhenFolderPathEmpty(): void
    {
        $capturedUrl = null;
        $client = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willReturnCallback(function (string $method, string $url, array $options = []) use (&$capturedUrl): ResponseInterface {
            $capturedUrl = $url;

            return $this->mockResponse(200, '{"value":[]}');
        });

        $clientId = 'my-client-id';
        $onedrive = new OneDriveClient($client, $clientId, 'consumers', '');
        $onedrive->listFiles('access-token');

        $this->assertIsString($capturedUrl);
        $this->assertStringContainsString('/me/drive/root/children', $capturedUrl);
    }

    public function testListFilesOn401ThrowsAuthException(): void
    {
        $response = $this->mockResponse(401, json_encode([
            'error' => ['code' => 'InvalidAuthenticationToken', 'message' => 'Token expired'],
        ], JSON_THROW_ON_ERROR));

        $client = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willReturn($response);

        $this->expectException(OneDriveAuthException::class);

        $this->clientWithMock($client, 'my-client-id')->listFiles('access-token');
    }

    public function testInvalidGrantOnRefreshThrowsAuthException(): void
    {
        $response = $this->mockResponse(400, json_encode([
            'error' => 'invalid_grant',
            'error_description' => 'The provided value for the input parameter refresh_token is not valid',
        ], JSON_THROW_ON_ERROR));

        $client = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willReturn($response);

        $this->expectException(OneDriveAuthException::class);

        $this->clientWithMock($client, 'my-client-id')->refreshAccess('refresh-old');
    }

    public function testListFilesSurfacesGraphErrorMessage(): void
    {
        $response = $this->mockResponse(500, json_encode([
            'error' => ['code' => 'UnknownError', 'message' => 'Something went wrong on Microsoft side'],
        ], JSON_THROW_ON_ERROR));

        $client = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willReturn($response);

        try {
            $this->clientWithMock($client, 'my-client-id')->listFiles('access-token');
            $this->fail('Expected OneDriveException to be thrown.');
        } catch (OneDriveException $e) {
            $this->assertStringContainsString('Something went wrong', $e->getMessage());
        }
    }

    private function clientWithMock(HttpClientInterface $client, string $clientId): OneDriveClient
    {
        return new OneDriveClient($client, $clientId, 'consumers', 'Resumes');
    }

    private function mockResponse(int $status, string $body): ResponseInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($status);
        $response->method('getContent')->willReturn($body);

        return $response;
    }
}