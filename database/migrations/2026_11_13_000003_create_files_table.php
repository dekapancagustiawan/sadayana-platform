<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('community_id')->constrained('communities');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            // Penentu aturan akses di FileAccessRegistry, mis. `meter_photo`.
            $table->string('category', 50);
            $table->string('storage_disk', 50);
            $table->text('storage_path');
            $table->string('original_name', 255);
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('file_size');
            $table->string('checksum', 64)->nullable(); // sha256
            $table->timestampsTz();

            $table->index(['community_id', 'category']);
            $table->index('uploaded_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
