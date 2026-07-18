<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class WorksAuthoringStateConflictException extends RuntimeException
{
    public function __construct(public readonly string $currentStatus)
    {
        parent::__construct('لا يمكن تعديل العمل في حالته الحالية.');
    }
}
