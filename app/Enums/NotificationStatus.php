<?php

namespace App\Enums;

enum NotificationStatus: string
{
    case Stubbed = 'stubbed';
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Stubbed => 'Stubbed',
            self::Queued => 'Queued',
            self::Sent => 'Sent',
            self::Failed => 'Failed',
        };
    }
}
