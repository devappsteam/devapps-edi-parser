<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('processing_executions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('ingestion_id')->constrained('ingestions')->cascadeOnDelete();
            $table->string('status', 30)->default('processing');
            $table->unsignedInteger('attempt_number')->default(1);
            $table->unsignedInteger('records_read')->default(0);
            $table->unsignedInteger('records_processed')->default(0);
            $table->unsignedInteger('records_rejected')->default(0);
            $table->unsignedInteger('warnings_count')->default(0);
            $table->json('header_data')->nullable();
            $table->unsignedInteger('trailer_total_records')->nullable();
            $table->unsignedInteger('trailer_total_branches')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('processing_time_ms')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique('uuid');
            $table->index('status');
            $table->index(['ingestion_id', 'attempt_number']);
        });

        // Now add the FK on ingestions for current_execution_id
        Schema::table('ingestions', function (Blueprint $table) {
            $table->foreign('current_execution_id')
                  ->references('id')
                  ->on('processing_executions')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ingestions', function (Blueprint $table) {
            $table->dropForeign(['current_execution_id']);
        });
        Schema::dropIfExists('processing_executions');
    }
};
