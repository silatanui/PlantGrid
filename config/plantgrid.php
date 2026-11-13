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

    'display_timezone' => 'Africa/Nairobi',

    'outage_hostname' => 'PLT-SW-PACK',

    'degraded_hostname' => 'PLT-FS01',

    /*
    |--------------------------------------------------------------------------
    | Live Pulse (portfolio demo activity)
    |--------------------------------------------------------------------------
    |
    | When enabled (env or admin toggle), plantgrid:live-pulse inserts curated
    | demo cases, notes, and messages on a schedule through ends_at.
    |
    */

    'live_pulse' => [
        'enabled' => (bool) env('PLANTGRID_LIVE_PULSE', false),
        'use_openai' => (bool) env('PLANTGRID_LIVE_PULSE_OPENAI', true),
        'ends_at' => env('PLANTGRID_LIVE_PULSE_ENDS_AT', '2026-12-31 23:59:59'),
        'timezone' => env('PLANTGRID_LIVE_PULSE_TZ', 'Africa/Nairobi'),
        'open_case_ceiling' => (int) env('PLANTGRID_LIVE_PULSE_OPEN_CEILING', 25),
        'marker' => '[Live pulse]',
        'daily_caps' => [
            'new_case' => 8,
            'note' => 20,
            'message' => 15,
            'monitoring' => 3,
            'resolve' => 6,
            'assign' => 8,
            'starve' => 4,
        ],
        'weights' => [
            'new_case' => 22,
            'starve' => 12,
            'internal_note' => 18,
            'visible_note' => 12,
            'assign' => 12,
            'resolve' => 10,
            'message' => 12,
            'monitoring' => 2,
        ],
    ],

];
