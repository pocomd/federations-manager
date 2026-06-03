<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('federation_registration_policies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('federation_id')
                  ->constrained()->cascadeOnDelete();
            $table->string('display_name', 255);
            $table->string('lang', 10)->default('en');
            $table->text('url');
            $table->text('description')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['federation_id', 'lang'], 'frp_federation_lang_unique');
            $table->index(['federation_id', 'enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('federation_registration_policies');
    }
};
