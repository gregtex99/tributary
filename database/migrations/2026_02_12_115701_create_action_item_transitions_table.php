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
        Schema::create('action_item_transitions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('action_item_id')->constrained('action_items')->cascadeOnDelete();
            $table->string('from_state');
            $table->string('to_state');
            $table->string('trigger');
            $table->jsonb('signal_data')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('action_item_transitions');
    }
};
