<?php

namespace Tests\Feature;

use App\Models\Ayah;
use App\Models\Surah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReviewLogFlowTest extends TestCase
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
            'text_arabic_uthmani' => 'بِسْمِ ٱللَّهِ',
            'juz_number' => 1,
            'hizb_number' => 1,
            'page_number' => 1,
            'ruku_number' => 1,
            'is_sajda' => false,
        ]);
    }

    public function test_submitting_a_review_log_creates_and_updates_the_memorisation_record(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $session = $this->postJson('/api/v1/review-sessions', ['session_type' => 'sabak'])
            ->assertCreated()
            ->json('data');

        $response = $this->postJson("/api/v1/review-sessions/{$session['id']}/logs", [
            'ayah_id' => $this->ayah->id,
            'is_correct' => true,
            'correctness_score' => 95,
            'time_to_recall_ms' => 1200,
            'confidence_level' => 5,
        ])->assertCreated();

        $response->assertJsonPath('data.interval_stage_before', 'immediate');
        $response->assertJsonPath('data.interval_stage_after', '1d');
        $response->assertJsonPath('data.memorisation_record.classification', 'sabqi');
        $response->assertJsonPath('data.memorisation_record.recall_count', 1);

        $this->assertDatabaseHas('memorisation_records', [
            'ayah_id' => $this->ayah->id,
            'classification' => 'sabqi',
            'current_interval_stage' => '1d',
            'recall_count' => 1,
            'mistake_count' => 0,
        ]);

        $this->assertDatabaseHas('review_sessions', [
            'id' => $session['id'],
            'total_ayat_reviewed' => 1,
        ]);
    }

    public function test_a_major_mistake_resets_the_interval_to_immediate(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $session = $this->postJson('/api/v1/review-sessions', ['session_type' => 'sabak'])->json('data');

        // First build up some progress...
        $this->postJson("/api/v1/review-sessions/{$session['id']}/logs", [
            'ayah_id' => $this->ayah->id,
            'is_correct' => true,
            'correctness_score' => 95,
            'confidence_level' => 5,
        ])->assertCreated();

        // ...then a severe mistake should reset the interval, not just step back.
        $response = $this->postJson("/api/v1/review-sessions/{$session['id']}/logs", [
            'ayah_id' => $this->ayah->id,
            'is_correct' => false,
            'correctness_score' => 35,
            'confidence_level' => 1,
        ])->assertCreated();

        $response->assertJsonPath('data.memorisation_record.current_interval_stage', 'immediate');
        $response->assertJsonPath('data.memorisation_record.mistake_count', 1);
    }

    public function test_review_logs_require_authentication(): void
    {
        $session = ['id' => 1];

        $this->postJson("/api/v1/review-sessions/{$session['id']}/logs", [
            'ayah_id' => $this->ayah->id,
            'is_correct' => true,
        ])->assertUnauthorized();
    }
}
