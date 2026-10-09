<?php

namespace App\Modules\Acquirer\Core\DTOs;

use App\Modules\Acquirer\Core\Enums\ErrorSeverity;

readonly class FieldError
{
    public function __construct(
        public string $field,
        public string $code,
        public string $message,
        public ErrorSeverity $severity,
        public ?string $sanitizedValue = null,
    ) {}

    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'code' => $this->code,
            'message' => $this->message,
            'severity' => $this->severity->value,
            'sanitized_value' => $this->sanitizedValue,
        ];
    }
}
