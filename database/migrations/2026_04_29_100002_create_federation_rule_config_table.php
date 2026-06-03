<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('federation_rule_config', function (Blueprint $table) {
            $table->uuid('federation_id');
            $table->string('rule_id', 8);
            $table->boolean('enabled')->default(true);
            $table->string('severity', 16)->nullable();  // null = use rule default
            $table->timestamps();

            $table->primary(['federation_id', 'rule_id']);
            $table->foreign('federation_id')->references('id')->on('federations')->cascadeOnDelete();
            $table->foreign('rule_id')->references('id')->on('rule_definitions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('federation_rule_config');
    }
};
