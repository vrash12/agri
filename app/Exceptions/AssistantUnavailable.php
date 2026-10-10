<?php

namespace App\Exceptions;

use RuntimeException;

final class AssistantUnavailable extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 503)
    {
        parent::__construct($message);
    }
}
