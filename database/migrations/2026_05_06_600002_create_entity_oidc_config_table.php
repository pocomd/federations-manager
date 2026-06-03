<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_oidc_config', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('entity_id')->constrained()->cascadeOnDelete();
            $table->string('client_id')->nullable();
            $table->json('redirect_uris');
            $table->json('grant_types');
            $table->json('response_types');
            $table->json('scopes');
            $table->string('application_type', 50)->nullable();
            $table->string('token_endpoint_auth_method', 100)->nullable();
            $table->text('logo_uri')->nullable();
            $table->text('policy_uri')->nullable();
            $table->text('tos_uri')->nullable();
            $table->timestamps();

            $table->unique('entity_id', 'eoc_entity_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_oidc_config');
    }
};
