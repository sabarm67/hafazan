<?php

namespace App\Contracts\Quran;

use App\Services\Quran\DTOs\AyahData;
use App\Services\Quran\DTOs\SurahData;
use App\Services\Quran\DTOs\WordByWordToken;
use Illuminate\Support\Collection;

/**
 * Isolates Quran content access behind a contract so the text/translation/
 * audio source can be replaced without touching consuming code, if licensing
 * or attribution requirements change (per the project's data-source policy).
 */
interface QuranContentRepositoryInterface
{
    /** @return Collection<int, SurahData> */
    public function getSurahs(): Collection;

    public function getSurah(int $surahNumber): SurahData;

    /** @return Collection<int, AyahData> */
    public function getAyat(int $surahNumber): Collection;

    public function getAyah(int $surahNumber, int $ayahNumber): AyahData;

    public function getTranslation(int $surahNumber, int $ayahNumber, string $locale = 'ms'): string;

    /** @return Collection<int, WordByWordToken> */
    public function getWordByWord(int $surahNumber, int $ayahNumber): Collection;

    public function getAudioUrl(int $surahNumber, int $ayahNumber, string $reciter = 'default'): string;
}
