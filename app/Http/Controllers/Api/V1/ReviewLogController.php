<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MemorisationClassification;
use App\Enums\ReviewIntervalStage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreReviewLogRequest;
use App\Http\Resources\Api\V1\ReviewLogResource;
use App\Models\MemorisationRecord;
use App\Models\ReviewLog;
use App\Models\ReviewSession;
use App\Services\SpacedRepetitionScheduler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReviewLogController extends Controller
{
    public function __construct(private readonly SpacedRepetitionScheduler $scheduler) {}

    public function index(Request $request, int $reviewSessionId)
    {
        $session = ReviewSession::where('user_id', Auth::id())->findOrFail($reviewSessionId);

        return ReviewLogResource::collection(
            $session->reviewLogs()->with('memorisationRecord')->orderBy('attempted_at')->get()
        );
    }

    public function store(StoreReviewLogRequest $request, int $reviewSessionId)
    {
        $session = ReviewSession::where('user_id', Auth::id())->findOrFail($reviewSessionId);

        $log = DB::transaction(function () use ($request, $session) {
            $record = MemorisationRecord::firstOrCreate(
                ['user_id' => Auth::id(), 'ayah_id' => $request->validated('ayah_id')],
                [
                    'memory_strength_score' => 0,
                    'recall_count' => 0,
                    'mistake_count' => 0,
                    'current_interval_stage' => ReviewIntervalStage::Immediate,
                    'classification' => MemorisationClassification::Sabak,
                    'next_review_date' => now()->toDateString(),
                ]
            );

            $log = new ReviewLog([
                'ayah_id' => $request->validated('ayah_id'),
                'attempted_at' => now(),
                'is_correct' => $request->validated('is_correct'),
                'correctness_score' => $request->validated('correctness_score'),
                'time_to_recall_ms' => $request->validated('time_to_recall_ms'),
                'confidence_level' => $request->validated('confidence_level'),
                'ai_provider_used' => $request->validated('ai_provider_used'),
                'ai_evaluation_result' => $request->validated('ai_evaluation_result'),
            ]);
            $log->reviewSession()->associate($session);
            $log->memorisationRecord()->associate($record);
            $log->save();

            $this->scheduler->processAttempt($record, $log);

            $session->increment('total_ayat_reviewed');

            return $log;
        });

        return ReviewLogResource::make($log->load('memorisationRecord'))
            ->response()
            ->setStatusCode(201);
    }
}
