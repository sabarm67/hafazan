<?php

namespace Tests\Feature;

use App\Models\Ayah;
use App\Models\Surah;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AyahTranslationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_translation_endpoint_calls_the_configured_locale_edition(): void
    {
        Http::fake([
            'api.alquran.cloud/*' => Http::response([
                'code' => 200,
                'status' => 'OK',
                'data' => ['text' => 'Dengan nama Allah, Yang Maha Pemurah, lagi Maha Mengasihani.'],
            ]),
        ]);

        $surah = Surah::create([
            'number' => 1,
            'name_arabic' => 'الفاتحة',
            'name_transliteration' => 'Al-Fatihah',
            'name_translation_ms' => 'Pembukaan',
            'revelation_type' => 'meccan',
            'total_ayat' => 7,
        ]);

        Ayah::create([
            'surah_id' => $surah->id,
            'number_in_surah' => 1,
            'number_in_quran' => 1,
            'text_arabic_uthmani' => 'بِسْمِ ٱللَّهِ',
            'juz_number' => 1,
            'hizb_number' => 1,
            'page_number' => 1,
            'ruku_number' => 1,
            'is_sajda' => false,
        ]);

        $response = $this->getJson('/api/v1/surahs/1/ayat/1/translation?locale=ms')->assertOk();

        $response->assertJsonPath('data.translation_text', 'Dengan nama Allah, Yang Maha Pemurah, lagi Maha Mengasihani.');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'ms.basmeih'));
    }

    public function test_ms_translation_is_served_locally_without_an_http_call_when_bundled(): void
    {
        Http::fake();

        $surah = Surah::create([
            'number' => 1,
            'name_arabic' => 'الفاتحة',
            'name_transliteration' => 'Al-Fatihah',
            'name_translation_ms' => 'Pembukaan',
            'revelation_type' => 'meccan',
            'total_ayat' => 7,
        ]);

        $ayah = Ayah::create([
            'surah_id' => $surah->id,
            'number_in_surah' => 1,
            'number_in_quran' => 1,
            'text_arabic_uthmani' => 'بِسْمِ ٱللَّهِ',
            'juz_number' => 1,
            'hizb_number' => 1,
            'page_number' => 1,
            'ruku_number' => 1,
            'is_sajda' => false,
        ]);

        $ayah->translations()->create([
            'locale' => 'ms',
            'translation_text' => 'Dengan nama Allah, Yang Maha Pemurah, lagi Maha Mengasihani.',
            'source' => 'alquran.cloud (ms.basmeih)',
        ]);

        $response = $this->getJson('/api/v1/surahs/1/ayat/1/translation?locale=ms')->assertOk();

        $response->assertJsonPath('data.translation_text', 'Dengan nama Allah, Yang Maha Pemurah, lagi Maha Mengasihani.');

        Http::assertNothingSent();
    }
}
