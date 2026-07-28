<?php

namespace Tests\Feature;

use App\Models\Ayah;
use App\Models\Surah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AiEvaluationApiTest extends TestCase
{
    use RefreshDatabase;

    private Ayah $ayah;

    protected function setUp(): void
    {
        parent::setUp();

        $surah = Surah::create([
            'number' => 1,
            'name_arabic' => 'الفاتحة',
            'name_transliteration' => 'Al-Fatihah',
            'name_translation_ms' => 'Pembukaan',
            'revelation_type' => 'meccan',
            'total_ayat' => 7,
        ]);

        $this->ayah = Ayah::create([
            'surah_id' => $surah->id,
            'number_in_surah' => 1,
            'number_in_quran' => 1,
            'text_arabic_uthmani' => 'بِسْمِ ٱللَّهِ ٱلرَّحْمَٰنِ ٱلرَّحِيمِ',
            'juz_number' => 1,
            'hizb_number' => 1,
            'page_number' => 1,
            'ruku_number' => 1,
            'is_sajda' => false,
        ]);
    }

    public function test_it_evaluates_a_transcribed_recitation_via_claude(): void
    {
        config(['ai.providers.claude.api_key' => 'test-key']);

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    ['text' => json_encode([
                        'correctness_score' => 88,
                        'wrong_sequence_detected' => false,
                        'missing_words' => ['الرحيم'],
                        'extra_words' => [],
                        'repeated_words' => [],
                        'pronunciation_confidence' => 0.9,
                    ])],
                ],
            ]),
        ]);

        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson(
            "/api/v1/surahs/1/ayat/1/evaluate-recitation",
            ['transcribed_text' => 'بسم الله الرحمن']
        )->assertOk();

        $response->assertJsonPath('data.correctness_score', 88);
        $response->assertJsonPath('data.missing_words', ['الرحيم']);
        $response->assertJsonPath('data.provider_name', 'claude');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.anthropic.com')
                && str_contains($request['messages'][0]['content'], 'بسم الله الرحمن');
        });
    }

    public function test_it_returns_503_when_the_provider_is_not_configured(): void
    {
        config(['ai.providers.claude.api_key' => '']);

        Sanctum::actingAs(User::factory()->create());

        $this->postJson(
            "/api/v1/surahs/1/ayat/1/evaluate-recitation",
            ['transcribed_text' => 'بسم الله']
        )->assertStatus(503);
    }

    public function test_it_returns_503_when_claude_errors(): void
    {
        config(['ai.providers.claude.api_key' => 'test-key']);

        Http::fake([
            'api.anthropic.com/*' => Http::response('server error', 500),
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->postJson(
            "/api/v1/surahs/1/ayat/1/evaluate-recitation",
            ['transcribed_text' => 'بسم الله']
        )->assertStatus(503);
    }

    public function test_evaluation_requires_authentication(): void
    {
        $this->postJson(
            "/api/v1/surahs/1/ayat/1/evaluate-recitation",
            ['transcribed_text' => 'بسم الله']
        )->assertUnauthorized();
    }

    public function test_transcribed_text_is_required(): void
    {
        config(['ai.providers.claude.api_key' => 'test-key']);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v1/surahs/1/ayat/1/evaluate-recitation", [])
            ->assertStatus(422);
    }
}
