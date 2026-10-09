<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('processing_errors', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('ingestion_id')->constrained('ingestions')->cascadeOnDelete();
            $table->foreignId('processing_execution_id')->constrained('processing_executions')->cascadeOnDelete();
            $table->unsignedInteger('line_number')->nullable();
            $table->string('record_type', 10)->nullable();
            $table->string('field_name', 100)->nullable();
            $table->string('error_code', 50);
            $table->text('message');
            $table->string('severity', 20)->default('error');  // error | warning | info
            $table->string('sanitized_value', 500)->nullable();
            $table->timestamps();

            $table->unique('uuid');
            $table->index(['ingestion_id', 'severity']);
            $table->index('processing_execution_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processing_errors');
    }
};
