<?php

namespace App\Service;

/**
 * Thrown when the stored OneDrive token is no longer usable (expired, revoked,
 * or the OAuth grant was invalidated). Callers should discard the token and ask
 * the user to connect again.
 */
class OneDriveAuthException extends OneDriveException
{
}
