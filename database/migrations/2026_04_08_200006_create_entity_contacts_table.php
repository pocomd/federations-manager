<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('entity_id')
                  ->constrained()->cascadeOnDelete();
            $table->enum('type', [
                'technical', 'support', 'security', 'administrative', 'billing', 'other',
            ]);
            $table->string('given_name', 255)->nullable();
            $table->string('sur_name', 255)->nullable();
            $table->string('email', 255);
            $table->string('phone', 50)->nullable();
            $table->timestamps();
            $table->index(['entity_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_contacts');
    }
};
