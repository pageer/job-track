<?php

namespace App\Service;

class OneDriveException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly bool $configurationProblem = false,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function isConfigurationProblem(): bool
    {
        return $this->configurationProblem;
    }
}