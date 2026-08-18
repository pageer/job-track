<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class ApiCsrfSubscriber implements EventSubscriberInterface
{
    public function __construct(private CsrfTokenManagerInterface $csrfTokenManager)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 0]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (!str_starts_with($path, '/api')) {
            return;
        }

        if (in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }

        // The login request is CSRF-protected by the session cookie (SameSite=Lax)
        // and receives a fresh CSRF token in its response.
        if ('/api/auth/login' === $path) {
            return;
        }

        $token = $request->headers->get('X-CSRF-TOKEN');
        if (null === $token || !$this->csrfTokenManager->isTokenValid(new CsrfToken('app', $token))) {
            throw new AccessDeniedHttpException('Invalid or missing CSRF token.');
        }
    }
}
