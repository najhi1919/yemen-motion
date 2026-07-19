<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class WorksMediaConflictException extends RuntimeException
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public readonly string $reason,
        public readonly array $data,
        string $message,
    ) {
        parent::__construct($message);
    }
}
