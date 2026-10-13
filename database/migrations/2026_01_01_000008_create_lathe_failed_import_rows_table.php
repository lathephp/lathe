<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lathe_failed_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('lathe_imports')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('data');
            $table->json('errors');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lathe_failed_import_rows');
    }
};
