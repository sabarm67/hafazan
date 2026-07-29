<?php

namespace App\Console\Commands;

use App\Models\Surah;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Populates `ayat` (Uthmani text + tajweed highlighting) and the Malay
 * translation, from editions Al Quran Cloud mirrors from Tanzil (Uthmani
 * text) and the Basmeih translation (see https://alquran.cloud/api — text
 * sourced from https://tanzil.net, both subject to their respective
 * licence/attribution terms), plus tajweed rule annotations derived from
 * cpfair/quran-tajweed (CC BY 4.0 — attribution required in the UI
 * wherever rendered, see SurahReaderView.vue).
 *
 * All three payloads are bundled at database/data/quran-uthmani.json,
 * database/data/quran-translation-ms.json, and
 * database/data/quran-tajweed.json rather than fetched live from
 * api.alquran.cloud at import/request time. They were fetched once and are
 * static (this text doesn't change), and some hosting networks cannot reach
 * api.alquran.cloud at all — diagnosed on production via `curl -v`: DNS
 * resolved fine, but the TCP handshake to its IPs just hung with no
 * response, while other HTTPS hosts (e.g. api.github.com) connected
 * normally. That's consistent with something on api.alquran.cloud's side
 * blocking traffic from cloud-hosting IP ranges, not a fixable server-side
 * firewall setting. Bundling the data removes the network dependency
 * entirely — `TanzilAlQuranCloudRepository::getTranslation()` reads the
 * `ayah_translations` rows this command writes before ever falling back to
 * a live HTTP call. `SurahSeeder` must have already run.
 *
 * quran-tajweed.json is derived, not a direct download — see
 * scripts/tajweed-build/remap.py for how (and why: the upstream project's
 * annotation indices were computed against a specific 2017 text snapshot
 * that no longer matches Tanzil's current encoding, so they had to be
 * remapped onto our own bundled text; verified 0 mismatches across all
 * 6236 ayat after accounting for that snapshot's missing optional pause
 * marks).
 */
class ImportTanzilQuranCommand extends Command
{
    protected $signature = 'quran:import-tanzil';

    protected $description = 'Import Uthmani ayah text, tajweed highlighting, and the Malay translation (plus juz/page/ruku/hizb-quarter/sajda metadata) into the ayat and ayah_translations tables';

    public function handle(): int
    {
        if (Surah::count() === 0) {
            $this->error('No surahs found — run `php artisan db:seed --class=SurahSeeder` first.');

            return self::FAILURE;
        }

        if (! $this->importAyat()) {
            return self::FAILURE;
        }

        if (! $this->importTajweed()) {
            return self::FAILURE;
        }

        if (! $this->importTranslations()) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function importAyat(): bool
    {
        $path = database_path('data/quran-uthmani.json');

        if (! is_file($path)) {
            $this->error("Bundled corpus not found at {$path}.");

            return false;
        }

        $this->info("Reading Uthmani text from {$path} ...");

        $payload = json_decode(file_get_contents($path), true);
        $surahsPayload = $payload['data']['surahs'] ?? [];

        if (empty($surahsPayload)) {
            $this->error('Bundled corpus did not contain any surah data.');

            return false;
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

            $rows = array_map(function (array $ayah) use ($surahId, $now) {
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

        return true;
    }

    private function importTajweed(): bool
    {
        $path = database_path('data/quran-tajweed.json');

        if (! is_file($path)) {
            $this->error("Bundled tajweed data not found at {$path}.");

            return false;
        }

        $this->info("Reading tajweed annotations from {$path} ...");

        $entries = json_decode(file_get_contents($path), true);

        if (empty($entries)) {
            $this->error('Bundled tajweed data did not contain any entries.');

            return false;
        }

        $ayahIdsBySurahAndAyah = [];
        DB::table('ayat')
            ->join('surahs', 'surahs.id', '=', 'ayat.surah_id')
            ->select('ayat.id', 'surahs.number as surah_number', 'ayat.number_in_surah')
            ->orderBy('ayat.id')
            ->each(function ($row) use (&$ayahIdsBySurahAndAyah) {
                $ayahIdsBySurahAndAyah["{$row->surah_number}:{$row->number_in_surah}"] = $row->id;
            });

        $now = now();
        $bar = $this->output->createProgressBar(count($entries));
        $matched = 0;

        // Plain per-row updates, not upsert() — this only ever touches
        // existing ayat rows (importAyat() has already run), and SQLite's
        // upsert() validates the INSERT branch's NOT NULL constraints even
        // when a conflict is expected, which fails here since surah_id etc.
        // aren't in the values list.
        DB::transaction(function () use ($entries, $ayahIdsBySurahAndAyah, $now, $bar, &$matched) {
            foreach ($entries as $entry) {
                $ayahId = $ayahIdsBySurahAndAyah["{$entry['surah']}:{$entry['ayah']}"] ?? null;

                if ($ayahId !== null) {
                    DB::table('ayat')->where('id', $ayahId)->update([
                        'tajweed_rules' => json_encode($entry['annotations']),
                        'updated_at' => $now,
                    ]);
                    $matched++;
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();

        $this->info("Done. {$matched} of ".count($entries).' ayat matched with tajweed annotations.');

        return true;
    }

    private function importTranslations(): bool
    {
        $path = database_path('data/quran-translation-ms.json');

        if (! is_file($path)) {
            $this->error("Bundled translation not found at {$path}.");

            return false;
        }

        $this->info("Reading Malay translation from {$path} ...");

        $payload = json_decode(file_get_contents($path), true);
        $surahsPayload = $payload['data']['surahs'] ?? [];

        if (empty($surahsPayload)) {
            $this->error('Bundled translation did not contain any surah data.');

            return false;
        }

        $ayahIdsByNumberInQuran = DB::table('ayat')->pluck('id', 'number_in_quran');
        $now = now();
        $bar = $this->output->createProgressBar(count($surahsPayload));

        foreach ($surahsPayload as $surahPayload) {
            $rows = [];

            foreach ($surahPayload['ayahs'] as $ayah) {
                $ayahId = $ayahIdsByNumberInQuran[$ayah['number']] ?? null;

                if ($ayahId === null) {
                    continue;
                }

                $rows[] = [
                    'ayah_id' => $ayahId,
                    'locale' => 'ms',
                    'translation_text' => $ayah['text'],
                    'source' => 'alquran.cloud (ms.basmeih)',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($rows !== []) {
                DB::table('ayah_translations')->upsert(
                    $rows,
                    ['ayah_id', 'locale'],
                    ['translation_text', 'source', 'updated_at']
                );
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $total = DB::table('ayah_translations')->where('locale', 'ms')->count();
        $this->info("Done. {$total} Malay translations in the database (expected 6236).");

        return true;
    }
}
