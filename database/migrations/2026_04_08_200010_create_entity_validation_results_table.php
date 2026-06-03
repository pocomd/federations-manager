<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_validation_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('entity_id')
                  ->constrained()->cascadeOnDelete();
            $table->boolean('passed');
            $table->json('errors')->nullable()->default(null);
            $table->json('warnings')->nullable()->default(null);
            $table->json('checks')->nullable()->default(null);
            $table->string('triggered_by', 100)->nullable();
            $table->foreignUuid('triggered_by_user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['entity_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_validation_results');
    }
};
