<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_types', function (Blueprint $table) {
            $table->string('id', 50)->primary();
            $table->string('label');
            $table->text('description');
            $table->foreignUuid('mail_template_id')->nullable()->constrained('mail_templates')->nullOnDelete();
            $table->boolean('notify_submitter')->default(true);
            $table->boolean('notify_federation_managers')->default(true);
            $table->boolean('notify_admins')->default(false);
            $table->boolean('notify_entity_technical')->default(false);
            $table->boolean('notify_entity_admin')->default(false);
            $table->boolean('default_via_ui')->default(true);
            $table->boolean('notify_email')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_types');
    }
};
