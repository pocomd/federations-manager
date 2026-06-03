<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduler_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('value');
            $table->string('type')->default('string');
            $table->string('label');
            $table->string('description')->nullable();
            $table->string('group')->default('general');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduler_settings');
    }
};
