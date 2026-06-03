<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_endpoints', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('entity_id')
                  ->constrained()->cascadeOnDelete();
            $table->enum('type', ['sso', 'acs', 'slo', 'artifact']);
            $table->string('binding', 255);
            $table->text('location');
            $table->text('response_location')->nullable();
            $table->unsignedSmallInteger('index')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->index(['entity_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_endpoints');
    }
};
