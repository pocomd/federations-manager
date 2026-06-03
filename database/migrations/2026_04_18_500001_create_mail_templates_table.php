<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('federation_id')->nullable()->constrained('federations')->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('group', 50);
            $table->string('subject', 255);
            $table->text('body');
            $table->string('lang', 10)->default('en');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['group', 'lang']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_templates');
    }
};
