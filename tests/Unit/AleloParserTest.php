<?php

namespace Tests\Unit\Modules\Acquirer\Alelo;

use App\Modules\Acquirer\Alelo\Parser\AleloParser;
use App\Modules\Acquirer\Core\Models\Ingestion;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for AleloParser — Layout v2.1_3 (vigência 12/02/2026).
 *
 * All fixtures are extracted from the real file:
 *   Arquivos de Teste/Alelo/20260907_0348_NAIP_04_0002096404_95421830000107_0021_PRD_0001040.txt
 *
 * Field positions are 1-based as per the manual.
 */
class AleloParserTest extends TestCase
{
    // =========================================================================
    // Fixture lines extracted verbatim from the real test file.
    // =========================================================================

    /**
     * Line 1 of the real file — Header (00), NAIP, extrato 04.
     * empresa_adquirente (pos 50-54) = "NAIP "
     * tipo_extrato       (pos 55-56) = "04"
     */
    private const HEADER_LINE = '00000000002096404202609072026090720260907D0001040NAIP 04                              nfemelissa@gmail.com002.109092759000116000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000';

    /**
     * Line 2 of the real file — Movimento 01, tipo_movimento=04 (Pagamento).
     * All monetary values are zero. Date of transaction (pos 111-118) = spaces.
     * id_movimento (pos 279-296) = "000000007480630446"
     */
    private const MOVIMENTO_04_LINE = '01                     44204654600000000209640400000000209640495421830 04      0000000000000002026090620260906              20260908+0000041910000+0000041910000-000000000000000000000000000000000000000  58    007 00                          0  000000     0 341 3715         27805000000007480630446   000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000';

    /**
     * Line 3 of the real file — Movimento 01, tipo_movimento=01 (Venda).
     * Negative fee amount (valor_taxa_sinal = '-').
     * id_movimento (pos 279-296) = "000000007479703844"
     */
    private const MOVIMENTO_01_LINE = '01                     44204654600000000209640400000000209640495421830 01      00000000000000020260906202609062026090614394220260908+0000046490000+0000043710000-000000278000000000059900000000000000000  580100007 05   509882******701101770638  094747133653 341 3715         27805000000007479703844100000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000';

    /**
     * Line 4 of the real file — Saldo Negativo (05).
     * saldo_atual=0, abatimento=1800000 (R$1,80), saldo_anterior=1800000 (R$1,80)
     */
    private const SALDO_NEGATIVO_LINE = '05                                            000000000209640495421830005                    020260906                     020260903-0000000000000-0000001800000-00000018000000000000000002            0005800000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000';

    /**
     * Line 5 of the real file — Trailer (99).
     * total_registros (pos 3-13) = "00000000003" (3)
     * total_filiais   (pos 14-19) = "000001" (1)
     */
    private const TRAILER_LINE = '99000000000030000010000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000';

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeStream(string ...$lines): mixed
    {
        $stream = fopen('php://memory', 'r+');
        foreach ($lines as $line) {
            fwrite($stream, $line . "\n");
        }
        rewind($stream);
        return $stream;
    }

    private function makeIngestion(string $acquirerCode = 'NAIP'): Ingestion
    {
        return new Ingestion(['acquirer_code' => $acquirerCode]);
    }

    private function parseFirst(string ...$lines): \App\Modules\Acquirer\Core\DTOs\ParsedRecord
    {
        $stream = $this->makeStream(...$lines);
        $parser = new AleloParser();
        $gen = $parser->parse($stream, $this->makeIngestion());
        $record = $gen->current();
        fclose($stream);
        return $record;
    }

    // =========================================================================
    // 1. Header (Registro 00)
    // =========================================================================

    public function test_header_record_type_is_00(): void
    {
        $record = $this->parseFirst(self::HEADER_LINE);
        $this->assertSame('00', $record->recordType);
        $this->assertTrue($record->isHeader);
        $this->assertFalse($record->isTrailer);
    }

    public function test_header_acquirer_is_naip(): void
    {
        $record = $this->parseFirst(self::HEADER_LINE);
        $this->assertSame('NAIP', $record->field('empresa_adquirente'));
    }

    public function test_header_data_processamento(): void
    {
        $record = $this->parseFirst(self::HEADER_LINE);
        $this->assertSame('2026-09-07', $record->field('data_processamento'));
    }

    public function test_header_tipo_extrato_is_04(): void
    {
        $record = $this->parseFirst(self::HEADER_LINE);
        $this->assertSame('04', $record->field('tipo_extrato'));
    }

    public function test_header_versao_layout(): void
    {
        $record = $this->parseFirst(self::HEADER_LINE);
        $this->assertSame('002.1', $record->field('versao_layout'));
    }

    public function test_header_cnpj(): void
    {
        $record = $this->parseFirst(self::HEADER_LINE);
        $this->assertSame('09092759000116', $record->field('cnpj'));
    }

    public function test_header_id_agrupamento(): void
    {
        $record = $this->parseFirst(self::HEADER_LINE);
        $this->assertSame('000000002096404', $record->field('id_agrupamento'));
    }

    public function test_header_periodo_inicial_e_final(): void
    {
        $record = $this->parseFirst(self::HEADER_LINE);
        $this->assertSame('2026-09-07', $record->field('periodo_inicial'));
        $this->assertSame('2026-09-07', $record->field('periodo_final'));
    }

    public function test_header_sequencia(): void
    {
        $record = $this->parseFirst(self::HEADER_LINE);
        $this->assertSame('0001040', $record->field('sequencia'));
    }

    // =========================================================================
    // 2. Movimento 01 — tipo 04 (Pagamento, valores zerados)
    // =========================================================================

    public function test_movimento_04_record_type_is_01(): void
    {
        $record = $this->parseFirst(self::MOVIMENTO_04_LINE);
        $this->assertSame('01', $record->recordType);
        $this->assertFalse($record->hasBlockingErrors());
    }

    public function test_movimento_04_tipo_movimento(): void
    {
        $record = $this->parseFirst(self::MOVIMENTO_04_LINE);
        $this->assertSame('04', $record->field('movement_type_code'));
    }

    public function test_movimento_04_payment_key_preserves_value(): void
    {
        $record = $this->parseFirst(self::MOVIMENTO_04_LINE);
        // pos 3-32 = '                     442046546' → trim → '442046546'
        $this->assertSame('442046546', $record->field('payment_key'));
    }

    public function test_movimento_04_processing_date(): void
    {
        $record = $this->parseFirst(self::MOVIMENTO_04_LINE);
        $this->assertSame('2026-09-06', $record->field('processing_date'));
    }

    public function test_movimento_04_expected_processing_date(): void
    {
        // pos 103-110 = '20260906'
        $record = $this->parseFirst(self::MOVIMENTO_04_LINE);
        $this->assertSame('2026-09-06', $record->field('expected_processing_date'));
    }

    public function test_movimento_04_transaction_date_is_null_for_non_sale(): void
    {
        // pos 111-118 = '        ' (spaces) — only populated for sale movements
        $record = $this->parseFirst(self::MOVIMENTO_04_LINE);
        $this->assertNull($record->field('transaction_date'));
    }

    public function test_movimento_04_expected_payment_date(): void
    {
        // pos 125-132 = '20260908'
        $record = $this->parseFirst(self::MOVIMENTO_04_LINE);
        $this->assertSame('2026-09-08', $record->field('expected_payment_date'));
    }

    public function test_movimento_04_gross_amount_with_6_decimals(): void
    {
        // pos 134-146 = '0000041910000', 6 implicit decimals → 41.910000
        $record = $this->parseFirst(self::MOVIMENTO_04_LINE);
        $this->assertSame('41.910000', $record->field('gross_amount'));
    }

    public function test_movimento_04_net_amount_equals_gross_when_no_fee(): void
    {
        // Both bruto and liquido = 0000041910000 → 41.910000
        $record = $this->parseFirst(self::MOVIMENTO_04_LINE);
        $this->assertSame('41.910000', $record->field('net_amount'));
    }

    public function test_movimento_04_fee_amount_is_zero(): void
    {
        // pos 162-174 = '0000000000000' → 0.000000
        $record = $this->parseFirst(self::MOVIMENTO_04_LINE);
        $this->assertSame('0.000000', $record->field('fee_amount'));
    }

    public function test_movimento_04_unique_movement_id(): void
    {
        // pos 279-296 = '000000007480630446'
        $record = $this->parseFirst(self::MOVIMENTO_04_LINE);
        $this->assertSame('000000007480630446', $record->field('unique_movement_id'));
    }

    public function test_movimento_04_brand_code(): void
    {
        // pos 209-211 = '007'
        $record = $this->parseFirst(self::MOVIMENTO_04_LINE);
        $this->assertSame('007', $record->field('brand_code'));
    }

    public function test_movimento_04_bank_code_preserves_format(): void
    {
        // pos 256-259 = ' 341' → trim → '341'
        $record = $this->parseFirst(self::MOVIMENTO_04_LINE);
        $this->assertSame('341', $record->field('bank_code'));
    }

    public function test_movimento_04_nsu_doc(): void
    {
        // pos 250-255 = '     0' → trim → '0'
        $record = $this->parseFirst(self::MOVIMENTO_04_LINE);
        $this->assertSame('0', $record->field('nsu_doc'));
    }

    // =========================================================================
    // 3. Movimento 01 — tipo 01 (Venda, valor negativo na taxa)
    // =========================================================================

    public function test_movimento_01_tipo_movimento(): void
    {
        $record = $this->parseFirst(self::MOVIMENTO_01_LINE);
        $this->assertSame('01', $record->field('movement_type_code'));
    }

    public function test_movimento_01_gross_amount_positive(): void
    {
        // '+' sinal + '0000046490000' → 46.490000
        $record = $this->parseFirst(self::MOVIMENTO_01_LINE);
        $this->assertSame('46.490000', $record->field('gross_amount'));
    }

    public function test_movimento_01_net_amount_positive(): void
    {
        // '+' sinal + '0000043710000' → 43.710000
        $record = $this->parseFirst(self::MOVIMENTO_01_LINE);
        $this->assertSame('43.710000', $record->field('net_amount'));
    }

    public function test_movimento_01_fee_amount_negative(): void
    {
        // '-' sinal + '0000002780000' → -2.780000
        $record = $this->parseFirst(self::MOVIMENTO_01_LINE);
        $this->assertSame('-2.780000', $record->field('fee_amount'));
    }

    public function test_movimento_01_sign_applied_exactly_once(): void
    {
        // Ensures the sign is not doubled (not '--2.780000')
        $record = $this->parseFirst(self::MOVIMENTO_01_LINE);
        $feeAmount = $record->field('fee_amount');
        $this->assertSame(1, substr_count($feeAmount, '-'));
    }

    public function test_movimento_01_unique_movement_id_with_leading_zeros(): void
    {
        // pos 279-296 = '000000007479703844' — leading zeros must be preserved
        $record = $this->parseFirst(self::MOVIMENTO_01_LINE);
        $this->assertSame('000000007479703844', $record->field('unique_movement_id'));
    }

    public function test_movimento_01_transaction_date(): void
    {
        // pos 111-118 = '20260906'
        $record = $this->parseFirst(self::MOVIMENTO_01_LINE);
        $this->assertSame('2026-09-06', $record->field('transaction_date'));
    }

    public function test_movimento_01_nsu_doc_preserves_leading_zeros(): void
    {
        // pos 250-255 = '133653' → alpha type, leading zeros preserved
        $record = $this->parseFirst(self::MOVIMENTO_01_LINE);
        $this->assertSame('133653', $record->field('nsu_doc'));
    }

    public function test_movimento_01_product_code(): void
    {
        // pos 201-204 = '  58' → trim → '58'
        $record = $this->parseFirst(self::MOVIMENTO_01_LINE);
        $this->assertSame('58', $record->field('product_code'));
    }

    // =========================================================================
    // 4. Saldo Negativo (Registro 05)
    // =========================================================================

    public function test_saldo_negativo_record_type_is_05(): void
    {
        $record = $this->parseFirst(self::SALDO_NEGATIVO_LINE);
        $this->assertSame('05', $record->recordType);
        $this->assertFalse($record->hasBlockingErrors());
    }

    public function test_saldo_negativo_estabelecimento(): void
    {
        // pos 48-62 = '000000002096404'
        $record = $this->parseFirst(self::SALDO_NEGATIVO_LINE);
        $this->assertSame('000000002096404', $record->field('establishment_id'));
    }

    public function test_saldo_negativo_data_processamento(): void
    {
        // pos 95-102 = '20260906'
        $record = $this->parseFirst(self::SALDO_NEGATIVO_LINE);
        $this->assertSame('2026-09-06', $record->field('processing_date'));
    }

    public function test_saldo_negativo_saldo_atual_is_zero(): void
    {
        // pos 134-146 = '0000000000000' → 0.000000
        $record = $this->parseFirst(self::SALDO_NEGATIVO_LINE);
        $metadata = $record->field('metadata', []);
        $this->assertSame('0.000000', $metadata['saldo_atual'] ?? null);
    }

    public function test_saldo_negativo_abatimento_semantics(): void
    {
        // pos 148-160 = '0000001800000' → 1.800000 (with sign '-' → -1.800000)
        $record = $this->parseFirst(self::SALDO_NEGATIVO_LINE);
        $metadata = $record->field('metadata', []);
        $this->assertSame('-1.800000', $metadata['abatimento'] ?? null);
    }

    public function test_saldo_negativo_saldo_anterior_semantics(): void
    {
        // pos 162-174 = '0000001800000' → -1.800000
        $record = $this->parseFirst(self::SALDO_NEGATIVO_LINE);
        $metadata = $record->field('metadata', []);
        $this->assertSame('-1.800000', $metadata['saldo_anterior'] ?? null);
    }

    public function test_saldo_negativo_does_not_have_gross_amount(): void
    {
        // Registro 05 must NOT be interpreted as a transaction — no gross_amount
        $record = $this->parseFirst(self::SALDO_NEGATIVO_LINE);
        $this->assertNull($record->field('gross_amount'));
    }

    public function test_saldo_negativo_data_inicio_saldo_negativo(): void
    {
        // pos 125-132 = '20260903'
        $record = $this->parseFirst(self::SALDO_NEGATIVO_LINE);
        $metadata = $record->field('metadata', []);
        $this->assertSame('2026-09-03', $metadata['data_inicio_saldo_negativo'] ?? null);
    }

    // =========================================================================
    // 5. Trailer (Registro 99)
    // =========================================================================

    public function test_trailer_record_type_is_99(): void
    {
        $record = $this->parseFirst(self::TRAILER_LINE);
        $this->assertSame('99', $record->recordType);
        $this->assertTrue($record->isTrailer);
        $this->assertFalse($record->isHeader);
    }

    public function test_trailer_total_registros(): void
    {
        // pos 3-13 (11 chars) = '00000000003' → '3'
        $record = $this->parseFirst(self::TRAILER_LINE);
        $this->assertSame('3', $record->field('total_registros'));
    }

    public function test_trailer_total_filiais(): void
    {
        // pos 14-19 (6 chars) = '000001' → '1'
        $record = $this->parseFirst(self::TRAILER_LINE);
        $this->assertSame('1', $record->field('total_filiais'));
    }

    public function test_trailer_validates_record_count(): void
    {
        // The real file has 3 non-header/trailer records (mov04, mov01, saldo05).
        // Trailer declares 3 → no mismatch warning.
        $stream = $this->makeStream(
            self::HEADER_LINE,
            self::MOVIMENTO_04_LINE,
            self::MOVIMENTO_01_LINE,
            self::SALDO_NEGATIVO_LINE,
            self::TRAILER_LINE,
        );
        $parser = new AleloParser();
        $gen = $parser->parse($stream, $this->makeIngestion());

        $trailerRecord = null;
        foreach ($gen as $record) {
            $trailerRecord = $record;
        }
        fclose($stream);

        $this->assertNotNull($trailerRecord);
        $this->assertSame('99', $trailerRecord->recordType);

        $hasMismatch = false;
        foreach ($trailerRecord->errors as $err) {
            if ($err->code === 'TRAILER_COUNT_MISMATCH') {
                $hasMismatch = true;
            }
        }
        $this->assertFalse($hasMismatch, 'Trailer count should match for the real test file.');
    }

    public function test_trailer_reports_mismatch_when_count_differs(): void
    {
        // Trailer says 99 records but only 1 movement line is present.
        $trailerMismatch = str_pad('9900000000099000001', 500, '0');
        $stream = $this->makeStream(self::MOVIMENTO_04_LINE, $trailerMismatch);
        $parser = new AleloParser();
        $gen = $parser->parse($stream, $this->makeIngestion());

        $trailerRecord = null;
        foreach ($gen as $record) {
            $trailerRecord = $record;
        }
        fclose($stream);

        $this->assertNotNull($trailerRecord);
        $hasMismatch = false;
        foreach ($trailerRecord->errors as $err) {
            if ($err->code === 'TRAILER_COUNT_MISMATCH') {
                $hasMismatch = true;
            }
        }
        $this->assertTrue($hasMismatch, 'Should report TRAILER_COUNT_MISMATCH.');
    }

    // =========================================================================
    // 6. Campos em branco, zerados e identificadores com zeros à esquerda
    // =========================================================================

    public function test_blank_date_returns_null(): void
    {
        // pos 111-118 of MOVIMENTO_04_LINE = '        ' (all spaces) → null
        $record = $this->parseFirst(self::MOVIMENTO_04_LINE);
        $this->assertNull($record->field('transaction_date'));
    }

    public function test_all_zeros_date_returns_null(): void
    {
        // Manual rule: date '00000000' must return null.
        $base = str_pad('01', 500, ' ');
        // Replace pos 95-102 with '00000000'
        $line = substr_replace($base, '00000000', 94, 8);
        $line = str_pad($line, 500, ' ');
        $record = $this->parseFirst($line);
        $this->assertNull($record->field('processing_date'));
    }

    public function test_numeric_id_preserves_leading_zeros(): void
    {
        // id_movimento must preserve all 18 digits including leading zeros.
        $record = $this->parseFirst(self::MOVIMENTO_01_LINE);
        $id = $record->field('unique_movement_id');
        $this->assertSame(18, strlen($id));
        $this->assertStringStartsWith('0', $id);
    }

    public function test_cnpj_raiz_preserved(): void
    {
        // pos 63-70 = '95421830'
        $record = $this->parseFirst(self::MOVIMENTO_01_LINE);
        $this->assertSame('95421830', $record->field('cnpj_root'));
    }

    // =========================================================================
    // 7. Comprimento de registro inválido
    // =========================================================================

    public function test_rejects_line_shorter_than_500_chars(): void
    {
        $line = '0020261009'; // Only 10 chars
        $record = $this->parseFirst($line);

        $this->assertSame('UNKNOWN', $record->recordType);
        $this->assertTrue($record->hasBlockingErrors());

        $codes = array_map(fn($e) => $e->code, $record->errors);
        $this->assertContains('INVALID_LENGTH', $codes);
    }

    public function test_rejects_line_longer_than_500_chars(): void
    {
        $line = str_repeat('0', 501);
        $record = $this->parseFirst($line);

        $this->assertSame('UNKNOWN', $record->recordType);
        $this->assertTrue($record->hasBlockingErrors());

        $codes = array_map(fn($e) => $e->code, $record->errors);
        $this->assertContains('INVALID_LENGTH', $codes);
    }

    // =========================================================================
    // 8. supports() — NAIP e ALELO
    // =========================================================================

    public function test_parser_supports_alelo(): void
    {
        $parser = new AleloParser();
        $this->assertTrue($parser->supports('ALELO'));
        $this->assertTrue($parser->supports('alelo'));
    }

    public function test_parser_supports_naip(): void
    {
        $parser = new AleloParser();
        $this->assertTrue($parser->supports('NAIP'));
        $this->assertTrue($parser->supports('naip'));
    }

    public function test_parser_does_not_support_other_acquirers(): void
    {
        $parser = new AleloParser();
        $this->assertFalse($parser->supports('CIELO'));
        $this->assertFalse($parser->supports('REDE'));
    }

    // =========================================================================
    // 9. Arquivo completo — integração e idempotência
    // =========================================================================

    public function test_full_file_parsing_yields_5_records(): void
    {
        $stream = $this->makeStream(
            self::HEADER_LINE,
            self::MOVIMENTO_04_LINE,
            self::MOVIMENTO_01_LINE,
            self::SALDO_NEGATIVO_LINE,
            self::TRAILER_LINE,
        );
        $parser = new AleloParser();
        $records = iterator_to_array($parser->parse($stream, $this->makeIngestion()), false);
        fclose($stream);

        $this->assertCount(5, $records);
    }

    public function test_full_file_parsing_is_idempotent(): void
    {
        // Parse the same content twice and compare field values.
        $makeRecords = function () {
            $stream = $this->makeStream(
                self::HEADER_LINE,
                self::MOVIMENTO_04_LINE,
                self::MOVIMENTO_01_LINE,
                self::SALDO_NEGATIVO_LINE,
                self::TRAILER_LINE,
            );
            $parser = new AleloParser();
            $records = iterator_to_array($parser->parse($stream, $this->makeIngestion()), false);
            fclose($stream);
            return $records;
        };

        $first  = $makeRecords();
        $second = $makeRecords();

        $this->assertCount(count($first), $second);

        foreach ($first as $i => $record) {
            $this->assertSame($record->fields, $second[$i]->fields,
                "Fields differ at record index {$i} between two identical parse runs.");
        }
    }

    public function test_empty_lines_are_skipped(): void
    {
        $stream = $this->makeStream('', self::TRAILER_LINE, '');
        $parser = new AleloParser();
        $records = iterator_to_array($parser->parse($stream, $this->makeIngestion()), false);
        fclose($stream);

        $this->assertCount(1, $records);
        $this->assertSame('99', $records[0]->recordType);
    }
}
