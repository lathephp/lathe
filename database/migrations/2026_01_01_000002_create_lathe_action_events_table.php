<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lathe_action_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('batch_id');
            $table->string('user_id', 191)->nullable()->index();
            $table->string('type', 50)->index();
            $table->string('actionable_type', 191)->nullable();
            $table->string('actionable_id', 191)->nullable();
            $table->string('target_type', 191)->nullable();
            $table->string('target_id', 191)->nullable();
            $table->string('action_name')->nullable();
            $table->string('resource_class')->nullable();
            $table->json('original')->nullable();
            $table->json('changes')->nullable();
            $table->json('fields')->nullable();
            $table->string('status', 25)->default('finished');
            $table->text('exception')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['actionable_type', 'actionable_id', 'created_at'], 'lathe_ae_actionable_index');
            $table->index(['target_type', 'target_id', 'created_at'], 'lathe_ae_target_index');
            $table->index(['batch_id', 'actionable_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lathe_action_events');
    }
};
