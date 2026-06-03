<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_ui_info', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('entity_id')
                  ->constrained()->cascadeOnDelete();
            $table->enum('field', [
                'display_name',
                'description',
                'information_url',
                'privacy_url',
                'logo_url',
                'org_name',
                'org_display_name',
                'org_url',
            ]);
            $table->string('lang', 10)->default('en');
            $table->text('value');
            $table->smallInteger('logo_height')->nullable();
            $table->smallInteger('logo_width')->nullable();
            $table->timestamps();
            $table->unique(['entity_id', 'field', 'lang']);
            $table->index(['entity_id', 'field']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_ui_info');
    }
};
