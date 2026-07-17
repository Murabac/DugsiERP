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

    public function badgeClass(): string
    {
        return match ($this) {
            self::Stubbed => 'bg-slate-100 text-slate-700',
            self::Queued => 'bg-amber-100 text-amber-800',
            self::Sent => 'bg-green-100 text-green-800',
            self::Failed => 'bg-red-100 text-red-800',
        };
    }
}
