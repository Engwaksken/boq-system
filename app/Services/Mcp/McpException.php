<?php

namespace App\Services\Mcp;

use RuntimeException;

class McpException extends RuntimeException
{
    public function __construct(public readonly string $mcpCode, string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}
