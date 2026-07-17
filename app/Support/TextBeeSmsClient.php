<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

class TextBeeSmsClient
{
    public static function isConfigured(): bool
    {
        $cfg = config('services.textbee');

        return filled($cfg['api_key'] ?? null)
            && filled($cfg['device_id'] ?? null);
    }

    /**
     * @return array{ok: bool, response: ?string, error: ?string}
     */
    public static function send(string $to, string $message): array
    {
        if (! self::isConfigured()) {
            return [
                'ok' => false,
                'response' => null,
                'error' => 'TextBee SMS credentials are not configured.',
            ];
        }

        $to = self::normalizeE164($to);
        if ($to === null) {
            return [
                'ok' => false,
                'response' => null,
                'error' => 'Invalid recipient phone number.',
            ];
        }

        $cfg = config('services.textbee');
        $url = rtrim((string) $cfg['api_base'], '/').'/gateway/devices/'.$cfg['device_id'].'/send-sms';

        try {
            $response = Http::withHeaders([
                'x-api-key' => $cfg['api_key'],
                'Accept' => 'application/json',
            ])
                ->withOptions(['verify' => self::shouldVerifySsl()])
                ->timeout(20)
                ->asJson()
                ->post($url, [
                    'recipients' => [$to],
                    'message' => $message,
                ]);
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'response' => null,
                'error' => 'TextBee request failed: '.$e->getMessage(),
            ];
        }

        $body = $response->body();
        $json = $response->json();
        $ok = $response->successful();

        if ($ok && is_array($json)) {
            $status = strtolower((string) ($json['status'] ?? $json['success'] ?? ''));
            if (in_array($status, ['error', 'failed', 'false', '0'], true) || ($json['success'] ?? null) === false) {
                $ok = false;
            }
        }

        $error = null;
        if (! $ok) {
            $error = is_array($json)
                ? (string) ($json['message'] ?? $json['error'] ?? $json['msg'] ?? $body)
                : ($body !== '' ? $body : 'TextBee rejected the message.');
        }

        return [
            'ok' => $ok,
            'response' => mb_substr($body, 0, 2000),
            'error' => $error,
        ];
    }

    /**
     * Always verify TLS in production; allow local override via config.
     */
    public static function shouldVerifySsl(): bool
    {
        if (app()->environment('production')) {
            return true;
        }

        return (bool) (config('services.textbee.verify_ssl') ?? true);
    }

    /**
     * TextBee expects E.164 with a leading +.
     */
    public static function normalizeE164(string $phone): ?string
    {
        $digits = TelesomSmsClient::normalizeMsisdn($phone);
        if ($digits === null) {
            return null;
        }

        return '+'.$digits;
    }
}
