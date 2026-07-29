<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Quran\QuranContentRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AyahResource;
use App\Models\Ayah;
use App\Models\Surah;
use Illuminate\Http\Request;

class AyahController extends Controller
{
    public function __construct(private readonly QuranContentRepositoryInterface $quran) {}

    public function index(int $surahNumber)
    {
        $surah = Surah::where('number', $surahNumber)->firstOrFail();

        return AyahResource::collection(
            $surah->ayat()
                ->with(['surah', 'translations' => fn ($q) => $q->where('locale', 'ms')])
                ->orderBy('number_in_surah')
                ->get()
        );
    }

    public function show(int $surahNumber, int $ayahNumber)
    {
        $ayah = Ayah::query()
            ->whereHas('surah', fn ($q) => $q->where('number', $surahNumber))
            ->where('number_in_surah', $ayahNumber)
            ->with('surah')
            ->firstOrFail();

        return AyahResource::make($ayah);
    }

    public function translation(Request $request, int $surahNumber, int $ayahNumber)
    {
        $locale = $request->query('locale', 'ms');

        return response()->json([
            'data' => [
                'surah_number' => $surahNumber,
                'number_in_surah' => $ayahNumber,
                'locale' => $locale,
                'translation_text' => $this->quran->getTranslation($surahNumber, $ayahNumber, $locale),
            ],
        ]);
    }
}
