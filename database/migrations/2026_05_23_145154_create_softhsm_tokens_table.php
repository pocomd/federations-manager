<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('softhsm_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('federation_id')->constrained()->cascadeOnDelete();
            $table->string('token_label', 255);
            $table->string('slot_id', 64);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('federation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('softhsm_tokens');
    }
};
