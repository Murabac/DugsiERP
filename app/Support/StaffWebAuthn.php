<?php

namespace App\Support;

use App\Models\Staff;
use App\Models\StaffWebauthnCredential;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Uid\Uuid;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\CredentialRecord;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\TrustPath\EmptyTrustPath;

/**
 * WebAuthn ceremony helpers for staff phone check-in.
 * In testing, use TestingStaffWebAuthn (bound in AppServiceProvider).
 */
class StaffWebAuthn
{
    public function creationOptions(Staff $staff): array
    {
        $staff->loadMissing('webauthnCredentials');
        $challenge = random_bytes(32);
        Cache::put($this->challengeKey('reg', $staff->id), $challenge, now()->addMinutes(5));

        $exclude = $staff->webauthnCredentials->map(fn (StaffWebauthnCredential $c) => [
            'type' => 'public-key',
            'id' => $c->credential_id,
        ])->values()->all();

        $userId = $this->userHandle($staff);

        return [
            'challenge' => $this->b64url($challenge),
            'rp' => [
                'name' => config('app.name', 'Dugsi ERP'),
                'id' => $this->rpId(),
            ],
            'user' => [
                'id' => $this->b64url($userId),
                'name' => $staff->employee_code,
                'displayName' => $staff->full_name,
            ],
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7],
                ['type' => 'public-key', 'alg' => -257],
            ],
            'timeout' => 60000,
            'attestation' => 'none',
            'excludeCredentials' => $exclude,
            'authenticatorSelection' => [
                'authenticatorAttachment' => 'platform',
                'userVerification' => 'required',
                'residentKey' => 'preferred',
            ],
        ];
    }

    public function completeRegistration(Staff $staff, array $credential): void
    {
        $challenge = Cache::pull($this->challengeKey('reg', $staff->id));
        if (! is_string($challenge) || $challenge === '') {
            throw ValidationException::withMessages([
                'webauthn' => 'Registration challenge expired. Refresh and try again.',
            ]);
        }

        $publicKeyCredential = $this->deserializeCredential($credential);
        $response = $publicKeyCredential->response;
        if (! $response instanceof AuthenticatorAttestationResponse) {
            throw ValidationException::withMessages([
                'webauthn' => 'Invalid registration response.',
            ]);
        }

        $options = PublicKeyCredentialCreationOptions::create(
            PublicKeyCredentialRpEntity::create(config('app.name', 'Dugsi ERP'), $this->rpId()),
            PublicKeyCredentialUserEntity::create(
                $staff->employee_code,
                $this->userHandle($staff),
                $staff->full_name,
            ),
            $challenge,
            [
                PublicKeyCredentialParameters::create('public-key', -7),
                PublicKeyCredentialParameters::create('public-key', -257),
            ],
            AuthenticatorSelectionCriteria::create(
                authenticatorAttachment: AuthenticatorSelectionCriteria::AUTHENTICATOR_ATTACHMENT_PLATFORM,
                userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
            ),
            PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
            timeout: 60000,
        );

        $factory = new CeremonyStepManagerFactory;
        $factory->setAllowedOrigins([$this->origin()], false);
        $validator = AuthenticatorAttestationResponseValidator::create($factory->creationCeremony());

        try {
            $record = $validator->check($response, $options, $this->rpId());
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'webauthn' => 'Biometric registration failed: '.$e->getMessage(),
            ]);
        }

        StaffWebauthnCredential::query()->updateOrCreate(
            ['credential_id' => $this->b64url($record->publicKeyCredentialId)],
            [
                'staff_id' => $staff->id,
                'public_key' => base64_encode($record->credentialPublicKey),
                'sign_count' => $record->counter,
                'transports' => $record->transports,
                'user_handle' => $this->b64url($record->userHandle),
            ]
        );
    }

    public function requestOptions(Staff $staff): array
    {
        $creds = $staff->webauthnCredentials;
        if ($creds->isEmpty()) {
            throw ValidationException::withMessages([
                'webauthn' => 'No biometric enrolled yet. Register first.',
            ]);
        }

        $challenge = random_bytes(32);
        Cache::put($this->challengeKey('auth', $staff->id), $challenge, now()->addMinutes(5));

        return [
            'challenge' => $this->b64url($challenge),
            'timeout' => 60000,
            'rpId' => $this->rpId(),
            'allowCredentials' => $creds->map(fn (StaffWebauthnCredential $c) => [
                'type' => 'public-key',
                'id' => $c->credential_id,
                'transports' => $c->transports ?? [],
            ])->values()->all(),
            'userVerification' => 'required',
        ];
    }

    public function completeAssertion(Staff $staff, array $credential): void
    {
        $challenge = Cache::pull($this->challengeKey('auth', $staff->id));
        if (! is_string($challenge) || $challenge === '') {
            throw ValidationException::withMessages([
                'webauthn' => 'Check-in challenge expired. Try again.',
            ]);
        }

        $publicKeyCredential = $this->deserializeCredential($credential);
        $response = $publicKeyCredential->response;
        if (! $response instanceof AuthenticatorAssertionResponse) {
            throw ValidationException::withMessages([
                'webauthn' => 'Invalid authentication response.',
            ]);
        }

        $credentialId = $this->b64url($publicKeyCredential->rawId);
        $stored = StaffWebauthnCredential::query()
            ->where('staff_id', $staff->id)
            ->where('credential_id', $credentialId)
            ->first();

        if (! $stored) {
            throw ValidationException::withMessages([
                'webauthn' => 'Unknown biometric credential.',
            ]);
        }

        $options = PublicKeyCredentialRequestOptions::create(
            $challenge,
            rpId: $this->rpId(),
            allowCredentials: [
                PublicKeyCredentialDescriptor::create(
                    PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
                    $this->b64urlDecode($stored->credential_id),
                    $stored->transports ?? []
                ),
            ],
            userVerification: PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED,
            timeout: 60000,
        );

        $record = CredentialRecord::create(
            publicKeyCredentialId: $this->b64urlDecode($stored->credential_id),
            type: PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
            transports: $stored->transports ?? [],
            attestationType: 'none',
            trustPath: EmptyTrustPath::create(),
            aaguid: Uuid::fromString('00000000-0000-0000-0000-000000000000'),
            credentialPublicKey: base64_decode($stored->public_key, true) ?: '',
            userHandle: $stored->user_handle
                ? $this->b64urlDecode($stored->user_handle)
                : $this->userHandle($staff),
            counter: $stored->sign_count,
        );

        $factory = new CeremonyStepManagerFactory;
        $factory->setAllowedOrigins([$this->origin()], false);
        $validator = AuthenticatorAssertionResponseValidator::create($factory->requestCeremony());

        try {
            $updated = $validator->check(
                $record,
                $response,
                $options,
                $this->rpId(),
                $this->userHandle($staff),
            );
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'webauthn' => 'Biometric verification failed: '.$e->getMessage(),
            ]);
        }

        $stored->update(['sign_count' => $updated->counter]);
    }

    public function hasCredentials(Staff $staff): bool
    {
        return $staff->webauthnCredentials()->exists();
    }

    protected function deserializeCredential(array $credential): PublicKeyCredential
    {
        $manager = AttestationStatementSupportManager::create();
        $serializer = (new WebauthnSerializerFactory($manager))->create();

        try {
            /** @var PublicKeyCredential $pkc */
            $pkc = $serializer->deserialize(json_encode($credential, JSON_THROW_ON_ERROR), PublicKeyCredential::class, 'json');
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'webauthn' => 'Could not read biometric response.',
            ]);
        }

        return $pkc;
    }

    protected function rpId(): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : 'localhost';
    }

    protected function origin(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    protected function userHandle(Staff $staff): string
    {
        return 'staff:'.$staff->id;
    }

    protected function challengeKey(string $type, int $staffId): string
    {
        return "staff_webauthn:{$type}:{$staffId}";
    }

    protected function b64url(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    protected function b64urlDecode(string $value): string
    {
        $padded = strtr($value, '-_', '+/');
        $pad = strlen($padded) % 4;
        if ($pad > 0) {
            $padded .= str_repeat('=', 4 - $pad);
        }
        $decoded = base64_decode($padded, true);

        return $decoded === false ? '' : $decoded;
    }
}
