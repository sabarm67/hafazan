<?php

namespace App\Services\Quran\DTOs;

readonly class AyahData
{
    public function __construct(
        public int $surahNumber,
        public int $numberInSurah,
        public int $numberInQuran,
        public string $textArabicUthmani,
        public int $juzNumber,
        public int $hizbNumber,
        public int $pageNumber,
        public int $rukuNumber,
        public bool $isSajda,
    ) {}
}
