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
        Schema::create('nudge_log', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('action_item_id')->constrained('action_items')->cascadeOnDelete();
            $table->integer('nudge_number');
            $table->string('channel');
            $table->text('message_text');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('response_detected_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nudge_log');
    }
};
