<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('code_sequences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('entity_type');
            $table->string('month_year', 6); // Format: my (e.g. '0726')
            $table->integer('last_number');
            $table->timestamps();

            $table->unique(['entity_type', 'month_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('code_sequences');
    }
};
