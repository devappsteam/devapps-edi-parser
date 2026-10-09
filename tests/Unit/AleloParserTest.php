<?php

namespace Tests\Unit\Modules\Acquirer\Alelo;

use App\Modules\Acquirer\Alelo\Parser\AleloParser;
use App\Modules\Acquirer\Core\Models\Ingestion;
use PHPUnit\Framework\TestCase;

class AleloParserTest extends TestCase
{
    public function test_it_can_parse_alelo_header()
    {
        // 500 chars Header (00)
        // [00][20261009][103000][...][Empresa             ][...
        // 1-2=00, 3-10=20261009(8), 11-16=103000(6), 17-19=123, 20-39=Empresa...
        $line = str_pad("0020261009103000123EMPRESA TESTE       REMETENTE TESTE     DESTINATARIO TESTE  0000112345600RETORNO OK          1.0     00001", 500, " ");
        
        // Use a temporary stream
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $line . "\n");
        rewind($stream);

        $parser = new AleloParser();
        $ingestion = new Ingestion(['acquirer_code' => 'ALELO']);
        
        $generator = $parser->parse($stream, $ingestion);
        $record = $generator->current(); // get first yielded record

        $this->assertEquals('00', $record->recordType);
        $this->assertTrue($record->isHeader);
        $this->assertFalse($record->isTrailer);
        $this->assertEquals('2026-10-09', $record->field('data_processamento'));
        $this->assertEquals('103000', $record->field('hora_processamento'));
        $this->assertEquals('EMPRESA TESTE', $record->field('empresa'));
        $this->assertEquals('00', $record->field('codigo_retorno'));
        
        fclose($stream);
    }

    public function test_it_can_parse_alelo_movimento_decimals_and_signs()
    {
        // Movimento (01)
        $line = str_pad(
            "01" . // tipo
            str_pad("CHAVE123", 30, " ") . // chave
            "20261010" . // data_prev
            "20261009" . // data_proc
            str_pad("MATRIZ1", 20, " ") . 
            str_pad("ESTAB1", 20, " ") . 
            str_pad("NSU1", 10, " ") . 
            "20261009" . // transacao
            "120000" . // hora
            "01" . // tipo mov
            "+" . "000000000010000" . // bruto sinal + val (100.00)
            "-" . "000000000000500" . // taxa sinal + val (5.00)
            "+" . "000000000009500", // liq sinal + val (95.00)
            500, " ");

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $line . "\n");
        rewind($stream);

        $parser = new AleloParser();
        $ingestion = new Ingestion(['acquirer_code' => 'ALELO']);
        
        $generator = $parser->parse($stream, $ingestion);
        $record = $generator->current();

        $this->assertEquals('01', $record->recordType);
        $this->assertFalse($record->hasBlockingErrors());
        
        // Checks transformations mapped to canonical fields
        $this->assertEquals('100.00', $record->field('gross_amount'));
        $this->assertEquals('-5.00', $record->field('fee_amount'));
        $this->assertEquals('95.00', $record->field('net_amount'));
        
        fclose($stream);
    }

    public function test_it_rejects_invalid_line_length()
    {
        $line = "0020261009"; // Only 10 chars

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $line . "\n");
        rewind($stream);

        $parser = new AleloParser();
        $ingestion = new Ingestion(['acquirer_code' => 'ALELO']);
        
        $generator = $parser->parse($stream, $ingestion);
        $record = $generator->current();

        $this->assertEquals('UNKNOWN', $record->recordType);
        $this->assertTrue($record->hasBlockingErrors());
        $this->assertEquals('INVALID_LENGTH', $record->errors[0]->code);

        fclose($stream);
    }
}
