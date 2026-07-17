<?php

namespace App\Support;

class SmsGateway
{
    public static function driver(): string
    {
        $configured = strtolower((string) config('services.sms.driver', 'auto'));

        return match ($configured) {
            'textbee' => TextBeeSmsClient::isConfigured() ? 'textbee' : 'none',
            'telesom' => TelesomSmsClient::isConfigured() ? 'telesom' : 'none',
            default => TextBeeSmsClient::isConfigured()
                ? 'textbee'
                : (TelesomSmsClient::isConfigured() ? 'telesom' : 'none'),
        };
    }

    public static function isConfigured(): bool
    {
        return self::driver() !== 'none';
    }

    public static function label(): string
    {
        return match (self::driver()) {
            'textbee' => 'TextBee',
            'telesom' => 'Telesom',
            default => 'Not configured',
        };
    }

    /**
     * @return array{ok: bool, response: ?string, error: ?string}
     */
    public static function send(string $to, string $message): array
    {
        return match (self::driver()) {
            'textbee' => TextBeeSmsClient::send($to, $message),
            'telesom' => TelesomSmsClient::send($to, $message),
            default => [
                'ok' => false,
                'response' => null,
                'error' => 'SMS credentials are not configured. Set TEXTBEE_* or TELESOM_SMS_* in .env.',
            ],
        };
    }
}
