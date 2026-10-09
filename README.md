# 💳 DevApps - EDI Parser (Acquirer Reconciliation ETL)

Microserviço escalável focado em Ingestão, Extração, Transformação e Carga (ETL) de arquivos EDI de Adquirentes para o processo de conciliação financeira, integrado de forma assíncrona com n8n e ERP.

## 🚀 Arquitetura e Tecnologias

- **Framework:** Laravel 12 / PHP 8.3+
- **Database:** SQLite (dev) com abstrações limpas prontas para migração a SGBDs maiores.
- **Módulos:** Sistema segmentado utilizando `devapps/laravel-modules-kit`.
- **Idempotência:** Arquivos são submetidos a hash (SHA-256) com streams, bloqueando duplicidades lógicas independentemente do nome do arquivo.
- **Processamento em Stream (Assíncrono):** Os arquivos nunca são carregados em memória integralmente. O pipeline lê linha a linha (`yield`/Generator) de forma assíncrona garantindo *footprint* nulo em RAM mesmo com gigabytes.
- **Fidelidade Financeira:** Uso de colunas `decimal(18,6)` em substituição a `float` para prevenção de falhas matemáticas de ponto flutuante durante transações e resumos financeiros.

---

## 📂 Estrutura de Módulos
O código fonte reside em `app/Modules`. 
- **`Acquirer\Core`**: Núcleo agnóstico do microsserviço. Gerencia os modelos de `Ingestion`, transações, enfileiramento (Jobs), Rest API, segurança, validação de arquivos e as definições do `CanonicalRecord`.
- **`Acquirer\Alelo`**: Módulo isolado de implementação (Driver). Entende exclusivamente das posições e anomalias relatadas em `DOCs/Alelo.md`, mapeando-as em seu respectivo `AleloParser` de largura fixa (500 chars).

---

## 📡 Integração: API (n8n & ERP)

### 1. Upload e Ingestão de Arquivo
Utilizado pelo sistema externo (como n8n pegando um arquivo de FTP) para iniciar o ciclo. A ingestão devolverá status `201` caso novo ou `200` caso bata com o hash idempotente de um arquivo previamente aceito.

**Endpoint**: `POST /api/v1/acquirer/ingestions`  
**Content-Type**: `multipart/form-data`

#### Parâmetros do Payload
| Parâmetro | Tipo | Exemplo | Descrição |
|---|---|---|---|
| `acquirer` | String | `ALELO` | Sigla da adquirente. |
| `file` | File | `alelo_2026.txt` | O arquivo em si (texto ou remessa). |
| `idempotency_key` | String | `N8N-1029` | (Opcional) ID de correlação do lado do ERP. |

#### Exemplo de Chamada:
```bash
curl -X POST http://localhost:8000/api/v1/acquirer/ingestions \
  -F "acquirer=ALELO" \
  -F "file=@/caminho/do/arquivo.txt"
```

#### Exemplo de Resposta:
```json
{
  "message": "File ingested successfully",
  "data": {
    "id": "5ef7bf88-f648-49f4-a9c8-868f88424fdd",
    "acquirer_code": "ALELO",
    "status": "received",
    "idempotency_key": null,
    "received_at": "2026-10-09T19:54:00+00:00"
  }
}
```

---

### 2. Consultar Status do Processamento
Uma vez que o arquivo recebe *status="received"*, um Job de background processará linha por linha e preencherá os detalhes da extração.

**Endpoint**: `GET /api/v1/acquirer/ingestions/{uuid}`

#### Exemplo de Resposta:
```json
{
  "data": {
    "id": "5ef7bf88-f648-49f4-a9c8-868f88424fdd",
    "status": "completed",
    "current_execution": {
      "status": "completed",
      "records_read": 5,
      "records_processed": 3,
      "records_rejected": 0,
      "warnings_count": 0,
      "processing_time_ms": 12
    }
  }
}
```

> **Atenção:** Status válidos: `received`, `queued`, `processing`, `completed`, `completed_with_warnings`, `failed`, `rejected`.

---

### 3. Recuperar Dados Processados (Canonical Records)
Uma vez que o status acuse conclusão (completed), os dados financeiros extraídos, purificados de largura fixa e transladados para nosso layout canônico estarão acessíveis.

**Endpoint**: `GET /api/v1/acquirer/ingestions/{uuid}/records`

*(Opcional: utilize `?page=2` para paginação de grandes volumes)*

#### Exemplo de Resposta (Paginada):
```json
{
  "current_page": 1,
  "data": [
    {
      "id": 1,
      "uuid": "43e8ca79-f14d-4bc5-9c6a-4933a3f0535e",
      "acquirer_code": "ALELO",
      "source_record_type": "01",
      "source_line_number": 2,
      "transaction_date": "2026-10-09",
      "payment_key": "CHAVE_PGTO_123456",
      "gross_amount": "100.000000",
      "net_amount": "95.000000",
      "fee_amount": "-5.000000",
      "establishment_id": "99991234",
      "metadata": "{\"codigo_autorizacao\":\"AUTH01\"}"
    }
  ],
  "first_page_url": "http://localhost:8000/api/v1/acquirer/ingestions/5ef7bf.../records?page=1",
  "from": 1,
  "last_page": 1,
  "per_page": 50,
  "total": 3
}
```

---

## 🛠️ Como estender o projeto (Novas Adquirentes)

Para adicionar suporte à Cielo, Rede ou outras:
1. Crie uma pasta `app/Modules/Acquirer/{NovaAdquirente}`.
2. Crie uma classe que implemente a interface `App\Modules\Acquirer\Core\Contracts\AcquirerParserInterface`. Ela deve possuir a lógica do parser em `Generator` (`yield`).
3. Crie um `NovaAdquirenteServiceProvider` e registre o parser dentro do método `boot(ParserRegistry $registry)`.
4. Habilite a nova sigla no `.env` (ex: `ACQUIRER_SUPPORTED=ALELO,CIELO`).

---

## ⚙️ Ambiente de Desenvolvimento (Comandos úteis)
```bash
# Rodar Migrações e Atualizar Módulos
php artisan migrate
composer dump-autoload

# Limpar caches de módulos (laravel-modules-kit)
php artisan optimize:clear

# Processar as Filas assíncronas (Jobs)
php artisan queue:work

# Executar os testes unitários do Módulo da Alelo
./vendor/bin/phpunit tests/Unit/AleloParserTest.php
```
