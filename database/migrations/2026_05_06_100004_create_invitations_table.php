<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email', 255);
            $table->string('token', 64);
            $table->enum('role', ['entity_manager'])->default('entity_manager');
            $table->foreignUuid('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('federation_id')->constrained('federations')->cascadeOnDelete();
            $table->foreignUuid('entity_id')->nullable()->constrained('entities')->nullOnDelete();
            $table->foreignUuid('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('invitation_request_id')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->text('reissue_comment')->nullable();
            $table->string('previous_token', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique('token', 'inv_token_idx');
            $table->index('email', 'inv_email_idx');
            $table->index('federation_id', 'inv_federation_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
