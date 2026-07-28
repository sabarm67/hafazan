<?php

namespace App\Services\AI\Providers;

use App\Contracts\AI\AiProviderInterface;
use App\Enums\AiProviderName;
use App\Services\AI\DTOs\FeedbackRequest;
use App\Services\AI\DTOs\FeedbackResult;
use App\Services\AI\DTOs\RecitationEvaluationRequest;
use App\Services\AI\DTOs\RecitationEvaluationResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Real, working adapter — calls the Anthropic Messages API directly. This is
 * the only provider wired end-to-end during the scaffold phase; the other
 * four adapters implement the same interface as stubs (see Phase 6/9).
 */
class ClaudeProvider implements AiProviderInterface
{
    private string $apiKey;
    private string $model;
    private string $baseUrl;

    public function __construct(array $config)
    {
        $this->apiKey = $config['api_key'] ?? '';
        $this->model = $config['model'] ?? 'claude-sonnet-5';
        $this->baseUrl = $config['base_url'] ?? 'https://api.anthropic.com/v1';
    }

    public function evaluateRecitation(RecitationEvaluationRequest $request): RecitationEvaluationResult
    {
        $prompt = <<<PROMPT
            You are assisting a Quran memorisation (Hifz) app. Compare the transcribed
            recitation against the expected ayah text and respond with ONLY a JSON
            object (no prose, no markdown fences) with this exact shape:
            {"correctness_score": <0-100 number>, "wrong_sequence_detected": <bool>,
             "missing_words": [<string>...], "extra_words": [<string>...],
             "repeated_words": [<string>...], "pronunciation_confidence": <0-1 number>}

            Note: this is an assistive signal only, not a definitive Tajwid ruling.

            Expected Arabic text: {$request->expectedArabicText}
            Transcribed text: {$request->transcribedText}
            PROMPT;

        $data = $this->callMessagesApi($prompt);
        $parsed = json_decode($data['text'] ?? '', true) ?? [];

        return new RecitationEvaluationResult(
            correctnessScore: (float) ($parsed['correctness_score'] ?? 0),
            wrongSequenceDetected: (bool) ($parsed['wrong_sequence_detected'] ?? false),
            missingWords: $parsed['missing_words'] ?? [],
            extraWords: $parsed['extra_words'] ?? [],
            repeatedWords: $parsed['repeated_words'] ?? [],
            pronunciationConfidence: (float) ($parsed['pronunciation_confidence'] ?? 0),
            providerName: $this->getProviderName(),
            raw: $data,
        );
    }

    public function generateFeedback(FeedbackRequest $request): FeedbackResult
    {
        $summary = json_encode($request->performanceSummary);
        $prompt = <<<PROMPT
            You are an encouraging Hifz (Quran memorisation) coach. Never shame the
            learner. Write a short, warm, motivating message (2-3 sentences, in
            locale "{$request->locale}") based on this performance summary: {$summary}
            Respond with ONLY a JSON object: {"message": <string>, "suggestions": [<string>...]}
            PROMPT;

        $data = $this->callMessagesApi($prompt);
        $parsed = json_decode($data['text'] ?? '', true) ?? [];

        return new FeedbackResult(
            message: $parsed['message'] ?? ($data['text'] ?? ''),
            suggestions: $parsed['suggestions'] ?? [],
            providerName: $this->getProviderName(),
        );
    }

    public function getProviderName(): string
    {
        return AiProviderName::Claude->value;
    }

    public function isAvailable(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * @return array{text: string, raw: array}
     */
    private function callMessagesApi(string $prompt): array
    {
        if (! $this->isAvailable()) {
            throw new RuntimeException('Claude provider is not configured — set ANTHROPIC_API_KEY.');
        }

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->post("{$this->baseUrl}/messages", [
            'model' => $this->model,
            'max_tokens' => 1024,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        if ($response->failed()) {
            Log::error('Claude API call failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new RuntimeException("Claude API call failed with status {$response->status()}");
        }

        $json = $response->json();
        $text = $json['content'][0]['text'] ?? '';

        return ['text' => $text, 'raw' => $json ?? []];
    }
}
