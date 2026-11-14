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
        // Steady plant desk with natural quiet gaps — not a firehose.
        'open_case_ceiling' => (int) env('PLANTGRID_LIVE_PULSE_OPEN_CEILING', 85),
        'marker' => '[Live pulse]',
        'catch_up_after_minutes' => (int) env('PLANTGRID_LIVE_PULSE_CATCHUP_MINUTES', 8),
        // After activity, often pause 3–6 minutes before the next tick fires events.
        'quiet_minutes_min' => (int) env('PLANTGRID_LIVE_PULSE_QUIET_MIN', 3),
        'quiet_minutes_max' => (int) env('PLANTGRID_LIVE_PULSE_QUIET_MAX', 6),
        'quiet_chance_percent' => (int) env('PLANTGRID_LIVE_PULSE_QUIET_CHANCE', 40),
        'daily_caps' => [
            'new_case' => (int) env('PLANTGRID_LIVE_PULSE_CAP_NEW', 18),
            'note' => (int) env('PLANTGRID_LIVE_PULSE_CAP_NOTE', 96),
            'message' => (int) env('PLANTGRID_LIVE_PULSE_CAP_MESSAGE', 36),
            'monitoring' => (int) env('PLANTGRID_LIVE_PULSE_CAP_MONITORING', 12),
            'resolve' => (int) env('PLANTGRID_LIVE_PULSE_CAP_RESOLVE', 24),
            'close' => (int) env('PLANTGRID_LIVE_PULSE_CAP_CLOSE', 20),
            'assign' => (int) env('PLANTGRID_LIVE_PULSE_CAP_ASSIGN', 28),
            'progress' => (int) env('PLANTGRID_LIVE_PULSE_CAP_PROGRESS', 40),
            'starve' => (int) env('PLANTGRID_LIVE_PULSE_CAP_STARVE', 8),
        ],
        'weights' => [
            'new_case' => 8,
            'starve' => 3,
            'internal_note' => 22,
            'visible_note' => 8,
            'assign' => 12,
            'progress' => 18,
            'resolve' => 10,
            'close' => 14,
            'message' => 6,
            'monitoring' => 3,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring Pulse (rare simulated instability)
    |--------------------------------------------------------------------------
    |
    | Flips monitoring into a short outage about once a week and notifies ICT
    | staff who can view monitoring. Changing `trigger` after a deploy starts
    | a brief demo outage so you can see the red alert immediately.
    |
    */

    'monitoring_pulse' => [
        'enabled' => (bool) env('PLANTGRID_MONITORING_PULSE', true),
        'trigger' => env('PLANTGRID_MONITORING_PULSE_TRIGGER', '2026-11-14-outage-demo'),
        'demo_minutes' => (int) env('PLANTGRID_MONITORING_PULSE_DEMO_MINUTES', 8),
        'incident_minutes_min' => (int) env('PLANTGRID_MONITORING_PULSE_INCIDENT_MIN', 45),
        'incident_minutes_max' => (int) env('PLANTGRID_MONITORING_PULSE_INCIDENT_MAX', 120),
        'interval_days_min' => (int) env('PLANTGRID_MONITORING_PULSE_INTERVAL_MIN', 6),
        'interval_days_max' => (int) env('PLANTGRID_MONITORING_PULSE_INTERVAL_MAX', 9),
    ],

];
