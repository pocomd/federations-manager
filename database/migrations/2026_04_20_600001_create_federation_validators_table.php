<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('federation_validators', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('federation_id')
                  ->constrained()->cascadeOnDelete();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->text('url');
            $table->enum('http_method', ['GET', 'POST'])->default('GET');
            $table->string('metadata_arg_name', 100)->default('metadata');
            $table->text('optional_args')->nullable();
            $table->string('args_separator', 10)->default('&');
            $table->unsignedSmallInteger('timeout')->default(30);
            $table->string('response_code_element', 100)->default('returncode');
            $table->string('response_message_element', 100)->default('message');
            $table->string('success_value', 10)->default('0');
            $table->string('warning_value', 10)->default('1');
            $table->string('error_value', 10)->default('2');
            $table->string('critical_value', 10)->default('3');
            $table->boolean('enabled')->default(true);
            $table->boolean('enabled_on_registration')->default(false);
            $table->boolean('mandatory')->default(false);
            $table->timestamps();
            $table->index(['federation_id', 'enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('federation_validators');
    }
};
