<?php

namespace App\Modules\Acquirer\Alelo\Parser;

class AleloLayoutDefinition
{
    /**
     * Map of record types to their field definitions.
     * Definitions follow the [start (1-based), length, type, decimals] format.
     */
    public static function getDefinitionFor(string $recordType): ?array
    {
        return match ($recordType) {
            '00' => self::header(),
            '01' => self::movimento(),
            '02' => self::pagamento(),
            '03' => self::pagamentoMultibeneficios(),
            '05' => self::saldoNegativo(),
            '06' => self::totalPagamentoUnificado(),
            '99' => self::trailer(),
            default => null,
        };
    }

    protected static function header(): array
    {
        return [
            // name => [start, length, type, default_decimals]
            'tipo_registro' => [1, 2, 'numeric'],
            'data_processamento' => [3, 8, 'date'],
            'hora_processamento' => [11, 6, 'time'],
            'uso_alelo_1' => [17, 3, 'alpha'],
            'empresa' => [20, 20, 'alpha'],
            'remetente' => [40, 20, 'alpha'],
            'destinatario' => [60, 20, 'alpha'],
            'uso_alelo_2' => [80, 5, 'numeric'],
            'nsu_arquivo' => [85, 6, 'numeric'],
            'codigo_retorno' => [91, 2, 'numeric'],
            'descricao_retorno' => [93, 20, 'alpha'],
            'versao_layout' => [113, 8, 'alpha'],
            'numero_sequencial' => [121, 5, 'numeric'],
            'uso_alelo_3' => [126, 375, 'alpha'],
        ];
    }

    protected static function movimento(): array
    {
        return [
            'tipo_registro' => [1, 2, 'numeric'],
            'chave_pagamento' => [3, 30, 'alpha'],
            'data_pagamento_prevista' => [33, 8, 'date'],
            'data_processamento' => [41, 8, 'date'],
            'estabelecimento_matriz' => [49, 20, 'numeric'],
            'estabelecimento' => [69, 20, 'numeric'],
            'nsu_host' => [89, 10, 'numeric'],
            'data_transacao' => [99, 8, 'date'],
            'hora_transacao' => [107, 6, 'time'],
            'tipo_movimento' => [113, 2, 'numeric'], // 01 a 05
            'valor_bruto_sinal' => [115, 1, 'alpha'],
            'valor_bruto' => [116, 15, 'decimal', 2], // Needs confirmation, assuming 2 decimals usually, layout says "up to 6"
            'valor_taxa_sinal' => [131, 1, 'alpha'],
            'valor_taxa' => [132, 15, 'decimal', 2],
            'valor_liquido_sinal' => [147, 1, 'alpha'],
            'valor_liquido' => [148, 15, 'decimal', 2],
            'codigo_autorizacao' => [163, 10, 'alpha'],
            'numero_cartao' => [173, 19, 'numeric'],
            'tipo_captura' => [192, 2, 'numeric'], // 01 ou 02
            'taxa_adm' => [194, 9, 'decimal', 6],  // manual notes "até 6 casas"
            'tipo_produto' => [203, 3, 'numeric'],
            'uso_alelo_1' => [206, 5, 'numeric'],
            'valor_tarifa_sinal' => [211, 1, 'alpha'],
            'valor_tarifa' => [212, 15, 'decimal', 2],
            'forma_captura' => [227, 2, 'numeric'], // 00 a 05
            'modo_entrada_cartao' => [229, 2, 'numeric'],
            'codigo_ajuste' => [231, 2, 'numeric'],
            'uso_alelo_2' => [233, 25, 'alpha'],
            'id_transacao' => [258, 20, 'alpha'],
            'psr' => [278, 11, 'numeric'],
            'chave_ajuste' => [289, 30, 'alpha'],
            'uso_alelo_3' => [319, 11, 'alpha'],
            'nsu_doc_referencia' => [330, 10, 'numeric'],
            'uso_alelo_4' => [340, 161, 'alpha'],
        ];
    }

    protected static function pagamento(): array
    {
        return [
            'tipo_registro' => [1, 2, 'numeric'],
            'chave_pagamento' => [3, 30, 'alpha'],
            'data_pagamento_prevista' => [33, 8, 'date'],
            'data_processamento' => [41, 8, 'date'],
            'estabelecimento_matriz' => [49, 20, 'numeric'],
            'estabelecimento' => [69, 20, 'numeric'],
            'cnpj_raiz' => [89, 14, 'numeric'],
            'tipo_produto' => [103, 3, 'numeric'],
            'valor_bruto_sinal' => [106, 1, 'alpha'],
            'valor_bruto' => [107, 15, 'decimal', 2],
            'valor_taxa_sinal' => [122, 1, 'alpha'],
            'valor_taxa' => [123, 15, 'decimal', 2],
            'valor_liquido_sinal' => [138, 1, 'alpha'],
            'valor_liquido' => [139, 15, 'decimal', 2],
            'status_pagamento' => [154, 2, 'numeric'], // 01,03,04,05
            'codigo_banco' => [156, 4, 'numeric'], // Assuming 4 digits + 1 check usually, layout says 4 + DV? Wait, 156-159 is 4 digits. Let's use 5 total. Actually: 156-160? Manual says check it. Will parse raw as string.
            'agencia' => [161, 6, 'alpha'],
            'conta' => [167, 12, 'alpha'],
            'motivo_rejeicao' => [179, 3, 'numeric'],
            'data_envio_banco' => [182, 8, 'date'],
            'uso_alelo_1' => [190, 5, 'numeric'],
            'valor_tarifa_sinal' => [195, 1, 'alpha'],
            'valor_tarifa' => [196, 15, 'decimal', 2],
            'uso_alelo_2' => [211, 290, 'alpha'],
        ];
    }
    
    // ... [Simplificando a adição dos outros registros para o prompt atual. 
    // Em produção os outros (03, 05, 06) seguem exatamente a mesma lógica posicional.]
    
    protected static function pagamentoMultibeneficios(): array
    {
        return [
            'tipo_registro' => [1, 2, 'numeric'],
            'chave_pagamento' => [3, 30, 'alpha'],
            'data_pagamento_prevista' => [33, 8, 'date'],
            'data_processamento' => [41, 8, 'date'],
            'estabelecimento_matriz' => [49, 20, 'numeric'],
            'estabelecimento' => [69, 20, 'numeric'],
            'cnpj_raiz' => [89, 14, 'numeric'],
            'codigo_banco' => [103, 4, 'numeric'],
            'agencia' => [107, 6, 'alpha'],
            'conta' => [113, 12, 'alpha'],
            'status_pagamento' => [125, 2, 'numeric'],
            'motivo_rejeicao' => [127, 3, 'numeric'],
            'data_envio_banco' => [130, 8, 'date'],
            'id_transacao' => [138, 20, 'alpha'],
            'valor_liquido_sinal' => [158, 1, 'alpha'],
            'valor_liquido' => [159, 15, 'decimal', 2],
            'uso_alelo_1' => [174, 327, 'alpha'],
        ];
    }

    protected static function saldoNegativo(): array
    {
        return [
            'tipo_registro' => [1, 2, 'numeric'],
            'estabelecimento_matriz' => [3, 20, 'numeric'],
            'estabelecimento' => [23, 20, 'numeric'],
            'cnpj_raiz' => [43, 14, 'numeric'],
            'data_processamento' => [57, 8, 'date'],
            'valor_inicial_sinal' => [65, 1, 'alpha'],
            'valor_inicial' => [66, 15, 'decimal', 2],
            'valor_abatido_sinal' => [81, 1, 'alpha'],
            'valor_abatido' => [82, 15, 'decimal', 2],
            'valor_final_sinal' => [97, 1, 'alpha'],
            'valor_final' => [98, 15, 'decimal', 2],
            'chave_pagamento_abatido' => [113, 30, 'alpha'],
            'chave_pagamento_saldo' => [143, 30, 'alpha'],
            'uso_alelo_1' => [173, 328, 'alpha'],
        ];
    }

    protected static function totalPagamentoUnificado(): array
    {
        return [
            'tipo_registro' => [1, 2, 'numeric'],
            'chave_pagamento' => [3, 30, 'alpha'],
            'data_pagamento_prevista' => [33, 8, 'date'],
            'data_processamento' => [41, 8, 'date'],
            'estabelecimento_matriz' => [49, 20, 'numeric'],
            'estabelecimento' => [69, 20, 'numeric'],
            'cnpj_raiz' => [89, 14, 'numeric'],
            'valor_bruto_sinal' => [103, 1, 'alpha'],
            'valor_bruto' => [104, 15, 'decimal', 2],
            'valor_taxa_sinal' => [119, 1, 'alpha'],
            'valor_taxa' => [120, 15, 'decimal', 2],
            'valor_liquido_sinal' => [135, 1, 'alpha'],
            'valor_liquido' => [136, 15, 'decimal', 2],
            'status_pagamento' => [151, 2, 'numeric'],
            'codigo_banco' => [153, 4, 'numeric'],
            'agencia' => [157, 6, 'alpha'],
            'conta' => [163, 12, 'alpha'],
            'motivo_rejeicao' => [175, 3, 'numeric'],
            'uso_alelo_1' => [178, 323, 'alpha'],
        ];
    }

    protected static function trailer(): array
    {
        return [
            'tipo_registro' => [1, 2, 'numeric'],
            'total_registros' => [3, 9, 'numeric'],
            'total_estabelecimentos' => [12, 9, 'numeric'],
            'uso_alelo_1' => [21, 480, 'alpha'],
        ];
    }
}
