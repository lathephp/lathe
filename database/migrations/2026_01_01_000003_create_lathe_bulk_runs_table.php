<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lathe_bulk_runs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('user_id', 191)->index();
            $table->string('resource_class');
            $table->string('action_name');
            $table->string('batch_id')->nullable()->index();
            $table->string('status', 20)->default('waiting')->index();
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('processed')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->unsignedInteger('skipped')->default(0);
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lathe_bulk_runs');
    }
};
