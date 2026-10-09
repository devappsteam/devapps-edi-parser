<?php

namespace App\Modules\Acquirer\Alelo\Parser;

use App\Modules\Acquirer\Core\Contracts\AcquirerParserInterface;
use App\Modules\Acquirer\Core\DTOs\FieldError;
use App\Modules\Acquirer\Core\DTOs\ParsedRecord;
use App\Modules\Acquirer\Core\Enums\ErrorSeverity;
use App\Modules\Acquirer\Core\Models\Ingestion;
use Carbon\Carbon;
use Generator;

class AleloParser implements AcquirerParserInterface
{
    public function supports(string $acquirerCode): bool
    {
        return strtoupper($acquirerCode) === 'ALELO';
    }

    public function acquirerCode(): string
    {
        return 'ALELO';
    }

    public function parse($stream, Ingestion $ingestion): Generator
    {
        $lineNumber = 0;

        while (($line = fgets($stream)) !== false) {
            $lineNumber++;
            $line = str_replace(["\r", "\n"], '', $line);
            
            if (empty(trim($line))) {
                continue;
            }

            // Fixed width validation
            if (mb_strlen($line, '8bit') !== 500) {
                yield new ParsedRecord(
                    recordType: 'UNKNOWN',
                    lineNumber: $lineNumber,
                    rawData: $line,
                    errors: [
                        new FieldError(
                            'line', 
                            'INVALID_LENGTH', 
                            'Line length is not 500 characters', 
                            ErrorSeverity::ERROR
                        )
                    ]
                );
                continue;
            }

            // Extract Record Type (first 2 characters)
            $recordType = substr($line, 0, 2);
            $definition = AleloLayoutDefinition::getDefinitionFor($recordType);

            if (!$definition) {
                yield new ParsedRecord(
                    recordType: $recordType,
                    lineNumber: $lineNumber,
                    rawData: $line,
                    errors: [
                        new FieldError(
                            'tipo_registro', 
                            'UNKNOWN_RECORD_TYPE', 
                            "Unsupported record type: {$recordType}", 
                            ErrorSeverity::WARNING // We skip unknown records but don't halt ingestion
                        )
                    ]
                );
                continue;
            }

            // Extract fields based on layout definition
            $fields = [];
            $errors = [];
            
            foreach ($definition as $fieldName => $rules) {
                [$start, $length, $type] = $rules;
                // Pos is 1-based in definition, substr is 0-based
                $rawValue = substr($line, $start - 1, $length);
                
                try {
                    $fields[$fieldName] = $this->transform($rawValue, $type, $rules[3] ?? null);
                } catch (\Exception $e) {
                    $errors[] = new FieldError(
                        $fieldName,
                        'PARSE_ERROR',
                        $e->getMessage(),
                        ErrorSeverity::ERROR,
                        $rawValue
                    );
                }
            }

            foreach ($fields as $key => $value) {
                if (str_ends_with($key, '_sinal')) {
                    $targetKey = substr($key, 0, -6); // remove '_sinal'
                    if (isset($fields[$targetKey]) && $value === '-') {
                        $fields[$targetKey] = '-' . ltrim($fields[$targetKey], '-');
                    }
                }
            }

            // Mapeia para o Canonical properties esperadas caso seja transacional
            if (in_array($recordType, ['01', '02', '03', '05', '06'])) {
                $fields = $this->mapToCanonicalFields($recordType, $fields);
            }

            yield new ParsedRecord(
                recordType: $recordType,
                lineNumber: $lineNumber,
                rawData: $line,
                fields: $fields,
                isHeader: $recordType === '00',
                isTrailer: $recordType === '99',
                errors: $errors
            );
        }
    }

    protected function transform(string $raw, string $type, ?int $decimals = null): mixed
    {
        $clean = trim($raw);
        if ($clean === '') {
            return null;
        }

        return match ($type) {
            'numeric' => $clean, // IDs in EDI need leading zeros
            'alpha' => $clean,
            'date' => $this->parseDate($clean),
            'time' => $this->parseTime($clean),
            'decimal' => $this->parseDecimal($clean, $decimals ?? 2),
            default => $clean,
        };
    }

    protected function parseDate(string $raw): ?string
    {
        if ($raw === '00000000' || strlen($raw) !== 8) return null;
        try {
            return Carbon::createFromFormat('Ymd', $raw)->format('Y-m-d');
        } catch (\Exception $e) {
            throw new \InvalidArgumentException("Invalid date format: {$raw}");
        }
    }

    protected function parseTime(string $raw): ?string
    {
        if ($raw === '000000' || strlen($raw) !== 6) return null;
        return $raw; // Stored as HH24MISS
    }

    protected function parseDecimal(string $raw, int $decimals): string
    {
        // 15 chars e.g. 000000000012345 with 2 decimals => 123.45
        // Safe math avoiding float precision loss
        $base = ltrim($raw, '0');
        if ($base === '') {
            return '0.' . str_repeat('0', $decimals);
        }
        
        // Pad left if it's smaller than the decimal required
        $base = str_pad($base, $decimals + 1, '0', STR_PAD_LEFT);
        
        $integerPart = substr($base, 0, -$decimals);
        $decimalPart = substr($base, -$decimals);
        
        return $integerPart . '.' . $decimalPart;
    }

    protected function mapToCanonicalFields(string $recordType, array $extracted): array
    {
        // Maps specific Alelo keys to generic Core CanonicalRecord keys
        $canonical = [
            'payment_key' => $extracted['chave_pagamento'] ?? null,
            'transaction_date' => $extracted['data_transacao'] ?? null,
            'capture_time' => $extracted['hora_transacao'] ?? null,
            'processing_date' => $extracted['data_processamento'] ?? null,
            'expected_payment_date' => $extracted['data_pagamento_prevista'] ?? null,
            'payment_date' => $extracted['data_envio_banco'] ?? null, // Approximate for Alelo (02)
            'establishment_id' => $extracted['estabelecimento'] ?? null,
            'payment_matrix_id' => $extracted['estabelecimento_matriz'] ?? null,
            'cnpj_root' => $extracted['cnpj_raiz'] ?? null,
            
            'gross_amount' => $extracted['valor_bruto'] ?? null,
            'net_amount' => $extracted['valor_liquido'] ?? null,
            'fee_amount' => $extracted['valor_taxa'] ?? null,
            'tariff_amount' => $extracted['valor_tarifa'] ?? null,
            'fee_rate' => $extracted['taxa_adm'] ?? null,
            
            'movement_type_code' => $extracted['tipo_movimento'] ?? null,
            'product_code' => $extracted['tipo_produto'] ?? null,
            'authorization_code' => $extracted['codigo_autorizacao'] ?? null,
            'nsu_doc' => $extracted['nsu_host'] ?? null,
            
            'payment_status_code' => $extracted['status_pagamento'] ?? null,
            'rejection_code' => $extracted['motivo_rejeicao'] ?? null,
            
            'bank_code' => $extracted['codigo_banco'] ?? null,
            'agency_code' => $extracted['agencia'] ?? null,
            'account_number' => $extracted['conta'] ?? null,
        ];

        // Any non-canonical extracted values go into metadata
        $canonical['metadata'] = array_diff_key($extracted, $canonical);
        
        return array_filter($canonical, fn($val) => $val !== null);
    }
}
