<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lathe_field_attachments', function (Blueprint $table) {
            $table->id();
            $table->string('attachable_type', 191);
            $table->string('attachable_id', 191);
            $table->string('attachment');
            $table->string('disk');
            $table->string('url', 2048);
            $table->timestamps();

            $table->index(['attachable_type', 'attachable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lathe_field_attachments');
    }
};
