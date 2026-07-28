<?php

namespace Tests\Unit;

use App\Enums\MemorisationClassification;
use App\Enums\ReviewIntervalStage;
use App\Models\MemorisationRecord;
use App\Models\ReviewLog;
use App\Services\SpacedRepetitionScheduler;
use Tests\TestCase;

class SpacedRepetitionSchedulerTest extends TestCase
{
    private SpacedRepetitionScheduler $scheduler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scheduler = new SpacedRepetitionScheduler;
    }

    public function test_success_advances_one_stage(): void
    {
        $this->assertSame(
            ReviewIntervalStage::ThreeDays,
            $this->scheduler->nextStageOnSuccess(ReviewIntervalStage::OneDay)
        );
    }

    public function test_success_caps_at_the_final_stage(): void
    {
        $this->assertSame(
            ReviewIntervalStage::ThreeSixtyFiveDays,
            $this->scheduler->nextStageOnSuccess(ReviewIntervalStage::ThreeSixtyFiveDays)
        );
    }

    public function test_minor_mistake_steps_back_one_stage(): void
    {
        $log = $this->makeLog(isCorrect: false, correctnessScore: 85.0);

        $this->assertSame(
            ReviewIntervalStage::SevenDays,
            $this->scheduler->nextStageOnMistake(ReviewIntervalStage::FourteenDays, $log)
        );
    }

    public function test_major_mistake_resets_to_immediate(): void
    {
        $log = $this->makeLog(isCorrect: false, correctnessScore: 40.0);

        $this->assertSame(
            ReviewIntervalStage::Immediate,
            $this->scheduler->nextStageOnMistake(ReviewIntervalStage::NinetyDays, $log)
        );
    }

    public function test_mistake_without_a_correctness_score_only_steps_back_one_stage(): void
    {
        $log = $this->makeLog(isCorrect: false, correctnessScore: null);

        $this->assertSame(
            ReviewIntervalStage::ThirtyDays,
            $this->scheduler->nextStageOnMistake(ReviewIntervalStage::SixtyDays, $log)
        );
    }

    public function test_correct_recall_increases_the_score(): void
    {
        $record = $this->makeRecord(score: 40, lastRecallAt: null);
        $log = $this->makeLog(isCorrect: true, correctnessScore: 95.0, confidence: 5, timeMs: 1000);

        $newScore = $this->scheduler->recalculateMemoryStrength($record, $log);

        $this->assertGreaterThan(40, $newScore);
        $this->assertLessThanOrEqual(100, $newScore);
    }

    public function test_mistake_decreases_the_score(): void
    {
        $record = $this->makeRecord(score: 60, lastRecallAt: null);
        $log = $this->makeLog(isCorrect: false, correctnessScore: 30.0);

        $newScore = $this->scheduler->recalculateMemoryStrength($record, $log);

        $this->assertLessThan(60, $newScore);
        $this->assertGreaterThanOrEqual(0, $newScore);
    }

    public function test_score_never_exceeds_the_valid_range(): void
    {
        $record = $this->makeRecord(score: 99, lastRecallAt: null);
        $log = $this->makeLog(isCorrect: true, correctnessScore: 100.0, confidence: 5, timeMs: 500);

        $this->assertLessThanOrEqual(100, $this->scheduler->recalculateMemoryStrength($record, $log));

        $record2 = $this->makeRecord(score: 2, lastRecallAt: null);
        $log2 = $this->makeLog(isCorrect: false, correctnessScore: 0.0);

        $this->assertGreaterThanOrEqual(0, $this->scheduler->recalculateMemoryStrength($record2, $log2));
    }

    public function test_score_decays_the_longer_it_has_been_since_the_last_recall(): void
    {
        $recentRecord = $this->makeRecord(score: 80, lastRecallAt: now()->subDay(), stage: ReviewIntervalStage::SevenDays);
        $staleRecord = $this->makeRecord(score: 80, lastRecallAt: now()->subDays(60), stage: ReviewIntervalStage::SevenDays);
        $log = $this->makeLog(isCorrect: true, correctnessScore: 90.0, confidence: 4, timeMs: 2000);

        $recentScore = $this->scheduler->recalculateMemoryStrength($recentRecord, clone $log);
        $staleScore = $this->scheduler->recalculateMemoryStrength($staleRecord, clone $log);

        $this->assertGreaterThan($staleScore, $recentScore);
    }

    public function test_sabak_promotes_to_sabqi_after_first_recall(): void
    {
        $record = $this->makeRecord(score: 10, recallCount: 1, classification: MemorisationClassification::Sabak);

        $this->assertSame(MemorisationClassification::Sabqi, $this->scheduler->determineClassification($record));
    }

    public function test_sabqi_promotes_to_manzil_once_strong_and_long_interval(): void
    {
        $record = $this->makeRecord(
            score: 85,
            classification: MemorisationClassification::Sabqi,
            stage: ReviewIntervalStage::ThirtyDays,
        );

        $this->assertSame(MemorisationClassification::Manzil, $this->scheduler->determineClassification($record));
    }

    public function test_sabqi_does_not_promote_if_interval_is_still_short(): void
    {
        $record = $this->makeRecord(
            score: 90,
            classification: MemorisationClassification::Sabqi,
            stage: ReviewIntervalStage::SevenDays,
        );

        $this->assertSame(MemorisationClassification::Sabqi, $this->scheduler->determineClassification($record));
    }

    public function test_manzil_demotes_to_sabqi_when_score_drops(): void
    {
        $record = $this->makeRecord(
            score: 40,
            classification: MemorisationClassification::Manzil,
            stage: ReviewIntervalStage::ThirtyDays,
        );

        $this->assertSame(MemorisationClassification::Sabqi, $this->scheduler->determineClassification($record));
    }

    private function makeRecord(
        int $score,
        ?\DateTimeInterface $lastRecallAt = null,
        int $recallCount = 0,
        MemorisationClassification $classification = MemorisationClassification::Sabqi,
        ReviewIntervalStage $stage = ReviewIntervalStage::OneDay,
    ): MemorisationRecord {
        $record = new MemorisationRecord;
        $record->memory_strength_score = $score;
        $record->last_recall_at = $lastRecallAt;
        $record->recall_count = $recallCount;
        $record->classification = $classification;
        $record->current_interval_stage = $stage;

        return $record;
    }

    private function makeLog(
        bool $isCorrect,
        ?float $correctnessScore,
        ?int $confidence = null,
        ?int $timeMs = null,
    ): ReviewLog {
        $log = new ReviewLog;
        $log->is_correct = $isCorrect;
        $log->correctness_score = $correctnessScore;
        $log->confidence_level = $confidence;
        $log->time_to_recall_ms = $timeMs;
        $log->attempted_at = now();

        return $log;
    }
}
