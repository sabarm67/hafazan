<?php

namespace App\Services;

use App\Enums\MemorisationClassification;
use App\Enums\ReviewIntervalStage;
use App\Models\MemorisationRecord;
use App\Models\ReviewLog;

/**
 * The Adaptive Hifz Engine's scheduling core.
 *
 * The exact formulas below are a deliberately simple, explainable v1 — real
 * tuning constants that should move based on retention data once the app has
 * users, not a settled algorithm. Every magic number is named so it can be
 * tuned without reading the method bodies.
 *
 * Model: each review attempt (a ReviewLog) both reveals how well a
 * decayed/consolidated the memory currently is, and produces a new score.
 * `processAttempt()` is the single entry point every controller should call
 * — the sub-methods are exposed separately mainly so they're unit-testable
 * in isolation.
 */
class SpacedRepetitionScheduler
{
    private const int MIN_SCORE = 0;

    private const int MAX_SCORE = 100;

    /** A mistake below this correctness score resets the interval to Immediate instead of just stepping back one stage. */
    private const float MAJOR_MISTAKE_THRESHOLD = 70.0;

    /** Sabqi -> Manzil requires the score at or above this... */
    private const int MANZIL_PROMOTION_SCORE = 80;

    /** ...and the interval stage already at or beyond one of these. */
    private const array MANZIL_PROMOTION_STAGES = [
        ReviewIntervalStage::ThirtyDays,
        ReviewIntervalStage::SixtyDays,
        ReviewIntervalStage::NinetyDays,
        ReviewIntervalStage::OneEightyDays,
        ReviewIntervalStage::ThreeSixtyFiveDays,
    ];

    /** Manzil -> Sabqi (demotion) once the score drops below this. */
    private const int MANZIL_DEMOTION_SCORE = 55;

    /**
     * Apply one recall attempt's outcome to its memorisation record: update
     * the memory-strength score, move the interval stage, recompute the
     * Sabak/Sabqi/Manzil classification, and persist both the record and the
     * log's before/after stage. Returns the updated record.
     */
    public function processAttempt(MemorisationRecord $record, ReviewLog $log): MemorisationRecord
    {
        $log->interval_stage_before = $record->current_interval_stage;

        $record->memory_strength_score = $this->recalculateMemoryStrength($record, $log);
        $record->current_interval_stage = $log->is_correct
            ? $this->nextStageOnSuccess($record->current_interval_stage)
            : $this->nextStageOnMistake($record->current_interval_stage, $log);
        $record->next_review_date = now()->addDays($record->current_interval_stage->days())->toDateString();
        $record->last_recall_at = $log->attempted_at;
        $record->recall_count++;
        if (! $log->is_correct) {
            $record->mistake_count++;
        }

        $newClassification = $this->determineClassification($record);
        if ($newClassification !== $record->classification) {
            $record->classification = $newClassification;
            $record->classification_updated_at = now();
        }

        $record->save();

        $log->interval_stage_after = $record->current_interval_stage;
        $log->save();

        return $record;
    }

    public function nextStageOnSuccess(ReviewIntervalStage $currentStage): ReviewIntervalStage
    {
        $ladder = ReviewIntervalStage::cases();
        $index = array_search($currentStage, $ladder, true);

        return $ladder[min($index + 1, count($ladder) - 1)];
    }

    public function nextStageOnMistake(ReviewIntervalStage $currentStage, ReviewLog $log): ReviewIntervalStage
    {
        if ($log->correctness_score !== null && (float) $log->correctness_score < self::MAJOR_MISTAKE_THRESHOLD) {
            return ReviewIntervalStage::Immediate;
        }

        $ladder = ReviewIntervalStage::cases();
        $index = array_search($currentStage, $ladder, true);

        return $ladder[max($index - 1, 0)];
    }

    /**
     * Decay the record's current score for time elapsed since the last
     * recall, then apply this attempt's outcome. Decay half-life scales with
     * the interval stage the learner had reached — a longer-trusted interval
     * assumes slower forgetting.
     */
    public function recalculateMemoryStrength(MemorisationRecord $record, ReviewLog $log): int
    {
        $daysSinceLastRecall = $record->last_recall_at
            ? (int) $record->last_recall_at->diffInDays($log->attempted_at)
            : 0;

        $decayed = $this->applyDecay(
            $record->memory_strength_score,
            $daysSinceLastRecall,
            $record->current_interval_stage
        );

        $newScore = $log->is_correct
            ? $decayed + $this->recallGain($log->confidence_level, $log->time_to_recall_ms)
            : $decayed - $this->mistakePenalty($log->correctness_score);

        return (int) round(max(self::MIN_SCORE, min(self::MAX_SCORE, $newScore)));
    }

    public function determineClassification(MemorisationRecord $record): MemorisationClassification
    {
        $current = $record->classification;

        if ($current === MemorisationClassification::Sabak && $record->recall_count >= 1) {
            return MemorisationClassification::Sabqi;
        }

        if ($current === MemorisationClassification::Sabqi
            && $record->memory_strength_score >= self::MANZIL_PROMOTION_SCORE
            && in_array($record->current_interval_stage, self::MANZIL_PROMOTION_STAGES, true)
        ) {
            return MemorisationClassification::Manzil;
        }

        if ($current === MemorisationClassification::Manzil
            && $record->memory_strength_score < self::MANZIL_DEMOTION_SCORE
        ) {
            return MemorisationClassification::Sabqi;
        }

        return $current;
    }

    private function applyDecay(int $score, int $daysSinceLastRecall, ReviewIntervalStage $stage): float
    {
        if ($daysSinceLastRecall <= 0) {
            return $score;
        }

        $halfLifeDays = max(1, $stage->days() * 2);

        return $score * (0.5 ** ($daysSinceLastRecall / $halfLifeDays));
    }

    private function recallGain(?int $confidenceLevel, ?int $timeToRecallMs): float
    {
        $confidence = $confidenceLevel ?? 3; // 1-5 self-reported; default to neutral when not sent
        $speedPenalty = $timeToRecallMs !== null ? min(8.0, $timeToRecallMs / 2500) : 4.0;

        return max(4.0, 6 + ($confidence * 2) - $speedPenalty);
    }

    private function mistakePenalty(?float $correctnessScore): float
    {
        if ($correctnessScore === null) {
            return 20.0; // no AI/teacher score supplied — assume a moderate mistake
        }

        return max(10.0, min(40.0, 10 + (100 - $correctnessScore) / 4));
    }
}
