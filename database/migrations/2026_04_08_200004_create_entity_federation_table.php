<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_federation', function (Blueprint $table) {
            $table->uuid('entity_id');
            $table->uuid('federation_id');
            $table->enum('status', ['pending', 'active', 'rejected', 'suspended']);
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->primary(['entity_id', 'federation_id']);

            $table->foreign('entity_id')
                ->references('id')
                ->on('entities')
                ->cascadeOnDelete();

            $table->foreign('federation_id')
                ->references('id')
                ->on('federations')
                ->cascadeOnDelete();

            $table->foreign('approved_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_federation');
    }
};
