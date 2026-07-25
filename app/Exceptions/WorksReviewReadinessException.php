<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class WorksReviewReadinessException extends RuntimeException
{
    /** @param array<string, mixed> $readiness */
    public function __construct(public readonly array $readiness)
    {
        parent::__construct('Work is not ready for review submission.');
    }
}
