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
        Schema::create('nudge_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('org_id');
            $table->string('item_type');
            $table->integer('first_nudge_hours')->default(48);
            $table->integer('repeat_nudge_hours')->default(48);
            $table->integer('max_nudges')->default(3);
            $table->boolean('auto_send')->default(false);
            $table->integer('escalation_after_nudges')->default(2);
            $table->timestamps();

            $table->index('org_id');
            $table->unique(['org_id', 'item_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nudge_rules');
    }
};
