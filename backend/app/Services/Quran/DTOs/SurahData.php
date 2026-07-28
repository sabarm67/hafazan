<?php

namespace App\Services\Quran\DTOs;

readonly class SurahData
{
    public function __construct(
        public int $number,
        public string $nameArabic,
        public string $nameTransliteration,
        public string $nameTranslationMs,
        public string $revelationType,
        public int $totalAyat,
    ) {}
}
