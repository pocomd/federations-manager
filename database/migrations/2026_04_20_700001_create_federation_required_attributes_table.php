<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('federation_required_attributes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('federation_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('attribute_definition_id')
                  ->constrained('attribute_definitions')->cascadeOnDelete();
            $table->boolean('is_required')->default(true);
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->unique(['federation_id', 'attribute_definition_id'], 'fra_fed_attr_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('federation_required_attributes');
    }
};
