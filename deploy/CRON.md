# cPanel cron for PlantGrid

Laravel’s scheduler must run every minute so Live Pulse, SLA checks, and metric collection stay active.

## Cron entry

```bash
* * * * * cd ~/plantgrid_app && php artisan schedule:run >> /dev/null 2>&1
```

In cPanel → **Cron Jobs**, add that command with timing **Once Per Minute**.

Paste only the command body into the cPanel “Command” field — do **not** paste the five asterisks into SSH Terminal.

## What the scheduler runs

- `plantgrid:collect-metrics` — every 5 minutes  
- `plantgrid:check-sla` — every 5 minutes (marks breached cases)  
- `plantgrid:live-pulse` — every minute (autonomous demo desk through 31 Dec 2026; often idle 3–6 minutes between bursts)  
- `plantgrid:monitoring-pulse` — every minute (rare simulated outages ~weekly; short red-alert demo after each deploy trigger)  
- `plantgrid:refresh-demo-timeline` — daily (keeps overdue gaps frozen)  
- `plantgrid:prune-metrics` — daily  

## Live Pulse

1. Set `PLANTGRID_LIVE_PULSE=true` in `~/plantgrid_app/.env`, **or** enable it under **Administration → Live Pulse**.
2. Optional OpenAI paraphrase: `PLANTGRID_LIVE_PULSE_OPENAI=true` plus `OPENAI_API_KEY` (defaults off for volume).
3. Manual burst: `php artisan plantgrid:live-pulse --once`
4. Status: `php artisan plantgrid:live-pulse --status`
5. Purge synthetic rows: `php artisan plantgrid:live-pulse-reset --force`

Signed-in page loads also catch up Live Pulse if the last run is older than ~8 minutes (helps when cron is delayed).

## Monitoring Pulse

Simulated monitoring stays mostly quiet, then about once a week flips to an **outage** on `PLT-SW-PACK`, raises a critical alert, and notifies ICT users who can view Monitoring. After a few minutes/hours it returns to normal on its own.

After you `git pull` a release that bumps the monitoring pulse trigger, the next signed-in page load or cron tick starts an ~8 minute demo outage so you can see the red alert on `/monitoring`.

```bash
php artisan plantgrid:monitoring-pulse --demo
php artisan plantgrid:monitoring-pulse --status
```

After `git pull`, sync the public docroot when CSS/JS change (`bash deploy/sync-public.sh`).
