<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_certificates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('entity_id');
            $table->enum('use', ['signing', 'encryption', 'both']);
            $table->text('pem');
            $table->string('subject', 512);
            $table->string('issuer', 512);
            $table->string('serial', 128);
            $table->timestamp('not_before')->nullable();
            $table->timestamp('not_after')->nullable();
            $table->smallInteger('key_bits');
            $table->string('key_algorithm', 50);
            $table->string('fingerprint', 128);
            $table->string('signature_algorithm', 100);
            $table->boolean('debian_weak')->default(false);
            $table->timestamps();

            $table->foreign('entity_id')
                ->references('id')
                ->on('entities')
                ->cascadeOnDelete();

            // Indexes
            $table->index('entity_id');
            $table->index('not_after');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_certificates');
    }
};
