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
        Schema::create('ayat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('surah_id')->constrained('surahs')->cascadeOnDelete();
            $table->unsignedSmallInteger('number_in_surah');
            $table->unsignedSmallInteger('number_in_quran')->unique();
            $table->text('text_arabic_uthmani');
            $table->unsignedTinyInteger('juz_number');
            $table->unsignedTinyInteger('hizb_number');
            $table->unsignedSmallInteger('page_number');
            $table->unsignedSmallInteger('ruku_number');
            $table->boolean('is_sajda')->default(false);
            $table->string('audio_url')->nullable();
            $table->timestamps();

            $table->unique(['surah_id', 'number_in_surah']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ayat');
    }
};
