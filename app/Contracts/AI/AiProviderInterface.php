<?php

namespace App\Contracts\AI;

use App\Services\AI\DTOs\FeedbackRequest;
use App\Services\AI\DTOs\FeedbackResult;
use App\Services\AI\DTOs\RecitationEvaluationRequest;
use App\Services\AI\DTOs\RecitationEvaluationResult;

/**
 * Contract every AI provider adapter must implement so the rest of the
 * application (jobs, controllers, the future Adaptive Hifz Engine) can depend
 * on this interface alone and swap providers via config('ai.default').
 */
interface AiProviderInterface
{
    public function evaluateRecitation(RecitationEvaluationRequest $request): RecitationEvaluationResult;

    public function generateFeedback(FeedbackRequest $request): FeedbackResult;

    public function getProviderName(): string;

    /** Whether this provider is configured (e.g. API key present) and reachable. */
    public function isAvailable(): bool;
}
