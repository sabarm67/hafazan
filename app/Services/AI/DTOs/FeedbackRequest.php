<?php

namespace App\Services\AI\DTOs;

/**
 * Context passed to the AI coach to generate encouragement/guidance.
 * Never include shaming language in prompts built from this DTO.
 */
readonly class FeedbackRequest
{
    /**
     * @param array<string, mixed> $performanceSummary e.g. streak, recent mistake rate, weak ayat count
     */
    public function __construct(
        public int $userId,
        public string $locale,
        public array $performanceSummary = [],
    ) {}
}
