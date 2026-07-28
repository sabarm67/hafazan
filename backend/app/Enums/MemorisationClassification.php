<?php

namespace App\Enums;

/**
 * Traditional three-tier Hifz review classification for a single ayah/user pair.
 */
enum MemorisationClassification: string
{
    case Sabak = 'sabak';   // new memorisation, not yet consolidated
    case Sabqi = 'sabqi';   // recent memorisation under active short-interval review
    case Manzil = 'manzil'; // long-term retained, on extended-interval review

    public function label(): string
    {
        return match ($this) {
            self::Sabak => 'Sabak',
            self::Sabqi => 'Sabqi',
            self::Manzil => 'Manzil',
        };
    }
}
