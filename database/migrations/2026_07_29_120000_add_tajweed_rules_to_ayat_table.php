<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ayat', function (Blueprint $table) {
            // Array of {rule, start, end} — codepoint offsets into
            // text_arabic_uthmani — populated by `quran:import-tanzil` from
            // the bundled database/data/quran-tajweed.json. Purely a
            // rendering concern (colour-coded tajweed highlighting), never
            // queried by rule, so a JSON column is simpler than a separate
            // table.
            $table->json('tajweed_rules')->nullable()->after('is_sajda');
        });
    }

    public function down(): void
    {
        Schema::table('ayat', function (Blueprint $table) {
            $table->dropColumn('tajweed_rules');
        });
    }
};
