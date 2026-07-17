<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Check in — {{ $schoolName }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gradient-to-b from-slate-100 to-slate-200 text-slate-900 antialiased">
    <div class="mx-auto flex min-h-screen max-w-md flex-col px-4 py-8">
        <div class="mb-8 text-center">
            <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $schoolName }}</div>
            <h1 class="mt-2 text-2xl font-bold text-dugsi-primary">Staff check-in</h1>
            <p class="mt-1 text-sm text-slate-600">{{ $staff->full_name }}</p>
            <p class="font-mono text-[11px] text-slate-400">{{ $staff->employee_code }}</p>
        </div>

        <div class="flex-1 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            @if ($nextAction === 'done')
                <div class="py-8 text-center">
                    <p class="text-lg font-semibold text-green-700">Done for today</p>
                    <p class="mt-2 text-sm text-slate-500">You already checked in and out.</p>
                </div>
            @else
                <p class="mb-4 text-center text-sm text-slate-600" id="status-text">
                    @if ($allowLocalPunch ?? false)
                        On this local Wi‑Fi test, tap below to check in (biometrics need HTTPS in production).
                    @elseif (! $enrolled)
                        First time: enroll this phone’s fingerprint / Face ID, then check in.
                    @elseif ($nextAction === 'check_in')
                        Tap below and confirm with your biometric.
                    @else
                        Ready to check out — confirm with your biometric.
                    @endif
                </p>

                <button type="button" id="action-btn"
                    class="w-full rounded-xl bg-dugsi-primary px-4 py-4 text-base font-semibold text-white hover:bg-[#162d56] disabled:opacity-50">
                    @if (($allowLocalPunch ?? false) || $enrolled)
                        {{ $nextAction === 'check_out' ? 'Check out' : 'Check in' }}
                    @else
                        Enroll biometric
                    @endif
                </button>

                <p class="mt-4 text-center text-[11px] text-slate-400">
                    Must be on school Wi‑Fi.
                    @if ($allowLocalPunch ?? false)
                        Local test mode (no biometric).
                    @else
                        HTTPS required for biometrics.
                    @endif
                </p>
            @endif

            <p id="error-text" class="mt-4 hidden text-center text-sm text-red-600"></p>
        </div>
    </div>

    <script>
    (() => {
        const enrolled = @json($enrolled);
        const allowLocalPunch = @json($allowLocalPunch ?? false);
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        const btn = document.getElementById('action-btn');
        const statusText = document.getElementById('status-text');
        const errorText = document.getElementById('error-text');

        const urls = {
            regOptions: @json(route('staff-checkin.register.options', $token)),
            regVerify: @json(route('staff-checkin.register.verify', $token)),
            loginOptions: @json(route('staff-checkin.login.options', $token)),
            loginVerify: @json(route('staff-checkin.login.verify', $token)),
            localPunch: @json(route('staff-checkin.local-punch', $token)),
        };

        function b64urlToBuffer(value) {
            const pad = '='.repeat((4 - (value.length % 4)) % 4);
            const b64 = (value + pad).replace(/-/g, '+').replace(/_/g, '/');
            const str = atob(b64);
            const bytes = new Uint8Array(str.length);
            for (let i = 0; i < str.length; i++) bytes[i] = str.charCodeAt(i);
            return bytes.buffer;
        }

        function bufferToB64url(buffer) {
            const bytes = new Uint8Array(buffer);
            let str = '';
            bytes.forEach(b => { str += String.fromCharCode(b); });
            return btoa(str).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
        }

        function showError(msg) {
            if (!errorText) return;
            errorText.textContent = msg;
            errorText.classList.remove('hidden');
            window.DugsiUI?.error(msg);
        }

        async function postJson(url, body) {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(body ?? {}),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                const msg = data.message || data.errors?.webauthn?.[0] || data.errors?.punch?.[0] || 'Request failed';
                throw new Error(msg);
            }
            return data;
        }

        function publicKeyFromCreateOptions(options) {
            return {
                ...options,
                challenge: b64urlToBuffer(options.challenge),
                user: {
                    ...options.user,
                    id: b64urlToBuffer(options.user.id),
                },
                excludeCredentials: (options.excludeCredentials || []).map(c => ({
                    ...c,
                    id: b64urlToBuffer(c.id),
                })),
            };
        }

        function publicKeyFromRequestOptions(options) {
            return {
                ...options,
                challenge: b64urlToBuffer(options.challenge),
                allowCredentials: (options.allowCredentials || []).map(c => ({
                    ...c,
                    id: b64urlToBuffer(c.id),
                })),
            };
        }

        function credentialToJson(cred) {
            const response = cred.response;
            const out = {
                id: cred.id,
                rawId: bufferToB64url(cred.rawId),
                type: cred.type,
                response: {},
            };
            if (response.attestationObject) {
                out.response = {
                    clientDataJSON: bufferToB64url(response.clientDataJSON),
                    attestationObject: bufferToB64url(response.attestationObject),
                    transports: response.getTransports?.() || [],
                };
            } else {
                out.response = {
                    clientDataJSON: bufferToB64url(response.clientDataJSON),
                    authenticatorData: bufferToB64url(response.authenticatorData),
                    signature: bufferToB64url(response.signature),
                    userHandle: response.userHandle ? bufferToB64url(response.userHandle) : null,
                };
            }
            return out;
        }

        async function enroll() {
            const options = await postJson(urls.regOptions, {});
            const cred = await navigator.credentials.create({ publicKey: publicKeyFromCreateOptions(options) });
            if (!cred) throw new Error('Enrollment cancelled');
            await postJson(urls.regVerify, { credential: credentialToJson(cred) });
            window.DugsiUI?.success('Biometric enrolled');
            location.reload();
        }

        async function punchWebauthn() {
            const options = await postJson(urls.loginOptions, {});
            const cred = await navigator.credentials.get({ publicKey: publicKeyFromRequestOptions(options) });
            if (!cred) throw new Error('Cancelled');
            return postJson(urls.loginVerify, { credential: credentialToJson(cred) });
        }

        async function punchLocal() {
            return postJson(urls.localPunch, {});
        }

        btn?.addEventListener('click', async () => {
            errorText?.classList.add('hidden');
            btn.disabled = true;
            try {
                let result;
                if (allowLocalPunch && !window.isSecureContext) {
                    result = await punchLocal();
                } else if (!enrolled) {
                    if (!window.PublicKeyCredential) {
                        throw new Error('This browser does not support biometrics / WebAuthn.');
                    }
                    await enroll();
                    return;
                } else {
                    if (!window.PublicKeyCredential) {
                        throw new Error('This browser does not support biometrics / WebAuthn.');
                    }
                    result = await punchWebauthn();
                }

                window.DugsiUI?.success(result.message || 'Done');
                if (statusText) statusText.textContent = result.message;
                if (result.next_action === 'done') {
                    location.reload();
                } else if (btn && result.next_action === 'check_out') {
                    btn.textContent = 'Check out';
                }
            } catch (e) {
                showError(e.message || String(e));
            } finally {
                btn.disabled = false;
            }
        });
    })();
    </script>
</body>
</html>
