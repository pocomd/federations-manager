<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_managers', function (Blueprint $table) {
            $table->foreignUuid('entity_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('role', ['owner', 'manager'])->default('owner');
            $table->foreignUuid('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('added_at')->nullable();

            $table->primary(['entity_id', 'user_id']);
            $table->index('entity_id', 'em_entity_idx');
            $table->index('user_id', 'em_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_managers');
    }
};
