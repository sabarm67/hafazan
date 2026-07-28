<?php

namespace App\Services\AI\DTOs;

readonly class FeedbackResult
{
    /**
     * @param string[] $suggestions
     */
    public function __construct(
        public string $message,
        public array $suggestions = [],
        public string $providerName = '',
    ) {}
}
