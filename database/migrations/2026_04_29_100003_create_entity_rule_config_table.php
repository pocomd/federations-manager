<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_rule_config', function (Blueprint $table) {
            $table->uuid('entity_id');
            $table->string('rule_id', 8);
            $table->boolean('enabled')->default(true);
            $table->string('severity', 16)->nullable();  // null = use federation/rule default
            $table->timestamps();

            $table->primary(['entity_id', 'rule_id']);
            $table->foreign('entity_id')->references('id')->on('entities')->cascadeOnDelete();
            $table->foreign('rule_id')->references('id')->on('rule_definitions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_rule_config');
    }
};
