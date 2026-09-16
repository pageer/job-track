<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Talks to the Microsoft identity platform (Authorization Code + PKCE) and the
 * Graph API to list files in a configured personal OneDrive folder.
 */
class OneDriveClient
{
    private const AUTH_BASE = 'https://login.microsoftonline.com/%s/oauth2/v2.0';

    private const GRAPH_BASE = 'https://graph.microsoft.com/v1.0';

    private const SCOPES = 'offline_access Files.Read User.Read';

    private const DEFAULT_TIMEOUT = 20.0;

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $clientId,
        private string $tenant = 'consumers',
        private string $folderPath = 'Resumes',
        private string $redirectUri = '',
    ) {
    }

    public function isConfigured(): bool
    {
        return '' !== $this->clientId;
    }

    public function getFolderPath(): string
    {
        return $this->folderPath;
    }

    public function getRedirectUri(): string
    {
        return $this->redirectUri;
    }

    /**
     * @return string URL-safe random code verifier (128 chars)
     */
    public function createCodeVerifier(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(96)), '+/', '-_'), '=');
    }

    public function createCodeChallenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    public function buildAuthorizationUrl(string $callbackUrl, string $state, string $codeChallenge): string
    {
        $params = http_build_query([
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'redirect_uri' => $callbackUrl,
            'scope' => self::SCOPES,
            'response_mode' => 'query',
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);

        return sprintf(self::AUTH_BASE, $this->tenant) . '/authorize?' . $params;
    }

    /**
     * Exchanges the authorization code for tokens.
     *
     * @return array{accessToken: string, refreshToken: string|null, expiresIn: int}
     */
    public function exchangeCode(string $callbackUrl, string $code, string $codeVerifier): array
    {
        $data = $this->post(
            '/token',
            [
                'client_id' => $this->clientId,
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $callbackUrl,
                'code_verifier' => $codeVerifier,
                'scope' => self::SCOPES,
            ],
        );

        return [
            'accessToken' => (string) ($data['access_token'] ?? ''),
            'refreshToken' => isset($data['refresh_token']) ? (string) $data['refresh_token'] : null,
            'expiresIn' => (int) ($data['expires_in'] ?? 3600),
        ];
    }

    /**
     * Uses a refresh token to obtain a fresh access token. A rotated refresh
     * token is returned when Microsoft issues one.
     *
     * @return array{accessToken: string, refreshToken: string, expiresIn: int}
     */
    public function refreshAccess(string $refreshToken): array
    {
        $data = $this->post(
            '/token',
            [
                'client_id' => $this->clientId,
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
                'scope' => self::SCOPES,
            ],
        );

        return [
            'accessToken' => (string) ($data['access_token'] ?? ''),
            'refreshToken' => isset($data['refresh_token']) ? (string) $data['refresh_token'] : $refreshToken,
            'expiresIn' => (int) ($data['expires_in'] ?? 3600),
        ];
    }

    /**
     * Lists the files (no folders) in the configured OneDrive folder.
     *
     * @return list<array{id: string, name: string, size: int|null, lastModifiedDateTime: string|null, webUrl: string, mimeType: string|null}>
     */
    public function listFiles(string $accessToken): array
    {
        $encoded = implode('/', array_map('rawurlencode', explode('/', trim($this->folderPath, '/'))));

        $url = self::GRAPH_BASE . '/me/drive/root' . ('' === $encoded ? '/children' : ':/' . $encoded . ':/children');
        $url .= '?$select=id,name,size,file,lastModifiedDateTime,webUrl&$top=500';

        $data = $this->get($url, $accessToken);

        $files = [];
        foreach (($data['value'] ?? []) as $child) {
            if (!is_array($child) || isset($child['folder'])) {
                continue;
            }
            $files[] = [
                'id' => (string) ($child['id'] ?? ''),
                'name' => (string) ($child['name'] ?? ''),
                'size' => isset($child['size']) ? (int) $child['size'] : null,
                'lastModifiedDateTime' => isset($child['lastModifiedDateTime']) ? (string) $child['lastModifiedDateTime'] : null,
                'webUrl' => (string) ($child['webUrl'] ?? ''),
                'mimeType' => isset($child['file']['mimeType']) ? (string) $child['file']['mimeType'] : null,
            ];
        }

        usort($files, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $files;
    }

    /**
     * @return array{displayName: string|null, email: string|null}
     */
    public function getAccountInfo(string $accessToken): array
    {
        $data = $this->get(self::GRAPH_BASE . '/me?$select=displayName,mail,userPrincipalName', $accessToken);

        return [
            'displayName' => isset($data['displayName']) ? (string) $data['displayName'] : null,
            'email' => (string) ($data['mail'] ?? $data['userPrincipalName'] ?? null),
        ];
    }

    /**
     * @param array<string, string> $body
     *
     * @return array<string, mixed>
     */
    private function post(string $endpoint, array $body): array
    {
        $response = $this->httpClient->request('POST', sprintf(self::AUTH_BASE, $this->tenant) . $endpoint, [
            'body' => $body,
            'timeout' => self::DEFAULT_TIMEOUT,
        ]);

        return $this->handleTokenResponse($response);
    }

    /**
     * @return array<string, mixed>
     */
    private function get(string $url, string $accessToken): array
    {
        $response = $this->httpClient->request('GET', $url, [
            'auth_bearer' => $accessToken,
            'accept' => 'application/json',
            'timeout' => self::DEFAULT_TIMEOUT,
        ]);

        $status = $response->getStatusCode();
        $data = $this->decode($response, $status);

        if ($status >= 400) {
            if (401 === $status) {
                throw new OneDriveAuthException($this->graphErrorMessage($data, $status));
            }
            throw new OneDriveException($this->graphErrorMessage($data, $status));
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function handleTokenResponse(ResponseInterface $response): array
    {
        $status = $response->getStatusCode();
        $data = $this->decode($response, $status);

        if ($status >= 400) {
            $code = is_string($data['error'] ?? null) ? $data['error'] : '';
            if ('invalid_grant' === $code || 'invalid_client' === $code) {
                throw new OneDriveAuthException((string) ($data['error_description'] ?? 'The OneDrive connection is no longer valid.'));
            }
            $message = is_string($data['error_description'] ?? null) ? $data['error_description'] : 'OneDrive request failed.';
            throw new OneDriveException($message);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(ResponseInterface $response, int $status): array
    {
        try {
            $content = $response->getContent(false);
        } catch (TransportExceptionInterface $e) {
            throw new OneDriveException('Could not reach Microsoft: ' . $e->getMessage(), false, $e);
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            throw new OneDriveException(sprintf('Microsoft returned an unexpected response (HTTP %d).', $status));
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function graphErrorMessage(array $data, int $status): string
    {
        $message = $data['error']['message'] ?? null;

        return is_string($message) && '' !== $message
            ? $message
            : sprintf('OneDrive request failed (HTTP %d).', $status);
    }
}
