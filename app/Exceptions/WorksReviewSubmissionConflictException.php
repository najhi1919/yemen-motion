<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class WorksReviewSubmissionConflictException extends RuntimeException
{
    /** @param array<string, mixed> $readiness */
    public function __construct(
        public readonly string $currentStatus,
        public readonly ?string $currentUpdatedAt,
        public readonly array $readiness,
    ) {
        parent::__construct('Work review submission version conflict.');
    }
}
