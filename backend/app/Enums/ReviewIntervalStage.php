<?php

namespace App\Enums;

/**
 * Spaced-repetition interval ladder. Stage transitions and the resulting
 * next_review_date are computed by App\Services\SpacedRepetitionScheduler
 * (Phase 6 — Adaptive Hifz Engine), not by this enum.
 */
enum ReviewIntervalStage: string
{
    case Immediate = 'immediate';
    case OneDay = '1d';
    case ThreeDays = '3d';
    case SevenDays = '7d';
    case FourteenDays = '14d';
    case ThirtyDays = '30d';
    case SixtyDays = '60d';
    case NinetyDays = '90d';
    case OneEightyDays = '180d';
    case ThreeSixtyFiveDays = '365d';

    /** Number of days represented by this stage, used by the scheduler. */
    public function days(): int
    {
        return match ($this) {
            self::Immediate => 0,
            self::OneDay => 1,
            self::ThreeDays => 3,
            self::SevenDays => 7,
            self::FourteenDays => 14,
            self::ThirtyDays => 30,
            self::SixtyDays => 60,
            self::NinetyDays => 90,
            self::OneEightyDays => 180,
            self::ThreeSixtyFiveDays => 365,
        };
    }
}
