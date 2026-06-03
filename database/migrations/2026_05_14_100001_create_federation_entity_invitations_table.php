<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('federation_entity_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('federation_id')->constrained('federations')->cascadeOnDelete();
            $table->foreignUuid('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->foreignUuid('invited_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->string('status', 20)->default('pending'); // pending|accepted|rejected
            $table->foreignUuid('responded_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->timestamp('responded_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['entity_id', 'status']);
            $table->index(['federation_id', 'status']);
            $table->unique(['federation_id', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('federation_entity_invitations');
    }
};
