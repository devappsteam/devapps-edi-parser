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
            'estabelecimento_matriz' => [33, 15, 'numeric'],
            'estabelecimento' => [48, 15, 'numeric'],
            'cnpj_raiz' => [63, 8, 'numeric'],
            'tipo_movimento' => [71, 3, 'numeric'],
            'origem_ajuste' => [74, 6, 'numeric'],
            'id_compensacao' => [80, 15, 'alpha'],
            'data_processamento' => [95, 8, 'date'],
            'data_pagamento_prevista' => [103, 8, 'date'], // Data prevista de processamento
            'data_transacao' => [111, 8, 'date'],
            'hora_transacao' => [119, 6, 'time'], // Hora de captura
            'data_envio_banco' => [125, 8, 'date'], // Data prevista de pagamento
            'valor_bruto_sinal' => [133, 1, 'alpha'],
            'valor_bruto' => [134, 13, 'decimal', 2],
            'valor_liquido_sinal' => [147, 1, 'alpha'],
            'valor_liquido' => [148, 13, 'decimal', 2],
            'valor_taxa_sinal' => [161, 1, 'alpha'],
            'valor_taxa' => [162, 13, 'decimal', 2],
            'taxa_adm' => [175, 13, 'decimal', 6],
            'valor_tarifa' => [188, 13, 'decimal', 2],
            'tipo_produto' => [201, 4, 'numeric'],
            'tipo_captura' => [205, 2, 'numeric'],
            'meio_captura' => [207, 2, 'numeric'],
            'bandeira' => [209, 3, 'numeric'],
            'modo_entrada_cartao' => [212, 3, 'numeric'],
            'numero_cartao' => [215, 19, 'numeric'],
            'codigo_autorizacao' => [242, 8, 'alpha'],
            'nsu_host' => [250, 6, 'numeric'], // NSU/DOC
            'codigo_banco' => [256, 4, 'numeric'],
            'agencia' => [260, 5, 'alpha'],
            'conta' => [265, 14, 'alpha'],
            'id_transacao' => [279, 18, 'alpha'], // Identificador unico
            'psr' => [297, 3, 'numeric'],
        ];
    }

    protected static function pagamento(): array
    {
        return [
            'tipo_registro' => [1, 2, 'numeric'],
            'estabelecimento' => [3, 15, 'numeric'],
            'cnpj_raiz' => [18, 8, 'numeric'],
            'estabelecimento_matriz' => [26, 15, 'numeric'],
            'chave_pagamento' => [41, 30, 'alpha'],
            'status_pagamento' => [72, 3, 'numeric'],
            'motivo_rejeicao' => [75, 5, 'numeric'],
            'data_envio_banco' => [97, 8, 'date'],
            'valor_liquido_sinal' => [105, 1, 'alpha'], // Mapping payment amount to net_amount
            'valor_liquido' => [106, 18, 'decimal', 2],
            'codigo_banco' => [170, 4, 'numeric'],
            'agencia' => [174, 5, 'alpha'],
            'conta' => [179, 14, 'alpha'],
            'id_transacao' => [217, 12, 'alpha'],
        ];
    }
    
    protected static function pagamentoMultibeneficios(): array
    {
        return [
            'tipo_registro' => [1, 2, 'numeric'],
            'chave_pagamento' => [3, 30, 'alpha'],
            'estabelecimento' => [63, 15, 'numeric'],
            'cnpj_raiz' => [78, 8, 'numeric'],
            'status_pagamento' => [88, 3, 'numeric'],
            'motivo_rejeicao' => [91, 5, 'numeric'],
            'data_envio_banco' => [96, 8, 'date'], // Data de pagamento
            'valor_liquido_sinal' => [104, 1, 'alpha'],
            'valor_liquido' => [105, 18, 'decimal', 2],
            'codigo_banco' => [223, 4, 'numeric'],
            'agencia' => [227, 5, 'alpha'],
            'conta' => [232, 14, 'alpha'],
            'id_transacao' => [246, 12, 'alpha'],
        ];
    }

    protected static function saldoNegativo(): array
    {
        return [
            'tipo_registro' => [1, 2, 'numeric'],
            'estabelecimento' => [48, 15, 'numeric'],
            'cnpj_raiz' => [63, 8, 'numeric'],
            'tipo_movimento' => [71, 3, 'numeric'],
            'data_processamento' => [95, 8, 'date'],
            'valor_inicial_sinal' => [133, 1, 'alpha'], // Saldo dia atual
            'valor_inicial' => [134, 13, 'decimal', 2],
            'valor_abatido_sinal' => [147, 1, 'alpha'], // Abatimento dia atual
            'valor_abatido' => [148, 13, 'decimal', 2],
            'valor_final_sinal' => [161, 1, 'alpha'], // Saldo anterior
            'valor_final' => [162, 13, 'decimal', 2],
            'tipo_produto' => [201, 4, 'numeric'],
        ];
    }

    protected static function totalPagamentoUnificado(): array
    {
        return [
            'tipo_registro' => [1, 2, 'numeric'],
            'cnpj_raiz' => [18, 8, 'numeric'],
            'status_pagamento' => [72, 3, 'numeric'],
            'motivo_rejeicao' => [75, 5, 'numeric'],
            'data_envio_banco' => [97, 8, 'date'],
            'valor_liquido_sinal' => [105, 1, 'alpha'],
            'valor_liquido' => [106, 18, 'decimal', 2],
            'codigo_banco' => [170, 4, 'numeric'],
            'agencia' => [174, 5, 'alpha'],
            'conta' => [179, 14, 'alpha'],
            'id_transacao' => [217, 12, 'alpha'],
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
