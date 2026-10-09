<?php

namespace App\Modules\Acquirer\Core\Contracts;

use App\Modules\Acquirer\Core\DTOs\ParsedRecord;
use App\Modules\Acquirer\Core\Models\Ingestion;

interface AcquirerParserInterface
{
    /**
     * Check if this parser handles the given acquirer code.
     */
    public function supports(string $acquirerCode): bool;

    /**
     * Parse a file stream and yield ParsedRecord objects one at a time.
     * Implementations MUST use streaming — never load the entire file.
     *
     * @param resource $stream  An open readable stream (fopen handle)
     * @param Ingestion $ingestion  The ingestion context
     * @return \Generator<int, ParsedRecord>
     */
    public function parse($stream, Ingestion $ingestion): \Generator;

    /**
     * Return the acquirer code this parser handles (e.g. 'ALELO').
     */
    public function acquirerCode(): string;
}
