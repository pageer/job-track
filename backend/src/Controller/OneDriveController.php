<?php

namespace App\Controller;

use App\Entity\OneDriveToken;
use App\Entity\User;
use App\Repository\OneDriveTokenRepository;
use App\Service\OneDriveAuthException;
use App\Service\OneDriveClient;
use App\Service\OneDriveException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/onedrive', name: 'api_onedrive_')]
class OneDriveController extends AbstractController
{
    public function __construct(
        private OneDriveClient $oneDriveClient,
        private OneDriveTokenRepository $tokenRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    #[Route('/status', name: 'status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $token = $this->tokenRepository->findForUser($user->getId());

        return $this->json([
            'clientConfigured' => $this->oneDriveClient->isConfigured(),
            'connected' => null !== $token,
            'accountEmail' => $token?->getAccountEmail(),
            'accountDisplayName' => $token?->getAccountDisplayName(),
            'folderPath' => $this->oneDriveClient->getFolderPath(),
        ]);
    }

    #[Route('/auth-url', name: 'auth_url', methods: ['GET'])]
    public function authUrl(Request $request): JsonResponse
    {
        if (!$this->oneDriveClient->isConfigured()) {
            return $this->json(['error' => 'OneDrive is not configured.'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $verifier = $this->oneDriveClient->createCodeVerifier();
        $state = bin2hex(random_bytes(16));

        $session = $request->getSession();
        $session->set('onedrive_pkce', $verifier);
        $session->set('onedrive_state', $state);

        $url = $this->oneDriveClient->buildAuthorizationUrl(
            $this->callbackUrl($request),
            $state,
            $this->oneDriveClient->createCodeChallenge($verifier),
        );

        return $this->json(['url' => $url]);
    }

    #[Route('/callback', name: 'callback', methods: ['GET'])]
    public function callback(Request $request): RedirectResponse
    {
        if (!$this->oneDriveClient->isConfigured()) {
            $this->logger->error('OneDrive callback rejected: client is not configured.');
            return $this->redirectFlag('error', 'not_configured');
        }

        $session = $request->getSession();

        if ($request->query->has('error')) {
            $this->logger->error('OneDrive callback rejected by Microsoft', [
                'error' => (string) $request->query->get('error'),
                'errorDescription' => (string) $request->query->get('error_description'),
            ]);
            return $this->redirectFlag('error', 'microsoft');
        }

        $state = (string) $request->query->get('state', '');
        if ('' === $state || !hash_equals((string) $session->get('onedrive_state'), $state)) {
            $this->logger->error('OneDrive callback rejected: state mismatch.', [
                'receivedState' => $state,
                'hasSessionState' => null !== $session->get('onedrive_state'),
            ]);
            return $this->redirectFlag('error', 'state_mismatch');
        }

        $code = (string) $request->query->get('code', '');
        if ('' === $code) {
            $this->logger->error('OneDrive callback rejected: no authorization code present.');
            return $this->redirectFlag('error', 'missing_code');
        }

        $session->remove('onedrive_state');
        $pkce = (string) $session->remove('onedrive_pkce');

        try {
            $tokenData = $this->oneDriveClient->exchangeCode($this->callbackUrl($request), $code, $pkce);

            /** @var User $user */
            $user = $this->getUser();
            $token = $this->tokenRepository->findForUser($user->getId());
            if (null === $token) {
                $token = new OneDriveToken();
                $token->setUser($user);
                $this->entityManager->persist($token);
            }

            $token->setAccessToken((string) $tokenData['accessToken']);
            if (null !== $tokenData['refreshToken']) {
                $token->setRefreshToken($tokenData['refreshToken']);
            }
            $token->setExpiresAt((new \DateTimeImmutable())->modify(sprintf('+%d seconds', $tokenData['expiresIn'])));

            try {
                $account = $this->oneDriveClient->getAccountInfo((string) $tokenData['accessToken']);
                $token->setAccountEmail($account['email']);
                $token->setAccountDisplayName($account['displayName']);
            } catch (OneDriveException) {
                // Account info is best-effort; a failing profile call must not fail the connect.
            }

            $this->entityManager->flush();
        } catch (OneDriveException $e) {
            $this->logger->error('OneDrive callback failed during token exchange.', [
                'message' => $e->getMessage(),
            ]);
            return $this->redirectFlag('error', 'exchange:' . $e->getMessage());
        }

        $this->logger->info('OneDrive account connected.', [
            'accountEmail' => $token->getAccountEmail(),
        ]);
        return $this->redirectFlag('connected');
    }

    #[Route('/disconnect', name: 'disconnect', methods: ['POST'])]
    public function disconnect(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $token = $this->tokenRepository->findForUser($user->getId());

        if (null !== $token) {
            $this->entityManager->remove($token);
            $this->entityManager->flush();
        }

        return $this->json(['success' => true]);
    }

    #[Route('/files', name: 'files', methods: ['GET'])]
    public function files(): JsonResponse
    {
        if (!$this->oneDriveClient->isConfigured()) {
            return $this->json(['error' => 'OneDrive is not configured.'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        /** @var User $user */
        $user = $this->getUser();
        $token = $this->tokenRepository->findForUser($user->getId());
        if (null === $token) {
            return $this->json(['error' => 'Connect your OneDrive account to browse files.'], Response::HTTP_CONFLICT);
        }

        try {
            $accessToken = $this->freshAccessToken($token);
        } catch (OneDriveAuthException) {
            $this->expireConnection($token);

            return $this->json(['error' => 'Your OneDrive connection has expired. Please connect again.'], Response::HTTP_CONFLICT);
        } catch (OneDriveException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_GATEWAY);
        }

        try {
            $files = $this->oneDriveClient->listFiles($accessToken);
        } catch (OneDriveAuthException) {
            $this->expireConnection($token);

            return $this->json(['error' => 'Your OneDrive connection has expired. Please connect again.'], Response::HTTP_CONFLICT);
        } catch (OneDriveException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_GATEWAY);
        }

        return $this->json([
            'files' => $files,
            'folderPath' => $this->oneDriveClient->getFolderPath(),
        ]);
    }

    private function freshAccessToken(OneDriveToken $token): string
    {
        $expiresAt = $token->getExpiresAt();
        if (null !== $expiresAt && $expiresAt > new \DateTimeImmutable()) {
            return (string) $token->getAccessToken();
        }

        $fresh = $this->oneDriveClient->refreshAccess((string) $token->getRefreshToken());
        $token->setAccessToken((string) $fresh['accessToken']);
        $token->setRefreshToken((string) $fresh['refreshToken']);
        $token->setExpiresAt((new \DateTimeImmutable())->modify(sprintf('+%d seconds', $fresh['expiresIn'])));
        $this->entityManager->flush();

        return (string) $fresh['accessToken'];
    }

    private function expireConnection(OneDriveToken $token): void
    {
        $this->entityManager->remove($token);
        $this->entityManager->flush();
    }

    private function callbackUrl(Request $request): string
    {
        $override = $this->oneDriveClient->getRedirectUri();

        return '' !== $override ? $override : $request->getSchemeAndHttpHost() . '/api/onedrive/callback';
    }

    private function redirectFlag(string $flag, ?string $reason = null): RedirectResponse
    {
        $url = '/resumes?onedrive=' . $flag;
        if (null !== $reason) {
            $url .= '&reason=' . rawurlencode($reason);
        }

        return $this->redirect($url);
    }
}
