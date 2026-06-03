<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_attributes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('entity_id')
                  ->constrained()->cascadeOnDelete();
            $table->enum('attribute_name', [
                'entity_category',
                'entity_category_support',
                'assurance_profile',
            ]);
            $table->string('attribute_value', 512);
            $table->timestamps();
            $table->unique(['entity_id', 'attribute_name', 'attribute_value'], 'ea_entity_name_value_unique');
            $table->index(['attribute_name', 'attribute_value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_attributes');
    }
};
