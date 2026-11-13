# cPanel cron for PlantGrid

Laravel’s scheduler must run every minute so Live Pulse, SLA checks, and metric collection stay active.

## Cron entry

```bash
* * * * * cd ~/plantgrid_app && php artisan schedule:run >> /dev/null 2>&1
```

In cPanel → **Cron Jobs**, add that command with timing **Once Per Minute**.

## What the scheduler runs

- `plantgrid:collect-metrics` — every 5 minutes  
- `plantgrid:check-sla` — every 5 minutes (marks breached cases)  
- `plantgrid:live-pulse` — every 5 minutes (portfolio demo activity through 31 Dec 2026)  
- `plantgrid:prune-metrics` — daily  

## Live Pulse

1. Set `PLANTGRID_LIVE_PULSE=true` in `~/plantgrid_app/.env`, **or** enable it under **Administration → Live Pulse**.
2. Optional: `PLANTGRID_LIVE_PULSE_OPENAI=true` paraphrases note/message text when `OPENAI_API_KEY` is set (falls back to templates).
3. Manual tick: `php artisan plantgrid:live-pulse --once`
4. Status: `php artisan plantgrid:live-pulse --status`
5. Purge synthetic rows: `php artisan plantgrid:live-pulse-reset --force`

After `git pull`, still sync the public docroot when CSS/JS change (`bash deploy/sync-public.sh`).
