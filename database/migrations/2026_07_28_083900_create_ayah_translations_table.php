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
        Schema::create('ayah_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ayah_id')->constrained('ayat')->cascadeOnDelete();
            $table->string('locale', 10);
            $table->text('translation_text');
            $table->string('source');
            $table->timestamps();

            $table->unique(['ayah_id', 'locale']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ayah_translations');
    }
};
