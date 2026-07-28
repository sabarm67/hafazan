<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AyahResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'surah_number' => $this->surah->number,
            'number_in_surah' => $this->number_in_surah,
            'number_in_quran' => $this->number_in_quran,
            'text_arabic_uthmani' => $this->text_arabic_uthmani,
            'juz_number' => $this->juz_number,
            'hizb_number' => $this->hizb_number,
            'page_number' => $this->page_number,
            'ruku_number' => $this->ruku_number,
            'is_sajda' => $this->is_sajda,
            'audio_url' => $this->audioUrl(),
        ];
    }

    /**
     * Deterministic CDN URL pattern — same one
     * TanzilAlQuranCloudRepository::getAudioUrl() uses — built directly here
     * to avoid a redundant DB round-trip per ayah.
     */
    private function audioUrl(): string
    {
        $base = config('quran.tanzil_alquran_cloud.audio_base_url');
        $reciter = config('quran.default_reciter');

        return "{$base}/128/{$reciter}/{$this->number_in_quran}.mp3";
    }
}
