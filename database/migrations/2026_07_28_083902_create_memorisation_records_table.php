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
        Schema::create('memorisation_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ayah_id')->constrained('ayat')->cascadeOnDelete();
            $table->unsignedTinyInteger('memory_strength_score')->default(0); // 0-100
            $table->dateTime('last_recall_at')->nullable();
            $table->unsignedInteger('recall_count')->default(0);
            $table->unsignedInteger('mistake_count')->default(0);
            // Backed by App\Enums\ReviewIntervalStage
            $table->string('current_interval_stage')->default('immediate');
            $table->date('next_review_date')->nullable();
            // Backed by App\Enums\MemorisationClassification
            $table->string('classification')->default('sabak');
            $table->timestamp('classification_updated_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'ayah_id']);
            $table->index(['user_id', 'next_review_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memorisation_records');
    }
};
