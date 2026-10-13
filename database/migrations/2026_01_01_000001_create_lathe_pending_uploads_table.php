<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lathe_pending_uploads', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('user_id', 191)->index();
            $table->string('field');
            $table->string('disk');
            $table->string('path');
            $table->string('original_name');
            $table->unsignedBigInteger('size')->default(0);
            $table->string('draft_id')->nullable()->index();
            $table->string('status', 20)->default('pending');
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lathe_pending_uploads');
    }
};
