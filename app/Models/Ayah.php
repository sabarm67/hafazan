<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ayah extends Model
{
    protected $table = 'ayat';

    protected $fillable = [
        'surah_id',
        'number_in_surah',
        'number_in_quran',
        'text_arabic_uthmani',
        'juz_number',
        'hizb_number',
        'page_number',
        'ruku_number',
        'is_sajda',
        'audio_url',
    ];

    protected function casts(): array
    {
        return [
            'is_sajda' => 'boolean',
        ];
    }

    public function surah(): BelongsTo
    {
        return $this->belongsTo(Surah::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(AyahTranslation::class);
    }

    public function words(): HasMany
    {
        return $this->hasMany(AyahWord::class);
    }

    public function memorisationRecords(): HasMany
    {
        return $this->hasMany(MemorisationRecord::class);
    }
}
