<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Every enabled AI provider is out of tokens or credit. Admins have been notified.
 */
class AiCreditExhaustedException extends RuntimeException
{
    public function __construct(string $message = 'The provider has no tokens or credit left. An administrator has been notified.', ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
