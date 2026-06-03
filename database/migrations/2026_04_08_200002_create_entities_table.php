<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('entity_id', 255)->unique();
            $table->enum('type', ['idp', 'sp', 'oidc']);
            $table->enum('status', ['draft', 'pending', 'active', 'suspended', 'deleted'])
                  ->default('draft');
            $table->boolean('edugain')->default(false);
            $table->string('registration_authority', 255)->default('');
            $table->json('registration_policies')->nullable()->default(null);
            $table->enum('source', ['manual', 'edugain', 'imported'])->nullable()->default(null);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('last_updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('org_lat', 10, 7)->nullable();
            $table->decimal('org_lng', 10, 7)->nullable();

            // IdP specific
            $table->string('scope', 255)->nullable();
            $table->json('nameid_formats')->nullable()->default(null);

            // SP specific
            $table->boolean('sp_want_authn_requests_signed')->default(true);
            $table->boolean('sp_want_assertions_signed')->default(true);
            $table->json('requested_attributes')->nullable()->default(null);

            // MDQ
            $table->string('sha1_entity_id', 40)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('type');
            $table->index('status');
            $table->index('edugain');
            $table->index('source');
            $table->index('sha1_entity_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entities');
    }
};
