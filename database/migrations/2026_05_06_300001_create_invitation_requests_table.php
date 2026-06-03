<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitation_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('entity_id');
            $table->foreign('entity_id')->references('id')->on('entities')->cascadeOnDelete();
            $table->string('contact_email', 255);
            $table->enum('contact_type', ['technical', 'support', 'security', 'administrative']);
            $table->string('contact_name', 255)->nullable();
            $table->uuid('requested_by')->nullable();
            $table->foreign('requested_by')->references('id')->on('users')->nullOnDelete();
            $table->uuid('federation_id')->nullable();
            $table->foreign('federation_id')->references('id')->on('federations')->nullOnDelete();
            $table->enum('status', ['pending', 'approved', 'rejected', 'accepted'])->default('pending');
            $table->text('fm_note')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('entity_id', 'ir_entity_idx');
            $table->index('federation_id', 'ir_federation_idx');
            $table->index('status', 'ir_status_idx');
        });

        // Add FK on invitations.invitation_request_id now that invitation_requests exists
        Schema::table('invitations', function (Blueprint $table) {
            $table->foreign('invitation_request_id')
                ->references('id')
                ->on('invitation_requests')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->dropForeign(['invitation_request_id']);
        });

        Schema::dropIfExists('invitation_requests');
    }
};
