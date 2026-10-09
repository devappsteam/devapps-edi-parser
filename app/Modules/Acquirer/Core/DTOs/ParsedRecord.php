<?php

namespace App\Modules\Acquirer\Core\DTOs;

use App\Modules\Acquirer\Core\Enums\ErrorSeverity;

readonly class ParsedRecord
{
    /**
     * @param string $recordType  The raw record type code (e.g. '00', '01', '99')
     * @param int $lineNumber  1-based line number in the source file
     * @param string $rawData  The complete raw line (for audit)
     * @param array<string, mixed> $fields  Parsed field name => value pairs
     * @param bool $isHeader  True if this is a header record
     * @param bool $isTrailer  True if this is a trailer record
     * @param array<FieldError> $errors  Validation errors/warnings for this record
     */
    public function __construct(
        public string $recordType,
        public int $lineNumber,
        public string $rawData,
        public array $fields = [],
        public bool $isHeader = false,
        public bool $isTrailer = false,
        public array $errors = [],
    ) {}

    public function hasBlockingErrors(): bool
    {
        foreach ($this->errors as $error) {
            if ($error->severity->isBlocking()) {
                return true;
            }
        }
        return false;
    }

    public function hasWarnings(): bool
    {
        foreach ($this->errors as $error) {
            if ($error->severity === ErrorSeverity::WARNING) {
                return true;
            }
        }
        return false;
    }

    public function field(string $name, mixed $default = null): mixed
    {
        return $this->fields[$name] ?? $default;
    }
}
