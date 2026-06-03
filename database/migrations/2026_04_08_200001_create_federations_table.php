<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('federations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 255);
            $table->string('slug', 255)->unique();
            $table->string('signing_driver', 50)->default('file');
            $table->text('description')->nullable();
            $table->string('uri', 512)->unique();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->string('metadata_url', 1024)->nullable();
            $table->boolean('jagger_compat_enabled')->default(false);
            $table->string('jagger_fed_name', 255)->nullable()->unique();
            $table->timestamp('metadata_generated_at')->nullable();
            $table->timestamp('metadata_edugain_generated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Now that federations exists, add the FK from users.federation_id
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('federation_id')
                ->references('id')
                ->on('federations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['federation_id']);
        });

        Schema::dropIfExists('federations');
    }
};
