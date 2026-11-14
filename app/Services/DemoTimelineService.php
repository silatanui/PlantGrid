<?php

namespace App\Services;

use App\Models\SystemSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DemoTimelineService
{
    public const ANCHOR_KEY = 'demo_timeline_anchor';

    /**
     * Shift demo case timestamps forward when the calendar day advances,
     * so relative ages (including intentional overdue gaps) stay frozen.
     *
     * @return array{shifted: bool, days: int, anchor: string}
     */
    public function refreshIfNeeded(bool $force = false): array
    {
        $today = now()->startOfDay();
        $todayKey = $today->toDateString();
        $anchorRaw = SystemSetting::getValue(self::ANCHOR_KEY);
        $anchor = $anchorRaw ? Carbon::parse($anchorRaw)->startOfDay() : null;

        if ($anchor === null) {
            SystemSetting::putValue(
                self::ANCHOR_KEY,
                $todayKey,
                null,
                'Last calendar day when demo case timelines were aligned to today'
            );

            return ['shifted' => false, 'days' => 0, 'anchor' => $todayKey];
        }

        $days = (int) $anchor->diffInDays($today, false);
        if ($days <= 0 && ! $force) {
            return ['shifted' => false, 'days' => 0, 'anchor' => $anchor->toDateString()];
        }

        if ($days <= 0 && $force) {
            $days = 1;
        }

        $this->shiftByDays($days);

        SystemSetting::putValue(
            self::ANCHOR_KEY,
            $todayKey,
            null,
            'Last calendar day when demo case timelines were aligned to today'
        );

        return ['shifted' => true, 'days' => $days, 'anchor' => $todayKey];
    }

    /**
     * Once-per-day guard for web requests (cPanel hosts without cron).
     */
    public function refreshOncePerDay(): void
    {
        $todayKey = now()->toDateString();
        $cacheKey = 'plantgrid.demo_timeline.'.$todayKey;

        if (Cache::get($cacheKey) === 'done') {
            $anchor = SystemSetting::getValue(self::ANCHOR_KEY);
            if ($anchor === $todayKey) {
                return;
            }
        }

        Cache::lock('plantgrid.demo_timeline.lock', 30)->block(5, function () use ($cacheKey, $todayKey): void {
            if (Cache::get($cacheKey) === 'done' && SystemSetting::getValue(self::ANCHOR_KEY) === $todayKey) {
                return;
            }

            $this->refreshIfNeeded();
            Cache::put($cacheKey, 'done', now()->endOfDay());
        });
    }

    public function shiftByDays(int $days): void
    {
        if ($days === 0) {
            return;
        }

        $ticketColumns = [
            'reported_at',
            'due_at',
            'sla_due_at',
            'sla_breached_at',
            'sla_at_risk_notified_at',
            'resolved_at',
            'closed_at',
            'created_at',
            'updated_at',
        ];

        DB::transaction(function () use ($days, $ticketColumns): void {
            $this->bumpTable('support_tickets', $ticketColumns, $days);
            $this->bumpTable('ticket_comments', ['created_at', 'updated_at'], $days);
            $this->bumpTable('ticket_status_histories', ['created_at'], $days);
            $this->bumpTable('ticket_attachments', ['created_at', 'updated_at'], $days);
        });
    }

    /**
     * @param  list<string>  $columns
     */
    private function bumpTable(string $table, array $columns, int $days): void
    {
        $driver = DB::connection()->getDriverName();
        $assignments = [];

        foreach ($columns as $column) {
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                $assignments[] = sprintf(
                    '`%s` = CASE WHEN `%s` IS NULL THEN NULL ELSE DATE_ADD(`%s`, INTERVAL %d DAY) END',
                    $column,
                    $column,
                    $column,
                    $days
                );
                continue;
            }

            // SQLite (tests) and other drivers
            $assignments[] = sprintf(
                '"%s" = CASE WHEN "%s" IS NULL THEN NULL ELSE datetime("%s", "+%d days") END',
                $column,
                $column,
                $column,
                $days
            );
        }

        if ($assignments === []) {
            return;
        }

        DB::statement('UPDATE '.$table.' SET '.implode(', ', $assignments));
    }
}
