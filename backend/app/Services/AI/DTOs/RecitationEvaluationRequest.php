<?php

namespace App\Services\AI\DTOs;

readonly class RecitationEvaluationRequest
{
    public function __construct(
        public int $ayahId,
        public string $expectedArabicText,
        public string $transcribedText,
        public ?string $audioUrl = null,
    ) {}
}
