<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReviewSessionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_session_can_be_started_and_completed(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $session = $this->postJson('/api/v1/review-sessions', ['session_type' => 'sabqi'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'in_progress')
            ->json('data');

        $this->putJson("/api/v1/review-sessions/{$session['id']}", ['status' => 'completed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.ended_at', fn ($value) => $value !== null);
    }

    public function test_invalid_session_type_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/review-sessions', ['session_type' => 'not-a-real-type'])
            ->assertStatus(422);
    }

    public function test_sessions_require_authentication(): void
    {
        $this->postJson('/api/v1/review-sessions', ['session_type' => 'sabak'])->assertUnauthorized();
    }
}
