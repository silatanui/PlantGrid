<?php

namespace App\Support;

class Labels
{
    public static function status(string $value): string
    {
        return ucwords(str_replace('_', ' ', $value));
    }

    public static function tone(string $value): string
    {
        return match ($value) {
            'active', 'available', 'running', 'in_place', 'good', 'pass', 'passed' => 'info',
            'completed', 'resolved', 'closed', 'successful' => 'muted',
            'in_progress', 'assigned', 'scheduled', 'pending', 'pending_vendor', 'open', 'warning', 'degraded', 'fair', 'acknowledged', 'new' => 'warning',
            'critical', 'failed', 'outage', 'unavailable', 'lost', 'damaged', 'high' => 'critical',
            'retired', 'cancelled', 'spare', 'inactive', 'low' => 'muted',
            default => 'info',
        };
    }

    public static function badge(string $value): string
    {
        $tone = self::tone($value);
        if (! in_array($tone, ['success', 'warning', 'critical', 'info', 'muted'], true)) {
            $tone = 'info';
        }

        return '<span class="pg-badge pg-badge-'.$tone.'">'.e(self::status($value)).'</span>';
    }
}
