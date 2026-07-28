<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\AI\AiProviderInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\EvaluateRecitationRequest;
use App\Models\Ayah;
use App\Services\AI\DTOs\RecitationEvaluationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Compares a transcribed recitation attempt against the expected ayah text
 * via App\Contracts\AI\AiProviderInterface (Claude by default). This is an
 * assistive signal, not a definitive Tajwid ruling — see the prompt in
 * App\Services\AI\Providers\ClaudeProvider.
 */
class AiEvaluationController extends Controller
{
    public function __construct(private readonly AiProviderInterface $ai) {}

    public function evaluateRecitation(EvaluateRecitationRequest $request, int $surahNumber, int $ayahNumber): JsonResponse
    {
        $ayah = Ayah::query()
            ->whereHas('surah', fn ($q) => $q->where('number', $surahNumber))
            ->where('number_in_surah', $ayahNumber)
            ->firstOrFail();

        if (! $this->ai->isAvailable()) {
            return response()->json([
                'message' => 'AI evaluation is not available right now. You can self-assess this attempt instead.',
            ], 503);
        }

        try {
            $result = $this->ai->evaluateRecitation(new RecitationEvaluationRequest(
                ayahId: $ayah->id,
                expectedArabicText: $ayah->text_arabic_uthmani,
                transcribedText: $request->validated('transcribed_text'),
                audioUrl: $request->validated('audio_url'),
            ));
        } catch (Throwable $e) {
            Log::warning('Recitation evaluation failed', ['ayah_id' => $ayah->id, 'error' => $e->getMessage()]);

            return response()->json([
                'message' => 'AI evaluation failed. You can self-assess this attempt instead.',
            ], 503);
        }

        return response()->json([
            'data' => [
                'ayah_id' => $ayah->id,
                'correctness_score' => $result->correctnessScore,
                'wrong_sequence_detected' => $result->wrongSequenceDetected,
                'missing_words' => $result->missingWords,
                'extra_words' => $result->extraWords,
                'repeated_words' => $result->repeatedWords,
                'pronunciation_confidence' => $result->pronunciationConfidence,
                'provider_name' => $result->providerName,
            ],
        ]);
    }
}
