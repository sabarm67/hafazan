<?php

namespace App\Console\Commands;

use App\Models\Surah;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Populates `ayat` from the Uthmani text edition Al Quran Cloud mirrors from
 * Tanzil (`quran-uthmani`, see https://alquran.cloud/api — text sourced from
 * https://tanzil.net, both subject to their respective licence/attribution
 * terms). One bulk request returns the full Mushaf; no manual corpus
 * download is needed for this source. `SurahSeeder` must have already run.
 */
class ImportTanzilQuranCommand extends Command
{
    protected $signature = 'quran:import-tanzil';

    protected $description = 'Import Uthmani ayah text (and juz/page/ruku/hizb-quarter/sajda metadata) into the ayat table';

    public function handle(): int
    {
        if (Surah::count() === 0) {
            $this->error('No surahs found — run `php artisan db:seed --class=SurahSeeder` first.');

            return self::FAILURE;
        }

        $baseUrl = config('quran.tanzil_alquran_cloud.alquran_cloud_base_url');
        $this->info("Fetching Uthmani text from {$baseUrl}/quran/quran-uthmani ...");

        $response = Http::timeout(60)->get("{$baseUrl}/quran/quran-uthmani");

        if ($response->failed()) {
            $this->error("Request failed with status {$response->status()}.");

            return self::FAILURE;
        }

        $surahsPayload = $response->json('data.surahs', []);

        if (empty($surahsPayload)) {
            $this->error('Response did not contain any surah data.');

            return self::FAILURE;
        }

        $surahIdsByNumber = Surah::pluck('id', 'number');
        $now = now();
        $bar = $this->output->createProgressBar(count($surahsPayload));

        foreach ($surahsPayload as $surahPayload) {
            $surahNumber = $surahPayload['number'];
            $surahId = $surahIdsByNumber[$surahNumber] ?? null;

            if ($surahId === null) {
                $this->warn("Skipping surah {$surahNumber} — not found in local surahs table.");
                $bar->advance();

                continue;
            }

            $rows = array_map(function (array $ayah) use ($surahId, $surahNumber, $now) {
                // The API's "hizbQuarter" is the quarter-hizb index (1-240),
                // the granularity actually marked in most printed Mushaf
                // margins — stored here as-is, not collapsed to whole hizb (1-60).
                return [
                    'surah_id' => $surahId,
                    'number_in_surah' => $ayah['numberInSurah'],
                    'number_in_quran' => $ayah['number'],
                    'text_arabic_uthmani' => $ayah['text'],
                    'juz_number' => $ayah['juz'],
                    'hizb_number' => $ayah['hizbQuarter'],
                    'page_number' => $ayah['page'],
                    'ruku_number' => $ayah['ruku'],
                    'is_sajda' => $ayah['sajda'] !== false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }, $surahPayload['ayahs']);

            DB::table('ayat')->upsert(
                $rows,
                ['number_in_quran'],
                ['text_arabic_uthmani', 'juz_number', 'hizb_number', 'page_number', 'ruku_number', 'is_sajda', 'updated_at']
            );

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $total = DB::table('ayat')->count();
        $this->info("Done. {$total} ayat in the database (expected 6236).");

        return self::SUCCESS;
    }
}
