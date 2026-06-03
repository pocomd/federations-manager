<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_arp', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('idp_entity_id')
                  ->constrained('entities', 'id')->cascadeOnDelete();
            $table->foreignUuid('sp_entity_id')
                  ->constrained('entities', 'id')->cascadeOnDelete();
            $table->foreignUuid('attribute_definition_id')
                  ->constrained('attribute_definitions')->cascadeOnDelete();
            $table->boolean('is_permitted')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(
                ['idp_entity_id', 'sp_entity_id', 'attribute_definition_id'],
                'arp_idp_sp_attr_unique'
            );
            $table->index(['idp_entity_id', 'sp_entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_arp');
    }
};
