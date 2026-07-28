<?php

namespace App\Services\Quran\DTOs;

readonly class WordByWordToken
{
    public function __construct(
        public int $position,
        public string $textArabic,
        public string $transliteration,
        public string $translationMs,
    ) {}
}
