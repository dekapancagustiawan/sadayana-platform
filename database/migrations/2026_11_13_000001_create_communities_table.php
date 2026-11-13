<?php

use App\Support\Database\Constraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->text('address')->nullable();
            $table->string('timezone', 50)->default('Asia/Jakarta');
            $table->string('status', 20)->default('active');
            $table->auditFields();
            $table->timestampsTz();
        });

        Constraints::check('communities', 'communities_status_check', "status IN ('active', 'inactive')");
    }

    public function down(): void
    {
        Schema::dropIfExists('communities');
    }
};
