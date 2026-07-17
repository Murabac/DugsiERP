<?php

namespace App\Support;

use App\Models\Staff;
use App\Models\StaffWebauthnCredential;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * WebAuthn stand-in for automated tests (no real authenticator).
 */
class TestingStaffWebAuthn extends StaffWebAuthn
{
    public function completeRegistration(Staff $staff, array $credential): void
    {
        $challenge = Cache::pull($this->challengeKey('reg', $staff->id));
        if (! is_string($challenge) || $challenge === '') {
            throw ValidationException::withMessages([
                'webauthn' => 'Registration challenge expired. Refresh and try again.',
            ]);
        }

        $id = (string) ($credential['id'] ?? '');
        if ($id === '') {
            throw ValidationException::withMessages([
                'webauthn' => 'Missing credential id.',
            ]);
        }

        StaffWebauthnCredential::query()->updateOrCreate(
            ['credential_id' => $id],
            [
                'staff_id' => $staff->id,
                'public_key' => base64_encode('test-public-key'),
                'sign_count' => 0,
                'transports' => ['internal'],
                'user_handle' => $this->b64url($this->userHandle($staff)),
            ]
        );
    }

    public function completeAssertion(Staff $staff, array $credential): void
    {
        $challenge = Cache::pull($this->challengeKey('auth', $staff->id));
        if (! is_string($challenge) || $challenge === '') {
            throw ValidationException::withMessages([
                'webauthn' => 'Check-in challenge expired. Try again.',
            ]);
        }

        $id = (string) ($credential['id'] ?? '');
        $exists = StaffWebauthnCredential::query()
            ->where('staff_id', $staff->id)
            ->where('credential_id', $id)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'webauthn' => 'Unknown biometric credential.',
            ]);
        }
    }
}
