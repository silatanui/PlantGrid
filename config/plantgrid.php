<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public web mirror
    |--------------------------------------------------------------------------
    |
    | Apache serves this directory. The Laravel source stays outside htdocs.
    |
    */

    'public_path' => env('PLANTGRID_PUBLIC_PATH'),

    'display_timezone' => env('PLANTGRID_DISPLAY_TZ', 'Africa/Nairobi'),

    'outage_hostname' => 'PLT-SW-PACK',

    'degraded_hostname' => 'PLT-FS01',

    /*
    |--------------------------------------------------------------------------
    | Live Pulse (portfolio demo activity)
    |--------------------------------------------------------------------------
    |
    | When enabled (env or admin toggle), plantgrid:live-pulse inserts curated
    | demo cases, notes, status work, and messages on a schedule through ends_at.
    | Tuned for busy, autonomous plant-ICT activity through the end of the year.
    |
    */

    'live_pulse' => [
        'enabled' => (bool) env('PLANTGRID_LIVE_PULSE', false),
        // Templates are rich enough for volume; OpenAI paraphrase is optional.
        'use_openai' => (bool) env('PLANTGRID_LIVE_PULSE_OPENAI', false),
        'ends_at' => env('PLANTGRID_LIVE_PULSE_ENDS_AT', '2026-12-31 23:59:59'),
        'timezone' => env('PLANTGRID_LIVE_PULSE_TZ', 'Africa/Nairobi'),
        // Busy plant desk without unbounded growth.
        'open_case_ceiling' => (int) env('PLANTGRID_LIVE_PULSE_OPEN_CEILING', 85),
        'marker' => '[Live pulse]',
        'catch_up_after_minutes' => (int) env('PLANTGRID_LIVE_PULSE_CATCHUP_MINUTES', 8),
        'daily_caps' => [
            'new_case' => (int) env('PLANTGRID_LIVE_PULSE_CAP_NEW', 48),
            'note' => (int) env('PLANTGRID_LIVE_PULSE_CAP_NOTE', 160),
            'message' => (int) env('PLANTGRID_LIVE_PULSE_CAP_MESSAGE', 72),
            'monitoring' => (int) env('PLANTGRID_LIVE_PULSE_CAP_MONITORING', 24),
            'resolve' => (int) env('PLANTGRID_LIVE_PULSE_CAP_RESOLVE', 42),
            'close' => (int) env('PLANTGRID_LIVE_PULSE_CAP_CLOSE', 36),
            'assign' => (int) env('PLANTGRID_LIVE_PULSE_CAP_ASSIGN', 48),
            'progress' => (int) env('PLANTGRID_LIVE_PULSE_CAP_PROGRESS', 72),
            'starve' => (int) env('PLANTGRID_LIVE_PULSE_CAP_STARVE', 16),
        ],
        'weights' => [
            'new_case' => 18,
            'starve' => 6,
            'internal_note' => 16,
            'visible_note' => 10,
            'assign' => 14,
            'progress' => 16,
            'resolve' => 12,
            'close' => 8,
            'message' => 10,
            'monitoring' => 4,
        ],
    ],

];
