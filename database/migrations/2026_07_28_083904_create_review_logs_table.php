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
        Schema::create('review_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_session_id')->constrained('review_sessions')->cascadeOnDelete();
            $table->foreignId('memorisation_record_id')->constrained('memorisation_records')->cascadeOnDelete();
            $table->foreignId('ayah_id')->constrained('ayat')->cascadeOnDelete();
            $table->dateTime('attempted_at');
            $table->boolean('is_correct');
            $table->decimal('correctness_score', 5, 2)->nullable();
            $table->unsignedInteger('time_to_recall_ms')->nullable();
            $table->unsignedTinyInteger('confidence_level')->nullable(); // 1-5, self-reported
            $table->string('ai_provider_used')->nullable();
            $table->json('ai_evaluation_result')->nullable();
            $table->string('interval_stage_before')->nullable();
            $table->string('interval_stage_after')->nullable();
            $table->timestamps();

            $table->index(['memorisation_record_id', 'attempted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('review_logs');
    }
};
