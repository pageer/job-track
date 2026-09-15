<?php

namespace App\Service;

/**
 * Thrown by AiExtractor when an extraction cannot be produced.
 */
class AiExtractionException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly bool $configurationProblem = false,
    ) {
        parent::__construct($message);
    }

    public function isConfigurationProblem(): bool
    {
        return $this->configurationProblem;
    }
}
