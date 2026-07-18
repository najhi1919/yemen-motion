<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

final class WorksSettingsVersionConflictException extends Exception
{
    public function __construct(
        public readonly int $currentVersion,
    ) {
        parent::__construct('The works settings version is no longer current.');
    }
}
