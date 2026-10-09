<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingestions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->string('acquirer_code', 20);
            $table->string('original_filename', 500);
            $table->string('storage_path', 1000);
            $table->string('storage_disk', 50)->default('local');
            $table->unsignedBigInteger('file_size_bytes');
            $table->string('file_extension', 20)->nullable();
            $table->string('content_hash', 64);        // SHA-256 hex digest
            $table->string('idempotency_key', 100)->nullable();
            $table->string('status', 30)->default('received');
            $table->unsignedBigInteger('current_execution_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->json('file_metadata')->nullable();
            $table->timestamp('received_at');
            $table->timestamps();
            $table->softDeletes();

            $table->unique('uuid');
            $table->index('acquirer_code');
            $table->index('content_hash');
            $table->index('status');
            $table->index('idempotency_key');

            // Prevents the same file content from being ingested twice for the same acquirer
            $table->unique(['acquirer_code', 'content_hash'], 'ingestions_acquirer_hash_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingestions');
    }
};
