<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class TelesomSmsClient
{
    public static function isConfigured(): bool
    {
        $cfg = config('services.telesom');

        return filled($cfg['api_url'] ?? null)
            && filled($cfg['username'] ?? null)
            && filled($cfg['password'] ?? null)
            && filled($cfg['sender'] ?? null)
            && filled($cfg['secret'] ?? null);
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
                'error' => 'Telesom SMS credentials are not configured.',
            ];
        }

        $cfg = config('services.telesom');
        $to = self::normalizeMsisdn($to);
        if ($to === null) {
            return [
                'ok' => false,
                'response' => null,
                'error' => 'Invalid recipient phone number.',
            ];
        }

        $date = now()->format('d/m/Y');
        $encodedMsg = str_ireplace(' ', '%20', $message);
        $key = strtoupper(md5(implode('|', [
            $cfg['username'],
            $cfg['password'],
            $to,
            $encodedMsg,
            $cfg['sender'],
            $date,
            $cfg['secret'],
        ])));

        try {
            $response = Http::asForm()
                ->timeout(20)
                ->post($cfg['api_url'], [
                    'username' => $cfg['username'],
                    'password' => $cfg['password'],
                    'to' => $to,
                    'msg' => $encodedMsg,
                    'from' => $cfg['sender'],
                    'date' => $date,
                    'key' => $key,
                ]);
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'response' => null,
                'error' => 'SMS gateway request failed: '.$e->getMessage(),
            ];
        }

        $body = $response->body();
        $json = $response->json();

        $status = is_array($json) ? strtolower((string) ($json['status'] ?? '')) : '';
        $ok = $response->successful() && ($status === 'success' || $status === '' && str_contains(strtolower($body), 'success'));

        if (! $ok && $status === '' && $response->successful() && ! str_contains(strtolower($body), 'error')) {
            // Some gateways return plain "OK" / delivery text without JSON.
            $ok = $response->status() === 200 && strlen(trim($body)) > 0;
        }

        return [
            'ok' => $ok,
            'response' => mb_substr($body, 0, 2000),
            'error' => $ok
                ? null
                : (is_array($json) ? (string) ($json['msg'] ?? $body) : ($body !== '' ? $body : 'SMS gateway rejected the message.')),
        ];
    }

    public static function normalizeMsisdn(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return null;
        }

        // Local Somaliland mobiles often stored as 063… or +25263…
        if (str_starts_with($digits, '0') && strlen($digits) >= 9) {
            $digits = '252'.substr($digits, 1);
        }

        if (strlen($digits) < 9 || strlen($digits) > 15) {
            return null;
        }

        return $digits;
    }

    /**
     * @throws RuntimeException
     */
    public static function assertConfigured(): void
    {
        if (! self::isConfigured()) {
            throw new RuntimeException('Telesom SMS credentials are not configured.');
        }
    }
}
