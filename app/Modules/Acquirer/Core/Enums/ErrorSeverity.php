<?php

namespace App\Modules\Acquirer\Core\Enums;

enum ErrorSeverity: string
{
    case ERROR = 'error';
    case WARNING = 'warning';
    case INFO = 'info';

    public function isBlocking(): bool
    {
        return $this === self::ERROR;
    }
}
