<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lathe_user_preferences', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 191);
            $table->string('key');
            $table->json('value');
            $table->timestamps();

            $table->unique(['user_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lathe_user_preferences');
    }
};
