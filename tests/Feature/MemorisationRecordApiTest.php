<?php

namespace Tests\Feature;

use App\Enums\MemorisationClassification;
use App\Enums\ReviewIntervalStage;
use App\Models\Ayah;
use App\Models\MemorisationRecord;
use App\Models\Surah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MemorisationRecordApiTest extends TestCase
{
    use RefreshDatabase;

    private Surah $surah;

    protected function setUp(): void
    {
        parent::setUp();

        $this->surah = Surah::create([
            'number' => 1,
            'name_arabic' => 'الفاتحة',
            'name_transliteration' => 'Al-Fatihah',
            'name_translation_ms' => 'Pembukaan',
            'revelation_type' => 'meccan',
            'total_ayat' => 7,
        ]);
    }

    public function test_store_is_idempotent_for_the_same_ayah(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $ayah = $this->makeAyah();

        $first = $this->postJson('/api/v1/memorisation-records', ['ayah_id' => $ayah->id])->assertCreated();
        $second = $this->postJson('/api/v1/memorisation-records', ['ayah_id' => $ayah->id])->assertOk();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, MemorisationRecord::count());
    }

    public function test_index_filters_by_due_date(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $dueAyah = $this->makeAyah(1);
        $notDueAyah = $this->makeAyah(2);

        MemorisationRecord::create([
            'user_id' => $user->id,
            'ayah_id' => $dueAyah->id,
            'memory_strength_score' => 10,
            'recall_count' => 1,
            'mistake_count' => 0,
            'current_interval_stage' => ReviewIntervalStage::OneDay,
            'classification' => MemorisationClassification::Sabqi,
            'next_review_date' => now()->subDay()->toDateString(),
        ]);

        MemorisationRecord::create([
            'user_id' => $user->id,
            'ayah_id' => $notDueAyah->id,
            'memory_strength_score' => 80,
            'recall_count' => 5,
            'mistake_count' => 0,
            'current_interval_stage' => ReviewIntervalStage::ThirtyDays,
            'classification' => MemorisationClassification::Sabqi,
            'next_review_date' => now()->addDays(20)->toDateString(),
        ]);

        $response = $this->getJson('/api/v1/memorisation-records?due=1')->assertOk();

        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.ayah_id', $dueAyah->id);
    }

    public function test_update_resets_the_record_for_immediate_review(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $ayah = $this->makeAyah();

        $record = MemorisationRecord::create([
            'user_id' => $user->id,
            'ayah_id' => $ayah->id,
            'memory_strength_score' => 50,
            'recall_count' => 3,
            'mistake_count' => 0,
            'current_interval_stage' => ReviewIntervalStage::ThirtyDays,
            'classification' => MemorisationClassification::Sabqi,
            'next_review_date' => now()->addDays(25)->toDateString(),
        ]);

        $this->putJson("/api/v1/memorisation-records/{$record->id}", ['reset_for_review' => true])
            ->assertOk()
            ->assertJsonPath('data.next_review_date', now()->toDateString());
    }

    public function test_records_belonging_to_other_users_are_not_visible(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $ayah = $this->makeAyah();

        $record = MemorisationRecord::create([
            'user_id' => $owner->id,
            'ayah_id' => $ayah->id,
            'memory_strength_score' => 10,
            'recall_count' => 1,
            'mistake_count' => 0,
            'current_interval_stage' => ReviewIntervalStage::OneDay,
            'classification' => MemorisationClassification::Sabqi,
            'next_review_date' => now()->toDateString(),
        ]);

        Sanctum::actingAs($intruder);

        $this->getJson("/api/v1/memorisation-records/{$record->id}")->assertNotFound();
    }

    private function makeAyah(int $numberInQuran = 1): Ayah
    {
        return Ayah::create([
            'surah_id' => $this->surah->id,
            'number_in_surah' => $numberInQuran,
            'number_in_quran' => $numberInQuran,
            'text_arabic_uthmani' => 'بِسْمِ ٱللَّهِ',
            'juz_number' => 1,
            'hizb_number' => 1,
            'page_number' => 1,
            'ruku_number' => 1,
            'is_sajda' => false,
        ]);
    }
}
