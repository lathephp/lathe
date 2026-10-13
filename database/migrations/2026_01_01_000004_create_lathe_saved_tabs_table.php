<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lathe_saved_tabs', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 191);
            $table->string('resource_class');
            $table->string('name', 100);
            $table->json('filters')->nullable();
            $table->string('q', 500)->nullable();
            $table->string('sort')->nullable();
            $table->string('trashed', 10)->nullable();
            $table->boolean('show_count')->default(false);
            $table->string('badge_type', 10)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('schema_hash', 64)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'resource_class'], 'lathe_saved_tabs_user_resource_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lathe_saved_tabs');
    }
};
