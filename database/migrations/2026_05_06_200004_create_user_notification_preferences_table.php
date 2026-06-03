<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notification_preferences', function (Blueprint $table) {
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('notification_type', 50);
            $table->foreign('notification_type')->references('id')->on('notification_types')->cascadeOnDelete();
            $table->boolean('via_ui')->default(true);
            $table->boolean('via_email')->default(false);

            $table->primary(['user_id', 'notification_type']);
            $table->index('user_id', 'unp_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notification_preferences');
    }
};
