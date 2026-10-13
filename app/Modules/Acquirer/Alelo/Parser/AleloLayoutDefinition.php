<?php

namespace App\Modules\Acquirer\Alelo\Parser;

class AleloLayoutDefinition
{
    /**
     * Map of record types to their field definitions.
     * Definitions follow the [start (1-based), length, type, default_decimals] format.
     *
     * Source: Manual Técnico EDI Alelo — Layout v2.1_3 — vigência 12/02/2026.
     *
     * Convention (per manual OBS section):
     *   - Numeric ("Num") fields: padded with '0' on the left.
     *   - Alpha ("Alfa") fields: padded with ' ' (space) on the left.
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

    /**
     * Registro 00 – Header
     * Manual §3.1, p.4 — Layout v2.1_3 (revisão 09/04/2025 + 02/06/2025)
     */
    protected static function header(): array
    {
        return [
            // name => [start, length, type, decimals]
            'tipo_registro'         => [1,   2,   'numeric'],
            'id_agrupamento'        => [3,   15,  'numeric'],
            'data_processamento'    => [18,  8,   'date'],
            'periodo_inicial'       => [26,  8,   'date'],
            'periodo_final'         => [34,  8,   'date'],
            'tipo_geracao_arquivo'  => [42,  1,   'alpha'],
            'sequencia'             => [43,  7,   'numeric'],
            'empresa_adquirente'    => [50,  5,   'alpha'],
            'tipo_extrato'          => [55,  2,   'alpha'],
            'caixa_postal'          => [57,  50,  'alpha'],
            'versao_layout'         => [107, 5,   'alpha'],
            'cnpj'                  => [112, 14,  'alpha'],
            'uso_alelo'             => [126, 375, 'alpha'],
        ];
    }

    /**
     * Registro 01 – Movimento
     * Manual §3.2, pp.5–6 — Layout v2.1_3 (revisão 30/10/2024)
     *
     * Date fields (positions confirmed against real file):
     *   95-102  data_processamento       = Data do Processamento
     *   103-110 data_prev_processamento  = Data Prevista de processamento → expected_processing_date
     *   111-118 data_transacao           = Data da transação de venda e captura → transaction_date
     *   119-124 hora_transacao           = Hora da captura (HH24MISS)
     *   125-132 data_prev_pagamento      = Data prevista de pagamento → expected_payment_date
     *
     * Financial fields: all monetary values use 6 implicit decimal places.
     */
    protected static function movimento(): array
    {
        return [
            'tipo_registro'             => [1,   2,   'numeric'],
            'chave_pagamento'           => [3,   30,  'alpha'],
            'estabelecimento_matriz'    => [33,  15,  'numeric'],
            'estabelecimento'           => [48,  15,  'numeric'],
            'cnpj_raiz'                 => [63,  8,   'alpha'],
            'tipo_movimento'            => [71,  3,   'alpha'],
            'origem_ajuste'             => [74,  6,   'alpha'],
            'id_compensacao'            => [80,  15,  'numeric'],
            'data_processamento'        => [95,  8,   'date'],
            'data_prev_processamento'   => [103, 8,   'date'],
            'data_transacao'            => [111, 8,   'date'],
            'hora_transacao'            => [119, 6,   'time'],
            'data_prev_pagamento'       => [125, 8,   'date'],
            'valor_bruto_sinal'         => [133, 1,   'alpha'],
            'valor_bruto'               => [134, 13,  'decimal', 6],
            'valor_liquido_sinal'       => [147, 1,   'alpha'],
            'valor_liquido'             => [148, 13,  'decimal', 6],
            'valor_taxa_sinal'          => [161, 1,   'alpha'],
            'valor_taxa'                => [162, 13,  'decimal', 6],
            'pct_taxa_adm'              => [175, 13,  'decimal', 6],
            'valor_tarifa'              => [188, 13,  'decimal', 6],
            'tipo_produto'              => [201, 4,   'alpha'],
            'tipo_captura'              => [205, 2,   'alpha'],
            'meio_captura'              => [207, 2,   'alpha'],
            'bandeira'                  => [209, 3,   'numeric'],
            'modo_entrada_cartao'       => [212, 3,   'alpha'],
            'numero_cartao'             => [215, 19,  'alpha'],
            'numero_terminal'           => [234, 8,   'alpha'],
            'codigo_autorizacao'        => [242, 8,   'alpha'],
            'nsu_doc'                   => [250, 6,   'alpha'],
            'codigo_banco'              => [256, 4,   'alpha'],
            'agencia'                   => [260, 5,   'alpha'],
            'conta'                     => [265, 14,  'alpha'],
            'id_movimento'              => [279, 18,  'numeric'],
            'psr'                       => [297, 3,   'alpha'],
            'uso_alelo'                 => [300, 201, 'alpha'],
        ];
    }

    /**
     * Registro 02 – Pagamento
     * Manual §3.3, pp.6–7 — Layout v2.1_3
     */
    protected static function pagamento(): array
    {
        return [
            'tipo_registro'             => [1,   2,   'numeric'],
            'estabelecimento'           => [3,   15,  'numeric'],
            'cnpj_raiz'                 => [18,  8,   'alpha'],
            'estabelecimento_matriz'    => [26,  15,  'alpha'],
            'chave_pagamento'           => [41,  30,  'alpha'],
            'tipo_pagamento'            => [71,  1,   'alpha'],
            'status_pagamento'          => [72,  3,   'numeric'],
            'motivo_rejeicao'           => [75,  5,   'alpha'],
            'reenvio_pagamento'         => [80,  1,   'alpha'],
            'data_pagamento_original'   => [81,  8,   'date'],
            'data_pagamento'            => [89,  8,   'date'],
            'data_envio_banco'          => [97,  8,   'date'],
            'valor_pagamento_sinal'     => [105, 1,   'alpha'],
            'valor_pagamento'           => [106, 18,  'decimal', 6],
            'num_operacao_antecipacao'  => [124, 9,   'numeric'],
            'valor_liquido_arv_sinal'   => [133, 1,   'alpha'],
            'valor_liquido_arv'         => [134, 18,  'decimal', 6],
            'desconto_arv'              => [152, 18,  'decimal', 6],
            'codigo_banco'              => [170, 4,   'alpha'],
            'agencia'                   => [174, 5,   'alpha'],
            'conta'                     => [179, 14,  'alpha'],
            'tipo_operacao_arv'         => [193, 4,   'numeric'],
            'id_pagamento_arv'          => [197, 12,  'numeric'],
            'data_atualizacao_status'   => [209, 8,   'date'],
            'id_pagamento_unificado'    => [217, 12,  'numeric'],
            'uso_alelo'                 => [229, 272, 'alpha'],
        ];
    }

    /**
     * Registro 03 – Pagamento – Multibenefícios
     * Manual §3.4, p.8 — Layout v2.1_3
     */
    protected static function pagamentoMultibeneficios(): array
    {
        return [
            'tipo_registro'             => [1,   2,   'numeric'],
            'chave_pagamento'           => [3,   30,  'alpha'],
            'id_ip'                     => [33,  30,  'alpha'],
            'estabelecimento'           => [63,  15,  'numeric'],
            'cnpj_raiz'                 => [78,  8,   'alpha'],
            'tipo_pagamento'            => [86,  2,   'alpha'],
            'status_pagamento'          => [88,  3,   'numeric'],
            'motivo_rejeicao'           => [91,  5,   'alpha'],
            'data_pagamento'            => [96,  8,   'date'],
            'valor_pagamento_sinal'     => [104, 1,   'alpha'],
            'valor_pagamento'           => [105, 18,  'decimal', 6],
            'unidade_recebivel'         => [123, 50,  'alpha'],
            'numero_contrato'           => [173, 50,  'alpha'],
            'codigo_banco'              => [223, 4,   'alpha'],
            'agencia'                   => [227, 5,   'alpha'],
            'conta'                     => [232, 14,  'alpha'],
            'id_pagamento_unificado'    => [246, 12,  'numeric'],
            'uso_alelo'                 => [258, 243, 'alpha'],
        ];
    }

    /**
     * Registro 05 – Debit Balance (Saldo Negativo)
     * Manual §3.6, p.9 — Layout v2.1_3 (adicionado em 21/10/2024)
     *
     * IMPORTANT: Fields below have their own semantics — do NOT interpret as
     * gross_amount/net_amount/fee_amount (those belong to Registro 01 – Movimento).
     *
     *   saldo_atual    = Saldo Negativo do dia Atual
     *   abatimento     = Abatimento de Saldo Negativo do dia
     *   saldo_anterior = Saldo Negativo do dia anterior
     *   variacao_saldo = Tabela XIV: '01'=Aumentou, '02'=Diminuiu, '03'=Manteve
     */
    protected static function saldoNegativo(): array
    {
        return [
            'tipo_registro'              => [1,   2,   'numeric'],
            'uso_alelo_1'                => [3,   45,  'numeric'],
            'estabelecimento'            => [48,  15,  'numeric'],
            'cnpj_raiz'                  => [63,  8,   'alpha'],
            'tipo_movimento'             => [71,  3,   'numeric'],
            'uso_alelo_2'                => [74,  21,  'numeric'],
            'data_processamento'         => [95,  8,   'date'],
            'uso_alelo_3'                => [103, 22,  'numeric'],
            'data_inicio_saldo_negativo' => [125, 8,   'date'],
            'saldo_atual_sinal'          => [133, 1,   'alpha'],
            'saldo_atual'                => [134, 13,  'decimal', 6],
            'abatimento_sinal'           => [147, 1,   'alpha'],
            'abatimento'                 => [148, 13,  'decimal', 6],
            'saldo_anterior_sinal'       => [161, 1,   'alpha'],
            'saldo_anterior'             => [162, 13,  'decimal', 6],
            'variacao_saldo'             => [175, 13,  'alpha'],
            'uso_alelo_4'                => [188, 13,  'numeric'],
            'tipo_produto'               => [201, 4,   'numeric'],
            'uso_alelo_5'                => [205, 296, 'numeric'],
        ];
    }

    /**
     * Registro 06 – Total Pagamento Unificado
     * Manual §3.7, p.10 — Layout v2.1_3 (adicionado em 21/10/2024)
     */
    protected static function totalPagamentoUnificado(): array
    {
        return [
            'tipo_registro'             => [1,   2,   'numeric'],
            'uso_alelo_1'               => [3,   15,  'numeric'],
            'cnpj_raiz'                 => [18,  8,   'alpha'],
            'uso_alelo_2'               => [26,  45,  'alpha'],
            'tipo_pagamento'            => [71,  1,   'alpha'],
            'status_pagamento'          => [72,  3,   'numeric'],
            'motivo_rejeicao'           => [75,  5,   'alpha'],
            'reenvio_pagamento'         => [80,  1,   'alpha'],
            'data_pagamento_original'   => [81,  8,   'date'],
            'data_pagamento'            => [89,  8,   'date'],
            'data_envio_banco'          => [97,  8,   'date'],
            'valor_pagamento_sinal'     => [105, 1,   'alpha'],
            'valor_pagamento'           => [106, 18,  'decimal', 6],
            'uso_alelo_3'               => [124, 46,  'numeric'],
            'codigo_banco'              => [170, 4,   'alpha'],
            'agencia'                   => [174, 5,   'alpha'],
            'conta'                     => [179, 14,  'alpha'],
            'uso_alelo_4'               => [193, 16,  'numeric'],
            'data_atualizacao_status'   => [209, 8,   'date'],
            'id_pagamento_unificado'    => [217, 12,  'numeric'],
            'uso_alelo_5'               => [229, 272, 'numeric'],
        ];
    }

    /**
     * Registro 99 – Trailer
     * Manual §3.5, p.9 — Layout v2.1_3
     *
     * | Início | Fim  | Tam | Tipo | Campo             |
     * |--------|------|-----|------|-------------------|
     * |      1 |    2 |   2 | Alfa | Tipo de registro  |
     * |      3 |   13 |  11 | Num  | Total de registro |
     * |     14 |   19 |   6 | Num  | Total de filiais  |
     * |     20 |  500 | 481 | Num  | Uso Alelo         |
     */
    protected static function trailer(): array
    {
        return [
            'tipo_registro'         => [1,  2,   'numeric'],
            'total_registros'       => [3,  11,  'numeric'],
            'total_filiais'         => [14, 6,   'numeric'],
            'uso_alelo'             => [20, 481, 'alpha'],
        ];
    }
}
