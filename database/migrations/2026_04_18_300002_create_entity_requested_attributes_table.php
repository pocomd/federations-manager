<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_requested_attributes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('entity_id')
                  ->constrained()->cascadeOnDelete();
            $table->foreignUuid('attribute_definition_id')
                  ->constrained('attribute_definitions')->cascadeOnDelete();
            $table->boolean('is_required')->default(false);
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->unique(
                ['entity_id', 'attribute_definition_id'],
                'era_entity_attr_unique'
            );
            $table->index('entity_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_requested_attributes');
    }
};
