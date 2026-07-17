<?php

namespace App\Models;

use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    protected $fillable = [
        'type',
        'name',
        'channel',
        'body',
        'variables',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => NotificationType::class,
            'variables' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Replace {placeholders} in the template body.
     *
     * @param  array<string, string|int|float|null>  $vars
     */
    public function render(array $vars): string
    {
        $body = $this->body;

        foreach ($vars as $key => $value) {
            $body = str_replace('{'.$key.'}', (string) ($value ?? ''), $body);
        }

        return $body;
    }
}
