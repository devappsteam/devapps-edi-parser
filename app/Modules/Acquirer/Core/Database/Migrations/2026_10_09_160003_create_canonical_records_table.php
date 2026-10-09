<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('canonical_records', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('ingestion_id')->constrained('ingestions')->cascadeOnDelete();
            $table->foreignId('processing_execution_id')->constrained('processing_executions')->cascadeOnDelete();
            $table->string('acquirer_code', 20);
            $table->string('source_record_type', 10);
            $table->unsignedInteger('source_line_number');

            // ── Key identifiers ──
            $table->string('payment_key', 50)->nullable();
            $table->string('unique_movement_id', 30)->nullable();
            $table->string('unified_payment_id', 20)->nullable();
            $table->string('compensation_id', 20)->nullable();
            $table->string('authorization_code', 10)->nullable();
            $table->string('nsu_doc', 10)->nullable();

            // ── Dates ──
            $table->date('transaction_date')->nullable();
            $table->string('capture_time', 6)->nullable();   // HH24MISS
            $table->date('processing_date')->nullable();
            $table->date('expected_processing_date')->nullable();
            $table->date('expected_payment_date')->nullable();
            $table->date('payment_date')->nullable();
            $table->date('original_payment_date')->nullable();
            $table->date('bank_sent_date')->nullable();
            $table->date('status_update_date')->nullable();

            // ── Classification ──
            $table->string('movement_type_code', 5)->nullable();
            $table->string('product_code', 5)->nullable();
            $table->string('brand_code', 5)->nullable();

            // ── Establishment ──
            $table->string('establishment_id', 20)->nullable();
            $table->string('branch_id', 20)->nullable();
            $table->string('payment_matrix_id', 20)->nullable();
            $table->string('cnpj_root', 14)->nullable();

            // ── Financial (DECIMAL 18,6 — safe for up to 6 decimal places per Alelo manual) ──
            $table->decimal('gross_amount', 18, 6)->nullable();
            $table->decimal('net_amount', 18, 6)->nullable();
            $table->decimal('fee_amount', 18, 6)->nullable();
            $table->decimal('fee_rate', 18, 6)->nullable();
            $table->decimal('tariff_amount', 18, 6)->nullable();
            $table->decimal('payment_amount', 18, 6)->nullable();

            // ── Payment ──
            $table->string('payment_type', 5)->nullable();
            $table->string('payment_status_code', 5)->nullable();
            $table->string('rejection_code', 10)->nullable();
            $table->boolean('is_resend')->default(false);

            // ── Banking ──
            $table->string('bank_code', 5)->nullable();
            $table->string('agency_code', 10)->nullable();
            $table->string('account_number', 20)->nullable();

            // ── Extended acquirer-specific data (JSON) ──
            $table->json('metadata')->nullable();

            // ── Audit ──
            $table->text('raw_data')->nullable();

            $table->timestamps();

            $table->unique('uuid');
            $table->index('payment_key');
            $table->index('unified_payment_id');
            $table->index('transaction_date');
            $table->index('payment_date');
            $table->index('movement_type_code');
            $table->index('product_code');
            $table->index('establishment_id');
            $table->index('cnpj_root');
            $table->index(['acquirer_code', 'source_record_type']);

            // Prevents duplicate records within the same processing execution
            $table->unique(
                ['processing_execution_id', 'source_line_number'],
                'canonical_records_execution_line_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canonical_records');
    }
};
