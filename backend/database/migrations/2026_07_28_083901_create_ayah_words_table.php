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
        Schema::create('ayah_words', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ayah_id')->constrained('ayat')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('text_arabic');
            $table->string('transliteration')->nullable();
            $table->string('translation_ms')->nullable();
            $table->timestamps();

            $table->unique(['ayah_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ayah_words');
    }
};
