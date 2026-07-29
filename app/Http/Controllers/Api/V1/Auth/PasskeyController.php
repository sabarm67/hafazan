<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PasskeyResource;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Passkeys\Actions\DeletePasskey;
use Laravel\Passkeys\Actions\GenerateRegistrationOptions;
use Laravel\Passkeys\Actions\GenerateVerificationOptions;
use Laravel\Passkeys\Actions\StorePasskey;
use Laravel\Passkeys\Actions\VerifyPasskey;
use Laravel\Passkeys\Exceptions\InvalidPasskeyException;
use Laravel\Passkeys\Http\Requests\PasskeyRegistrationRequest;
use Laravel\Passkeys\Http\Requests\PasskeyVerificationRequest;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Passkeys;
use Laravel\Passkeys\Support\WebAuthn;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

/**
 * Reuses laravel/passkeys' Action classes (the actual WebAuthn ceremony
 * logic, delegating to the audited web-auth/webauthn-lib underneath) but
 * wires the results into this app's own routes/responses instead of the
 * package's built-in ones — see config/passkeys.php for why.
 */
class PasskeyController extends Controller
{
    /**
     * Registration options for the currently authenticated user (biometric
     * setup from Settings). A resident/discoverable credential is required
     * (see GenerateRegistrationOptions), so login later needs no email.
     */
    public function registrationOptions(Request $request, GenerateRegistrationOptions $generate)
    {
        $options = $generate($request->user());

        $request->session()->put('passkey.registration_options', WebAuthn::toJson($options));

        return response()->json(['data' => WebAuthn::toBrowserArray($options)]);
    }

    public function register(PasskeyRegistrationRequest $request, StorePasskey $storePasskey)
    {
        try {
            $passkey = $storePasskey(
                $request->user(),
                $request->string('name')->toString(),
                $request->credential(),
                $request->registrationOptions(),
            );
        } catch (InvalidPasskeyException $e) {
            return response()->json(['message' => $this->firstValidationMessage($e)], HttpResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $e) {
            // The underlying webauthn-lib validator throws its own exception
            // types (not wrapped in InvalidPasskeyException) on a malformed
            // or tampered attestation — never leak that as a raw 500.
            Log::warning('Passkey registration failed verification', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Could not verify this passkey. Please try again.'], HttpResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        return PasskeyResource::make($passkey)->response()->setStatusCode(HttpResponse::HTTP_CREATED);
    }

    public function index(Request $request)
    {
        return PasskeyResource::collection(
            $request->user()->passkeys()->orderByDesc('created_at')->get()
        );
    }

    public function destroy(Request $request, Passkey $passkey, DeletePasskey $deletePasskey)
    {
        abort_unless($passkey->user_id === $request->user()->getKey(), HttpResponse::HTTP_FORBIDDEN);

        $deletePasskey($request->user(), $passkey);

        return response()->noContent();
    }

    /**
     * Login options for a signed-out visitor — no email/username needed,
     * since registration required a resident/discoverable credential. The
     * browser's authenticator UI itself lists which account(s) it holds a
     * passkey for on this site.
     */
    public function loginOptions(Request $request, GenerateVerificationOptions $generate)
    {
        $options = $generate();

        $request->session()->put('passkey.verification_options', WebAuthn::toJson($options));

        return response()->json(['data' => WebAuthn::toBrowserArray($options)]);
    }

    public function login(PasskeyVerificationRequest $request, VerifyPasskey $verify)
    {
        try {
            $passkey = $verify($request->credential(), $request->verificationOptions());
        } catch (InvalidPasskeyException $e) {
            return response()->json(['message' => $this->firstValidationMessage($e)], HttpResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $e) {
            Log::warning('Passkey login failed verification', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Could not verify this passkey. Please try again.'], HttpResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (! Passkeys::allowsLogin($request, $passkey)) {
            return response()->json(['message' => 'Unable to sign in with this passkey.'], HttpResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        // $passkey->user is typed against the package's generic PasskeyUser
        // contract, not this app's concrete User — a real type narrowing at
        // runtime (User is the only model implementing PasskeyUser here),
        // not just satisfying static analysis.
        if (! $passkey->user instanceof User) {
            Log::error('Passkey resolved to a non-User model', ['user_class' => get_class($passkey->user)]);

            return response()->json(['message' => 'Could not verify this passkey. Please try again.'], HttpResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Same session-issuing tail as LoginController/RegisterController —
        // a passkey login results in the same kind of Sanctum SPA cookie
        // session as password login, not a separate auth mechanism.
        Auth::guard('web')->login($passkey->user);
        $request->session()->regenerate();

        return UserResource::make($passkey->user->load('roles'));
    }

    /**
     * InvalidPasskeyException extends ValidationException, whose own
     * getMessage() returns a generic "The given data was invalid." rather
     * than the specific text passed to withMessages() — pull that back out.
     */
    private function firstValidationMessage(InvalidPasskeyException $e): string
    {
        $errors = $e->errors();

        return $errors['credential'][0] ?? 'Unable to verify this passkey. Please try again.';
    }
}
