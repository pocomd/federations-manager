<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rule_definitions', function (Blueprint $table) {
            $table->string('id', 8)->primary();          // e.g. 'S01', 'C01', 'R01'
            $table->string('name');
            $table->string('group', 32);                 // structural|certificate|refeds|xsd
            $table->string('applies_to', 8);             // both|idp|sp
            $table->string('default_severity', 16);      // error|warning
            $table->text('description');
            $table->string('spec_url')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rule_definitions');
    }
};
