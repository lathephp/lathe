<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lathe_imports', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 191)->index();
            $table->string('file_disk')->default('local');
            $table->string('file_name');
            $table->string('original_name')->nullable();
            $table->string('model_class');
            $table->json('column_map')->nullable();
            $table->string('status', 20)->default('uploaded');
            $table->string('mode', 10)->default('import');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('last_processed_row')->default(0);
            $table->unsignedInteger('created_rows')->default(0);
            $table->unsignedInteger('updated_rows')->default(0);
            $table->unsignedInteger('skipped_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->string('error_file')->nullable();
            $table->text('exception')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lathe_imports');
    }
};
