<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('action_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('org_id');
            $table->string('user_id');
            $table->string('source');
            $table->string('source_ref')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('current_state');
            $table->string('ball_with');
            $table->string('waiting_for')->nullable();
            $table->integer('nudge_after_hours')->default(48);
            $table->timestamp('next_nudge_at')->nullable();
            $table->integer('nudge_count')->default(0);
            $table->integer('max_nudges')->default(3);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index('org_id');
            $table->index('user_id');
            $table->index('current_state');
            $table->index(['org_id', 'current_state']);
            $table->index('next_nudge_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('action_items');
    }
};
