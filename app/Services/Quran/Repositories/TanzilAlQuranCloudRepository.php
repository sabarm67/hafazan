<?php

namespace App\Services\Quran\Repositories;

use App\Contracts\Quran\QuranContentRepositoryInterface;
use App\Models\Ayah;
use App\Models\Surah;
use App\Services\Quran\DTOs\AyahData;
use App\Services\Quran\DTOs\SurahData;
use App\Services\Quran\DTOs\WordByWordToken;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Concrete QuranContentRepositoryInterface implementation.
 *
 * - Arabic Uthmani text + surah/ayah structural metadata: read from the local
 *   `surahs`/`ayat` tables, seeded once via `php artisan quran:import-tanzil`.
 *   Tanzil distributes its verified corpus as a download, not a live API, so
 *   there is no HTTP call to make here.
 * - Malay translation: read from the local `ayah_translations` table, also
 *   populated by `quran:import-tanzil` — api.alquran.cloud isn't reachable
 *   from every hosting network (see that command's docblock), so this can't
 *   depend on a live call either. Other locales fall back to a live,
 *   Redis-cached HTTP call to the Al Quran Cloud API (api.alquran.cloud).
 * - Audio: not a server-side HTTP call at all — getAudioUrl() just builds a
 *   URL string pointing at cdn.islamic.network, which the browser fetches
 *   directly.
 * - Word-by-word: Al Quran Cloud does not expose an official word-by-word
 *   endpoint, so this reads from the local `ayah_words` table instead. That
 *   table is intentionally left unseeded by this scaffold — populate it from
 *   a licensed word-by-word corpus in a future phase before relying on it.
 */
class TanzilAlQuranCloudRepository implements QuranContentRepositoryInterface
{
    private string $baseUrl;
    private string $audioBaseUrl;
    private int $cacheTtlSeconds;

    public function __construct(array $config)
    {
        $this->baseUrl = $config['alquran_cloud_base_url'] ?? 'https://api.alquran.cloud/v1';
        $this->audioBaseUrl = $config['audio_base_url'] ?? 'https://cdn.islamic.network/quran/audio';
        $this->cacheTtlSeconds = $config['cache_ttl_seconds'] ?? 604800; // 7 days
    }

    public function getSurahs(): Collection
    {
        return Surah::orderBy('number')->get()->map(fn (Surah $surah) => $this->toSurahData($surah));
    }

    public function getSurah(int $surahNumber): SurahData
    {
        return $this->toSurahData(Surah::where('number', $surahNumber)->firstOrFail());
    }

    public function getAyat(int $surahNumber): Collection
    {
        return Ayah::query()
            ->whereHas('surah', fn ($q) => $q->where('number', $surahNumber))
            ->orderBy('number_in_surah')
            ->get()
            ->map(fn (Ayah $ayah) => $this->toAyahData($ayah));
    }

    public function getAyah(int $surahNumber, int $ayahNumber): AyahData
    {
        $ayah = Ayah::query()
            ->whereHas('surah', fn ($q) => $q->where('number', $surahNumber))
            ->where('number_in_surah', $ayahNumber)
            ->firstOrFail();

        return $this->toAyahData($ayah);
    }

    public function getTranslation(int $surahNumber, int $ayahNumber, string $locale = 'ms'): string
    {
        // Bundled locally by `php artisan quran:import-tanzil` — see that
        // command's docblock for why (api.alquran.cloud isn't reachable
        // from every hosting network). Only 'ms' is bundled today; other
        // locales still fall back to the live API below.
        if ($locale === 'ms') {
            $ayah = Ayah::query()
                ->whereHas('surah', fn ($q) => $q->where('number', $surahNumber))
                ->where('number_in_surah', $ayahNumber)
                ->first();

            $translation = $ayah?->translations()->where('locale', 'ms')->value('translation_text');

            if ($translation !== null) {
                return $translation;
            }
        }

        $edition = match ($locale) {
            'ms' => 'ms.basmeih',
            'en' => 'en.sahih',
            default => "{$locale}.basmeih",
        };

        return Cache::remember(
            "quran:translation:{$surahNumber}:{$ayahNumber}:{$edition}",
            $this->cacheTtlSeconds,
            function () use ($surahNumber, $ayahNumber, $edition) {
                $response = Http::get("{$this->baseUrl}/ayah/{$surahNumber}:{$ayahNumber}/{$edition}");
                $response->throw();

                return $response->json('data.text', '');
            }
        );
    }

    public function getWordByWord(int $surahNumber, int $ayahNumber): Collection
    {
        $ayah = Ayah::query()
            ->whereHas('surah', fn ($q) => $q->where('number', $surahNumber))
            ->where('number_in_surah', $ayahNumber)
            ->with('words')
            ->firstOrFail();

        return $ayah->words
            ->sortBy('position')
            ->map(fn ($word) => new WordByWordToken(
                position: $word->position,
                textArabic: $word->text_arabic,
                transliteration: $word->transliteration,
                translationMs: $word->translation_ms,
            ))
            ->values();
    }

    public function getAudioUrl(int $surahNumber, int $ayahNumber, string $reciter = 'default'): string
    {
        $editionId = match ($reciter) {
            'default' => 'ar.alafasy',
            default => $reciter,
        };

        $numberInQuran = Cache::remember(
            "quran:ayah-number:{$surahNumber}:{$ayahNumber}",
            $this->cacheTtlSeconds,
            fn () => $this->getAyah($surahNumber, $ayahNumber)->numberInQuran
        );

        return "{$this->audioBaseUrl}/128/{$editionId}/{$numberInQuran}.mp3";
    }

    private function toSurahData(Surah $surah): SurahData
    {
        return new SurahData(
            number: $surah->number,
            nameArabic: $surah->name_arabic,
            nameTransliteration: $surah->name_transliteration,
            nameTranslationMs: $surah->name_translation_ms,
            revelationType: $surah->revelation_type,
            totalAyat: $surah->total_ayat,
        );
    }

    private function toAyahData(Ayah $ayah): AyahData
    {
        return new AyahData(
            surahNumber: $ayah->surah->number,
            numberInSurah: $ayah->number_in_surah,
            numberInQuran: $ayah->number_in_quran,
            textArabicUthmani: $ayah->text_arabic_uthmani,
            juzNumber: $ayah->juz_number,
            hizbNumber: $ayah->hizb_number,
            pageNumber: $ayah->page_number,
            rukuNumber: $ayah->ruku_number,
            isSajda: $ayah->is_sajda,
        );
    }
}
