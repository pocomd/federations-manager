<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('federation_contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('federation_id')
                  ->constrained()->cascadeOnDelete();
            $table->enum('type', [
                'technical', 'administrative', 'security', 'support',
            ]);
            $table->string('given_name', 255)->nullable();
            $table->string('sur_name', 255)->nullable();
            $table->string('email', 255);
            $table->string('phone', 50)->nullable();
            $table->timestamps();
            $table->index(['federation_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('federation_contacts');
    }
};
