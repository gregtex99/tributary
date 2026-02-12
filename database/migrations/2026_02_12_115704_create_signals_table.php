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
        Schema::create('signals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('org_id');
            $table->string('source');
            $table->string('source_ref')->nullable();
            $table->string('signal_type');
            $table->string('actor');
            $table->timestamp('detected_at');
            $table->foreignUuid('matched_item_id')->nullable()->constrained('action_items')->nullOnDelete();
            $table->jsonb('payload')->nullable();
            $table->timestamps();

            $table->index('org_id');
            $table->index(['org_id', 'signal_type', 'detected_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('signals');
    }
};
