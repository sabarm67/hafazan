<?php

namespace App\Services\AI\Providers;

use App\Contracts\AI\AiProviderInterface;
use App\Enums\AiProviderName;
use App\Services\AI\DTOs\FeedbackRequest;
use App\Services\AI\DTOs\FeedbackResult;
use App\Services\AI\DTOs\RecitationEvaluationRequest;
use App\Services\AI\DTOs\RecitationEvaluationResult;
use App\Services\AI\Exceptions\AiProviderNotImplementedException;

/**
 * STUB — implements AiProviderInterface so it can be selected via
 * AI_PROVIDER=ollama and satisfies the type system, but performs no real
 * calls yet. Wire up a local Ollama HTTP call (offline-dev use case) here in
 * Phase 6/9.
 */
class OllamaProvider implements AiProviderInterface
{
    public function __construct(private readonly array $config) {}

    public function evaluateRecitation(RecitationEvaluationRequest $request): RecitationEvaluationResult
    {
        throw new AiProviderNotImplementedException('OllamaProvider::evaluateRecitation is not implemented — see Phase 6/9.');
    }

    public function generateFeedback(FeedbackRequest $request): FeedbackResult
    {
        throw new AiProviderNotImplementedException('OllamaProvider::generateFeedback is not implemented — see Phase 6/9.');
    }

    public function getProviderName(): string
    {
        return AiProviderName::Ollama->value;
    }

    public function isAvailable(): bool
    {
        return false;
    }
}
