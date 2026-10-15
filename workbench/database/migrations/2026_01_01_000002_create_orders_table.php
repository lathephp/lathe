<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->index()->constrained();
            $table->string('number', 12)->unique();
            $table->string('status', 16)->index();
            $table->integer('total_cents')->index();
            $table->dateTime('placed_at')->index();
            $table->timestamps();

            // Filter by status and sort by total on 10 million rows stay on an index.
            $table->index(['status', 'total_cents']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
