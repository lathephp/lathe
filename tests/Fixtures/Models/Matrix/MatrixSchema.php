<?php

namespace Lathe\Tests\Fixtures\Models\Matrix;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The tables behind the matrix models.
 */
class MatrixSchema
{
    public static function create(): void
    {
        Schema::create('mx_parents', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('team')->default(1);
            $table->softDeletes();
        });
        Schema::create('mx_children', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mx_parent_id');
            $table->string('name');
        });
        Schema::create('mx_grandchildren', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mx_child_id');
            $table->string('name');
        });
        Schema::create('mx_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mx_parent_id');
            $table->string('body');
        });
        Schema::create('mx_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
        Schema::create('mx_parent_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mx_parent_id');
            $table->foreignId('mx_tag_id');
            $table->integer('position')->default(0);
        });
        Schema::create('mx_notes', function (Blueprint $table) {
            $table->id();
            $table->morphs('notable');
            $table->string('body');
        });
        Schema::create('mx_taggables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mx_tag_id');
            $table->morphs('taggable');
            $table->integer('position')->default(0);
        });
    }
}
