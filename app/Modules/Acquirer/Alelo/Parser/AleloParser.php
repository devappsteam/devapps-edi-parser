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
    /**
     * This parser handles both ALELO and NAIP files, which share the same
     * layout. The actual acquirer is determined by the 'empresa_adquirente'
     * field (pos 50-54) in the Header record.
     */
    public function supports(string $acquirerCode): bool
    {
        return in_array(strtoupper(trim($acquirerCode)), ['ALELO', 'NAIP']);
    }

    public function acquirerCode(): string
    {
        return 'ALELO';
    }

    public function parse($stream, Ingestion $ingestion): Generator
    {
        $lineNumber = 0;
        $recordCount = 0;

        while (($line = fgets($stream)) !== false) {
            $lineNumber++;
            $line = str_replace(["\r", "\n"], '', $line);

            if (empty(trim($line))) {
                continue;
            }

            // Fixed width validation — all records must be exactly 500 characters.
            if (mb_strlen($line, '8bit') !== 500) {
                yield new ParsedRecord(
                    recordType: 'UNKNOWN',
                    lineNumber: $lineNumber,
                    rawData: $line,
                    errors: [
                        new FieldError(
                            'line',
                            'INVALID_LENGTH',
                            sprintf(
                                'Expected 500 characters, got %d.',
                                mb_strlen($line, '8bit')
                            ),
                            ErrorSeverity::ERROR
                        )
                    ]
                );
                continue;
            }

            // Extract Record Type (first 2 characters, 1-based pos 1-2)
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
                            ErrorSeverity::WARNING
                        )
                    ]
                );
                continue;
            }

            // Extract fields based on the layout definition
            $fields = [];
            $errors = [];

            foreach ($definition as $fieldName => $rules) {
                [$start, $length, $type] = $rules;
                // pos is 1-based in the definition; substr() is 0-based.
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

            // Apply sign to corresponding monetary field.
            // The sign field is kept only temporarily; it must not appear in
            // the final $fields array to avoid leaking into metadata.
            $fieldsWithSign = $fields;
            $signFields = [];

            foreach ($fieldsWithSign as $key => $value) {
                if (str_ends_with($key, '_sinal')) {
                    $signFields[$key] = $value;
                    $targetKey = substr($key, 0, -6); // remove '_sinal'
                    if (isset($fieldsWithSign[$targetKey]) && $value === '-') {
                        // Apply negative sign exactly once — ltrim removes any
                        // pre-existing '-' before prepending.
                        $fieldsWithSign[$targetKey] = '-' . ltrim((string) $fieldsWithSign[$targetKey], '-');
                    }
                }
            }

            // Remove sign fields so they don't pollute metadata.
            foreach (array_keys($signFields) as $signKey) {
                unset($fieldsWithSign[$signKey]);
            }

            $fields = $fieldsWithSign;

            // Map to Canonical properties for transactional/payment records.
            if (in_array($recordType, ['01', '02', '03', '05', '06'])) {
                $fields = $this->mapToCanonicalFields($recordType, $fields);
            }

            // Count non-header, non-trailer records for trailer validation.
            if (!in_array($recordType, ['00', '99'])) {
                $recordCount++;
            }

            // Validate trailer totalisers.
            if ($recordType === '99') {
                $declaredTotal = isset($fields['total_registros'])
                    ? (int) $fields['total_registros']
                    : null;

                if ($declaredTotal !== null && $declaredTotal !== $recordCount) {
                    $errors[] = new FieldError(
                        'total_registros',
                        'TRAILER_COUNT_MISMATCH',
                        sprintf(
                            'Trailer declares %d records but %d were processed.',
                            $declaredTotal,
                            $recordCount
                        ),
                        ErrorSeverity::WARNING
                    );
                }
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
            // Numeric: keep as string to preserve leading zeros in identifiers/codes.
            'numeric' => $clean,
            'alpha'   => $clean,
            'date'    => $this->parseDate($clean),
            'time'    => $this->parseTime($clean),
            'decimal' => $this->parseDecimal($clean, $decimals ?? 6),
            default   => $clean,
        };
    }

    protected function parseDate(string $raw): ?string
    {
        // Reject all-zeros or all-spaces dates.
        if ($raw === '00000000' || strlen($raw) !== 8) {
            return null;
        }
        if (trim($raw) === '') {
            return null;
        }
        try {
            $date = Carbon::createFromFormat('Ymd', $raw);
            if (!$date || $date->format('Ymd') !== $raw) {
                throw new \InvalidArgumentException("Date value out of range: {$raw}");
            }
            return $date->format('Y-m-d');
        } catch (\Exception $e) {
            throw new \InvalidArgumentException("Invalid date format: {$raw}");
        }
    }

    protected function parseTime(string $raw): ?string
    {
        if (strlen($raw) !== 6) {
            return null;
        }
        if (trim($raw) === '' || $raw === '000000') {
            return null;
        }
        return $raw; // Stored as HH24MISS
    }

    /**
     * Parse a decimal value with $decimals implicit decimal places.
     *
     * The EDI layout stores monetary values as integer strings with implicit
     * decimal places (e.g., 6 decimals: "0000041910000" = 41.910000).
     * This method uses integer arithmetic to avoid float precision loss.
     */
    protected function parseDecimal(string $raw, int $decimals): string
    {
        // Strip leading zeros but keep at least one digit.
        $base = ltrim($raw, '0');
        if ($base === '') {
            return '0.' . str_repeat('0', $decimals);
        }

        // Ensure the string is at least (decimals + 1) digits long.
        $base = str_pad($base, $decimals + 1, '0', STR_PAD_LEFT);

        $integerPart = substr($base, 0, -$decimals);
        $decimalPart = substr($base, -$decimals);

        return $integerPart . '.' . $decimalPart;
    }

    protected function mapToCanonicalFields(string $recordType, array $extracted): array
    {
        $canonical = [];

        if ($recordType === '01') {
            $canonical = [
                'payment_key'               => $extracted['chave_pagamento'] ?? null,
                'compensation_id'           => $extracted['id_compensacao'] ?? null,
                'unique_movement_id'        => $extracted['id_movimento'] ?? null,
                'transaction_date'          => $extracted['data_transacao'] ?? null,
                'capture_time'              => $extracted['hora_transacao'] ?? null,
                'processing_date'           => $extracted['data_processamento'] ?? null,
                // pos 103-110: Data Prevista de processamento
                'expected_processing_date'  => $extracted['data_prev_processamento'] ?? null,
                // pos 125-132: Data prevista de pagamento
                'expected_payment_date'     => $extracted['data_prev_pagamento'] ?? null,
                'establishment_id'          => $extracted['estabelecimento'] ?? null,
                'payment_matrix_id'         => $extracted['estabelecimento_matriz'] ?? null,
                'cnpj_root'                 => $extracted['cnpj_raiz'] ?? null,
                'gross_amount'              => $extracted['valor_bruto'] ?? null,
                'net_amount'                => $extracted['valor_liquido'] ?? null,
                'fee_amount'                => $extracted['valor_taxa'] ?? null,
                'fee_rate'                  => $extracted['pct_taxa_adm'] ?? null,
                'tariff_amount'             => $extracted['valor_tarifa'] ?? null,
                'movement_type_code'        => $extracted['tipo_movimento'] ?? null,
                'product_code'              => $extracted['tipo_produto'] ?? null,
                'brand_code'                => $extracted['bandeira'] ?? null,
                'authorization_code'        => $extracted['codigo_autorizacao'] ?? null,
                'nsu_doc'                   => $extracted['nsu_doc'] ?? null,
                'bank_code'                 => $extracted['codigo_banco'] ?? null,
                'agency_code'               => $extracted['agencia'] ?? null,
                'account_number'            => $extracted['conta'] ?? null,
            ];
        } elseif (in_array($recordType, ['02', '06'])) {
            $canonical = [
                'payment_key'               => $extracted['chave_pagamento'] ?? null,
                'unique_movement_id'        => $extracted['id_pagamento_unificado'] ?? null,
                'establishment_id'          => $extracted['estabelecimento'] ?? null,
                'payment_matrix_id'         => $extracted['estabelecimento_matriz'] ?? null,
                'cnpj_root'                 => $extracted['cnpj_raiz'] ?? null,
                'payment_date'              => $extracted['data_pagamento'] ?? null,
                'original_payment_date'     => $extracted['data_pagamento_original'] ?? null,
                'bank_sent_date'            => $extracted['data_envio_banco'] ?? null,
                'status_update_date'        => $extracted['data_atualizacao_status'] ?? null,
                'payment_amount'            => $extracted['valor_pagamento'] ?? null,
                'payment_type'              => $extracted['tipo_pagamento'] ?? null,
                'payment_status_code'       => $extracted['status_pagamento'] ?? null,
                'rejection_code'            => $extracted['motivo_rejeicao'] ?? null,
                'is_resend'                 => isset($extracted['reenvio_pagamento'])
                                               ? ($extracted['reenvio_pagamento'] === 'S')
                                               : null,
                'bank_code'                 => $extracted['codigo_banco'] ?? null,
                'agency_code'               => $extracted['agencia'] ?? null,
                'account_number'            => $extracted['conta'] ?? null,
            ];
        } elseif ($recordType === '03') {
            $canonical = [
                'payment_key'               => $extracted['chave_pagamento'] ?? null,
                'unique_movement_id'        => $extracted['id_pagamento_unificado'] ?? null,
                'establishment_id'          => $extracted['estabelecimento'] ?? null,
                'cnpj_root'                 => $extracted['cnpj_raiz'] ?? null,
                'payment_date'              => $extracted['data_pagamento'] ?? null,
                'payment_amount'            => $extracted['valor_pagamento'] ?? null,
                'payment_type'              => $extracted['tipo_pagamento'] ?? null,
                'payment_status_code'       => $extracted['status_pagamento'] ?? null,
                'rejection_code'            => $extracted['motivo_rejeicao'] ?? null,
                'bank_code'                 => $extracted['codigo_banco'] ?? null,
                'agency_code'               => $extracted['agencia'] ?? null,
                'account_number'            => $extracted['conta'] ?? null,
            ];
        } elseif ($recordType === '05') {
            // Registro 05 – Debit Balance (Saldo Negativo)
            // Fields have their own semantics; do NOT reuse gross/net/fee fields.
            $canonical = [
                'establishment_id'          => $extracted['estabelecimento'] ?? null,
                'cnpj_root'                 => $extracted['cnpj_raiz'] ?? null,
                'processing_date'           => $extracted['data_processamento'] ?? null,
                'movement_type_code'        => $extracted['tipo_movimento'] ?? null,
                'product_code'              => $extracted['tipo_produto'] ?? null,
                // Saldo Negativo-specific fields go into metadata to preserve semantics.
            ];
        }

        // Any non-canonical extracted values go into metadata.
        $canonicalKeys = array_keys($canonical);
        $canonical['metadata'] = array_diff_key($extracted, array_flip($canonicalKeys));

        return array_filter($canonical, fn($val) => $val !== null);
    }
}
