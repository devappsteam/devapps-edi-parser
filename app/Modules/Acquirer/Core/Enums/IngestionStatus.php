<?php

namespace App\Modules\Acquirer\Core\Enums;

enum IngestionStatus: string
{
    case RECEIVED = 'received';
    case VALIDATING = 'validating';
    case QUEUED = 'queued';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case COMPLETED_WITH_WARNINGS = 'completed_with_warnings';
    case FAILED = 'failed';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';
    case REPROCESSING = 'reprocessing';

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::COMPLETED,
            self::COMPLETED_WITH_WARNINGS,
            self::FAILED,
            self::REJECTED,
            self::CANCELLED,
        ]);
    }

    public function allowsReprocessing(): bool
    {
        return in_array($this, [
            self::COMPLETED,
            self::COMPLETED_WITH_WARNINGS,
            self::FAILED,
        ]);
    }

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::RECEIVED      => in_array($next, [self::VALIDATING, self::REJECTED]),
            self::VALIDATING    => in_array($next, [self::QUEUED, self::REJECTED]),
            self::QUEUED        => in_array($next, [self::PROCESSING]),
            self::PROCESSING    => in_array($next, [self::COMPLETED, self::COMPLETED_WITH_WARNINGS, self::FAILED]),
            self::REPROCESSING  => in_array($next, [self::PROCESSING]),
            self::COMPLETED,
            self::COMPLETED_WITH_WARNINGS,
            self::FAILED        => in_array($next, [self::REPROCESSING]),
            self::REJECTED,
            self::CANCELLED     => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::RECEIVED                => 'Received',
            self::VALIDATING              => 'Validating',
            self::QUEUED                  => 'Queued for Processing',
            self::PROCESSING              => 'Processing',
            self::COMPLETED               => 'Completed',
            self::COMPLETED_WITH_WARNINGS => 'Completed with Warnings',
            self::FAILED                  => 'Failed',
            self::REJECTED                => 'Rejected',
            self::CANCELLED               => 'Cancelled',
            self::REPROCESSING            => 'Reprocessing',
        };
    }
}
