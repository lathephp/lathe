<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The columns the record pages edit. Orders keep their schema, so the 10M orders stay as seeded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('type', 16)->default('person');
            $table->string('company_name')->nullable();
            $table->string('state', 64)->nullable();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('reorder_level')->default(5);
            $table->json('specifications')->nullable();
            $table->text('description')->nullable();
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->string('slug')->nullable();
            $table->string('cover')->nullable();
            $table->text('markdown_body')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['slug', 'cover', 'markdown_body']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['reorder_level', 'specifications', 'description']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['type', 'company_name', 'state']);
        });
    }
};
