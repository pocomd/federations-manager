<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('federation_managers', function (Blueprint $table) {
            $table->foreignUuid('federation_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();

            $table->primary(['federation_id', 'user_id']);
            $table->index('federation_id', 'fm_federation_idx');
            $table->index('user_id', 'fm_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('federation_managers');
    }
};
