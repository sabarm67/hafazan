<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the parts of the passkey flow that don't require simulating a real
 * authenticator's cryptographic signature (options generation, auth/ownership
 * boundaries). The actual WebAuthn attestation/assertion verification is
 * delegated to web-auth/webauthn-lib, an independently audited library —
 * re-testing its crypto here would just be re-testing someone else's tests.
 */
class PasskeyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Passkey options endpoints write to the session (challenge storage
        // between the "generate options" and "verify" requests). Sanctum's
        // stateful-request middleware only starts a session for requests it
        // recognizes as coming from a configured frontend origin (see
        // EnsureFrontendRequestsAreStateful::fromFrontend()) - the test
        // client sends no Origin/Referer by default, so without this every
        // session()->put()/session()->pull() call would hit "Session store
        // not set on request." This must match a configured
        // SANCTUM_STATEFUL_DOMAINS entry (see .env.example).
        $this->withHeader('Referer', 'http://localhost:5173');
    }

    public function test_login_options_are_public_and_well_formed(): void
    {
        $response = $this->getJson('/api/v1/auth/passkeys/login-options');

        $response->assertOk()->assertJsonStructure([
            'data' => ['challenge', 'rpId', 'userVerification'],
        ]);
    }

    public function test_registration_options_require_authentication(): void
    {
        $this->getJson('/api/v1/auth/passkeys/registration-options')->assertUnauthorized();
    }

    public function test_registration_options_are_well_formed_for_an_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/auth/passkeys/registration-options');

        $response->assertOk()->assertJsonStructure([
            'data' => ['challenge', 'rp', 'user', 'pubKeyCredParams'],
        ]);
        $this->assertSame($user->name, $response->json('data.user.displayName'));
    }

    public function test_index_lists_only_the_current_users_passkeys(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $owner->passkeys()->create(['name' => 'Owner key', 'credential_id' => 'cred-owner', 'credential' => []]);
        $other->passkeys()->create(['name' => 'Other key', 'credential_id' => 'cred-other', 'credential' => []]);

        $response = $this->actingAs($owner)->getJson('/api/v1/auth/passkeys');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        $this->assertContains('Owner key', $names);
        $this->assertNotContains('Other key', $names);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/passkeys')->assertUnauthorized();
    }

    public function test_a_user_cannot_delete_another_users_passkey(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $passkey = $owner->passkeys()->create(['name' => 'Owner key', 'credential_id' => 'cred-x', 'credential' => []]);

        $this->actingAs($intruder)->deleteJson("/api/v1/auth/passkeys/{$passkey->id}")->assertForbidden();
        $this->assertDatabaseHas('passkeys', ['id' => $passkey->id]);
    }

    public function test_a_user_can_delete_their_own_passkey(): void
    {
        $owner = User::factory()->create();
        $passkey = $owner->passkeys()->create(['name' => 'Owner key', 'credential_id' => 'cred-y', 'credential' => []]);

        $this->actingAs($owner)->deleteJson("/api/v1/auth/passkeys/{$passkey->id}")->assertNoContent();
        $this->assertDatabaseMissing('passkeys', ['id' => $passkey->id]);
    }

    public function test_login_with_an_unrecognized_credential_is_rejected_cleanly(): void
    {
        // Prime the session with real login options first (login() pulls
        // and requires them), then send a syntactically valid but bogus
        // credential - this must fail verification cleanly (422), not 500.
        $this->getJson('/api/v1/auth/passkeys/login-options')->assertOk();

        $response = $this->postJson('/api/v1/auth/passkeys/login', [
            'credential' => [
                'id' => 'bm9uZXhpc3RlbnQ',
                'rawId' => 'bm9uZXhpc3RlbnQ',
                'type' => 'public-key',
                'response' => [
                    'clientDataJSON' => base64_encode('{}'),
                    'authenticatorData' => 'AA',
                    'signature' => 'AA',
                ],
            ],
        ]);

        $response->assertUnprocessable();
    }
}
