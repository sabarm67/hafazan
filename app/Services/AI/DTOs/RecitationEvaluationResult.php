<?php

namespace App\Services\AI\DTOs;

/**
 * Result of comparing a recitation attempt against the expected ayah text.
 * This is an assistive signal for the Adaptive Hifz Engine (Phase 6) — it is
 * not, and must not be presented as, a definitive Tajwid ruling.
 */
readonly class RecitationEvaluationResult
{
    /**
     * @param string[] $missingWords
     * @param string[] $extraWords
     * @param string[] $repeatedWords
     * @param array<int, array{startWordIndex: int, durationMs: int}> $pauses
     */
    public function __construct(
        public float $correctnessScore,
        public bool $wrongSequenceDetected,
        public array $missingWords = [],
        public array $extraWords = [],
        public array $repeatedWords = [],
        public array $pauses = [],
        public float $pronunciationConfidence = 0.0,
        public string $providerName = '',
        public array $raw = [],
    ) {}
}
