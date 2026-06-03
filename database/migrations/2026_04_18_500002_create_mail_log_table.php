<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_log', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('federation_id')
                  ->nullable()
                  ->constrained()->nullOnDelete();
            $table->foreignUuid('entity_id')
                  ->nullable()
                  ->constrained()->nullOnDelete();
            $table->foreignUuid('sent_by')
                  ->nullable()
                  ->constrained('users')->nullOnDelete();
            $table->string('to_email', 255);
            $table->string('to_name', 255)->nullable();
            $table->string('contact_type', 50)->nullable();
            $table->string('subject', 255);
            $table->text('body');
            $table->enum('status', ['sent', 'failed', 'pending'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['federation_id', 'created_at']);
            $table->index(['entity_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_log');
    }
};
