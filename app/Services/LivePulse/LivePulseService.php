<?php

namespace App\Services\LivePulse;

use App\Models\Asset;
use App\Models\DirectMessage;
use App\Models\MonitoringAlert;
use App\Models\Server;
use App\Models\SupportTicket;
use App\Models\SystemSetting;
use App\Models\TicketCategory;
use App\Models\TicketComment;
use App\Models\User;
use App\Services\OpenAiService;
use App\Services\TicketService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class LivePulseService
{
    public const SETTING_ENABLED = 'live_pulse_enabled';

    public const SETTING_LAST_RUN = 'live_pulse_last_run';

    public const SETTING_LAST_EVENT = 'live_pulse_last_event';

    public const SETTING_CAPS = 'live_pulse_caps';

    public const SETTING_QUIET_UNTIL = 'live_pulse_quiet_until';

    public function __construct(
        private readonly TicketService $tickets,
        private readonly OpenAiService $ai,
    ) {}

    public function marker(): string
    {
        return (string) config('plantgrid.live_pulse.marker', '[Live pulse]');
    }

    public function isEnabled(): bool
    {
        $override = SystemSetting::getValue(self::SETTING_ENABLED);
        if ($override === '1') {
            return true;
        }
        if ($override === '0') {
            return false;
        }

        return (bool) config('plantgrid.live_pulse.enabled', false);
    }

    public function setEnabled(bool $enabled, ?User $actor = null): void
    {
        SystemSetting::putValue(
            self::SETTING_ENABLED,
            $enabled ? '1' : '0',
            $actor?->id,
            'Portfolio Live Pulse simulator'
        );
    }

    public function endsAt(): Carbon
    {
        $tz = (string) config('plantgrid.live_pulse.timezone', config('plantgrid.display_timezone', 'Africa/Nairobi'));

        return Carbon::parse((string) config('plantgrid.live_pulse.ends_at'), $tz);
    }

    public function isActiveWindow(?Carbon $now = null): bool
    {
        $now = ($now ?? now())->copy()->timezone($this->endsAt()->timezone);

        return $this->isEnabled() && $now->lte($this->endsAt());
    }

    /**
     * @return array{ran: bool, reason?: string, events: list<string>}
     */
    public function run(bool $forceOne = false, ?Carbon $now = null): array
    {
        $now = $now ?? now();

        if (! $this->isEnabled()) {
            return ['ran' => false, 'reason' => 'disabled', 'events' => []];
        }

        if ($now->copy()->timezone($this->endsAt()->timezone)->gt($this->endsAt())) {
            return ['ran' => false, 'reason' => 'ended', 'events' => []];
        }

        $this->refreshCapsIfNeeded($now);

        if (! $forceOne && $this->isQuiet($now)) {
            SystemSetting::putValue(self::SETTING_LAST_RUN, $now->toIso8601String(), null, 'Live Pulse last schedule run');

            return ['ran' => true, 'reason' => 'quiet', 'events' => []];
        }

        $count = $forceOne ? 1 : $this->eventsThisTick($now);
        $events = [];

        for ($i = 0; $i < $count; $i++) {
            $type = $this->pickEventType();
            if ($type === null) {
                break;
            }

            try {
                $summary = $this->dispatch($type);
                if ($summary !== null) {
                    $events[] = $summary;
                    $this->bumpCap($type);
                }
            } catch (Throwable $exception) {
                Log::warning('live_pulse.event_failed', [
                    'type' => $type,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        SystemSetting::putValue(self::SETTING_LAST_RUN, $now->toIso8601String(), null, 'Live Pulse last schedule run');
        if ($events !== []) {
            SystemSetting::putValue(self::SETTING_LAST_EVENT, $now->toIso8601String().' · '.$events[array_key_last($events)], null, 'Live Pulse last event');
            if (! $forceOne) {
                $this->maybeScheduleQuiet($now);
            }
        }

        return ['ran' => true, 'events' => $events];
    }

    /**
     * @return array{tickets: int, comments: int, messages: int, alerts: int}
     */
    public function reset(): array
    {
        $marker = $this->marker();

        return DB::transaction(function () use ($marker) {
            $comments = TicketComment::query()->where('comment', 'like', '%'.$marker.'%')->delete();
            $messages = DirectMessage::query()
                ->where(function ($query) use ($marker) {
                    $query->where('subject', 'like', '%'.$marker.'%')
                        ->orWhere('body', 'like', '%'.$marker.'%');
                })
                ->delete();
            $alerts = MonitoringAlert::query()
                ->where(function ($query) use ($marker) {
                    $query->where('source', 'live_pulse')
                        ->orWhere('message', 'like', '%'.$marker.'%');
                })
                ->delete();

            $ticketIds = SupportTicket::query()
                ->where(function ($query) use ($marker) {
                    $query->where('description', 'like', '%'.$marker.'%')
                        ->orWhere('subject', 'like', '%'.$marker.'%');
                })
                ->pluck('id');

            $tickets = 0;
            if ($ticketIds->isNotEmpty()) {
                TicketComment::query()->whereIn('support_ticket_id', $ticketIds)->delete();
                DB::table('ticket_status_histories')->whereIn('support_ticket_id', $ticketIds)->delete();
                if (DB::getSchemaBuilder()->hasTable('support_ticket_partners')) {
                    DB::table('support_ticket_partners')->whereIn('support_ticket_id', $ticketIds)->delete();
                } elseif (DB::getSchemaBuilder()->hasTable('ticket_partners')) {
                    DB::table('ticket_partners')->whereIn('support_ticket_id', $ticketIds)->delete();
                }
                DB::table('ticket_attachments')->whereIn('support_ticket_id', $ticketIds)->delete();
                $tickets = SupportTicket::query()->whereIn('id', $ticketIds)->delete();
            }

            SystemSetting::putValue(self::SETTING_CAPS, json_encode(['day' => null, 'counts' => []]), null, 'Live Pulse daily caps');

            return [
                'tickets' => (int) $tickets,
                'comments' => (int) $comments,
                'messages' => (int) $messages,
                'alerts' => (int) $alerts,
            ];
        });
    }

    /**
     * @return array{enabled: bool, active: bool, ends_at: string, last_run: ?string, last_event: ?string, quiet_until: ?string, caps: array<string, int>, open_cases: int}
     */
    public function status(?Carbon $now = null): array
    {
        $now = $now ?? now();
        $caps = $this->capsState($now);

        return [
            'enabled' => $this->isEnabled(),
            'active' => $this->isActiveWindow($now),
            'ends_at' => $this->endsAt()->toDateTimeString(),
            'last_run' => SystemSetting::getValue(self::SETTING_LAST_RUN),
            'last_event' => SystemSetting::getValue(self::SETTING_LAST_EVENT),
            'quiet_until' => SystemSetting::getValue(self::SETTING_QUIET_UNTIL),
            'caps' => $caps['counts'],
            'open_cases' => SupportTicket::query()->whereNotIn('status', ['resolved', 'closed'])->count(),
        ];
    }

    /**
     * Catch-up tick for hosts where cron is missing or delayed.
     *
     * @return array{ran: bool, reason?: string, events: list<string>}|null
     */
    public function catchUpIfStale(?Carbon $now = null): ?array
    {
        $now = $now ?? now();
        if (! $this->isActiveWindow($now)) {
            return null;
        }

        if ($this->isQuiet($now)) {
            return null;
        }

        $minutes = max(3, (int) config('plantgrid.live_pulse.catch_up_after_minutes', 8));
        $last = SystemSetting::getValue(self::SETTING_LAST_RUN);
        if (is_string($last) && $last !== '') {
            try {
                if (Carbon::parse($last)->gt($now->copy()->subMinutes($minutes))) {
                    return null;
                }
            } catch (Throwable) {
                // Continue with catch-up when the stamp is unreadable.
            }
        }

        return $this->run(forceOne: false, now: $now);
    }

    private function eventsThisTick(Carbon $now): int
    {
        $local = $now->copy()->timezone($this->endsAt()->timezone);
        $hour = (int) $local->format('G');
        $weekday = $local->isWeekday();

        // Prefer quiet minutes and at most one or two events — not a burst every cron tick.
        if ($weekday && $hour >= 7 && $hour < 19) {
            $roll = mt_rand(1, 100);
            if ($roll <= 50) {
                return 0;
            }
            if ($roll <= 85) {
                return 1;
            }

            return 2;
        }

        if ($weekday && $hour >= 19 && $hour < 22) {
            $roll = mt_rand(1, 100);
            if ($roll <= 65) {
                return 0;
            }

            return 1;
        }

        if (! $weekday && $hour >= 9 && $hour < 16) {
            return mt_rand(1, 100) <= 40 ? 1 : 0;
        }

        return mt_rand(1, 100) <= 15 ? 1 : 0;
    }

    private function isQuiet(?Carbon $now = null): bool
    {
        $raw = SystemSetting::getValue(self::SETTING_QUIET_UNTIL);
        if (! is_string($raw) || $raw === '') {
            return false;
        }

        try {
            return Carbon::parse($raw)->gt($now ?? now());
        } catch (Throwable) {
            return false;
        }
    }

    private function maybeScheduleQuiet(Carbon $now): void
    {
        $chance = max(0, min(100, (int) config('plantgrid.live_pulse.quiet_chance_percent', 40)));
        if ($chance === 0 || mt_rand(1, 100) > $chance) {
            return;
        }

        $min = max(1, (int) config('plantgrid.live_pulse.quiet_minutes_min', 3));
        $max = max($min, (int) config('plantgrid.live_pulse.quiet_minutes_max', 6));
        $until = $now->copy()->addMinutes(mt_rand($min, $max));

        SystemSetting::putValue(
            self::SETTING_QUIET_UNTIL,
            $until->toIso8601String(),
            null,
            'Live Pulse quiet window'
        );
    }

    private function pickEventType(): ?string
    {
        $weights = config('plantgrid.live_pulse.weights', []);
        $open = SupportTicket::query()->whereNotIn('status', ['resolved', 'closed'])->count();
        $ceiling = (int) config('plantgrid.live_pulse.open_case_ceiling', 25);

        if ($open >= $ceiling) {
            unset($weights['new_case'], $weights['starve']);
            $weights['resolve'] = ($weights['resolve'] ?? 10) + 18;
            $weights['close'] = ($weights['close'] ?? 8) + 10;
            $weights['progress'] = ($weights['progress'] ?? 10) + 8;
            $weights['internal_note'] = ($weights['internal_note'] ?? 10) + 6;
        } elseif ($open >= (int) ($ceiling * 0.8)) {
            $weights['new_case'] = max(4, (int) (($weights['new_case'] ?? 10) * 0.5));
            $weights['resolve'] = ($weights['resolve'] ?? 10) + 8;
            $weights['progress'] = ($weights['progress'] ?? 10) + 6;
        }

        $filtered = [];
        foreach ($weights as $type => $weight) {
            if ($weight <= 0) {
                continue;
            }
            if (! $this->underCap($type)) {
                continue;
            }
            $filtered[$type] = (int) $weight;
        }

        if ($filtered === []) {
            return null;
        }

        $total = array_sum($filtered);
        $pick = mt_rand(1, max(1, $total));
        $running = 0;
        foreach ($filtered as $type => $weight) {
            $running += $weight;
            if ($pick <= $running) {
                return $type;
            }
        }

        return array_key_first($filtered);
    }

    private function dispatch(string $type): ?string
    {
        return match ($type) {
            'new_case' => $this->eventNewCase(),
            'starve' => $this->eventStarve(),
            'internal_note' => $this->eventNote('internal'),
            'visible_note' => $this->eventNote('requester_visible'),
            'assign' => $this->eventAssign(),
            'progress' => $this->eventProgress(),
            'resolve' => $this->eventResolve(),
            'close' => $this->eventClose(),
            'message' => $this->eventMessage(),
            'monitoring' => $this->eventMonitoring(),
            default => null,
        };
    }

    private function eventNewCase(): ?string
    {
        $requester = $this->requesters()->random();
        $template = EventCatalog::cases()[array_rand(EventCatalog::cases())];
        $category = TicketCategory::query()->where('name', $template['category'])->first()
            ?: TicketCategory::query()->orderBy('name')->first();
        if (! $category || ! $requester->department_id) {
            return null;
        }

        $asset = $this->pickAssetForCase($requester, $template);
        $description = $template['description'];
        if ($asset) {
            $deviceLine = trim($asset->asset_tag.' — '.$asset->name.($asset->model ? ' ('.$asset->model.')' : ''));
            $description .= "\n\nLinked device: ".$deviceLine
                .($asset->location?->name ? "\nLocation: ".$asset->location->name : '');
        }

        $description = $this->maybePolish($this->tag($description), 'case description');

        $ticket = $this->tickets->create($requester, [
            'department_id' => $requester->department_id,
            'ticket_category_id' => $category->id,
            'asset_id' => $asset?->id,
            'subject' => $template['subject'],
            'description' => $description,
            'priority' => $template['priority'],
        ]);

        return 'new_case '.$ticket->ticket_number.($asset ? ' @ '.$asset->asset_tag : '');
    }

    private function eventStarve(): ?string
    {
        $ticket = SupportTicket::query()
            ->whereNotIn('status', ['resolved', 'closed'])
            ->whereIn('priority', ['critical', 'high', 'medium'])
            ->whereNull('sla_breached_at')
            ->inRandomOrder()
            ->first();

        if (! $ticket) {
            $created = $this->eventNewCase();
            if ($created === null) {
                return null;
            }
            $ticket = SupportTicket::query()->latest('id')->first();
        }

        if (! $ticket) {
            return null;
        }

        $hours = match ($ticket->priority) {
            'critical' => 5,
            'high' => 10,
            default => 30,
        };

        $ticket->reported_at = now()->subHours($hours + 2);
        $ticket->sla_due_at = now()->subHours(1);
        $ticket->due_at = $ticket->sla_due_at;
        $ticket->save();

        return 'starve '.$ticket->ticket_number;
    }

    private function eventNote(string $visibility): ?string
    {
        $ticket = SupportTicket::query()
            ->with(['asset.location'])
            ->whereNotIn('status', ['resolved', 'closed'])
            ->inRandomOrder()
            ->first();
        if (! $ticket) {
            return null;
        }

        $techs = $this->technicians();
        $author = $visibility === 'requester_visible' && mt_rand(0, 1) === 1
            ? (User::query()->find($ticket->requester_id) ?: $techs->random())
            : $techs->random();

        $pool = $visibility === 'internal' ? EventCatalog::internalNotes() : EventCatalog::visibleNotes();
        $body = $this->contextualizeNote($pool[array_rand($pool)], $ticket);
        $body = $this->maybePolish($this->tag($body), 'note');

        $this->tickets->comment($ticket, $author, $body, $visibility);

        return $visibility.'_note '.$ticket->ticket_number;
    }

    private function eventAssign(): ?string
    {
        $ticket = SupportTicket::query()
            ->with(['asset'])
            ->whereNotIn('status', ['resolved', 'closed'])
            ->whereNull('assigned_to')
            ->inRandomOrder()
            ->first();
        if (! $ticket) {
            return null;
        }

        $tech = $this->technicians()->random();
        $actor = $this->assigners()->first() ?: $tech;
        $note = $this->tag($this->contextualizeNote(
            EventCatalog::assignNotes()[array_rand(EventCatalog::assignNotes())],
            $ticket,
            ['owner' => $tech->name, 'from' => 'new', 'to' => 'assigned']
        ));

        $this->tickets->assignTo($ticket, $tech, $actor, $note);

        return 'assign '.$ticket->ticket_number.' → '.$tech->name;
    }

    private function eventProgress(): ?string
    {
        $ticket = SupportTicket::query()
            ->with(['asset.location'])
            ->whereIn('status', ['new', 'assigned', 'in_progress', 'pending'])
            ->inRandomOrder()
            ->first();
        if (! $ticket) {
            return null;
        }

        $tech = $ticket->assigned_to
            ? (User::query()->find($ticket->assigned_to) ?: $this->technicians()->first())
            : $this->technicians()->random();

        if (! $tech) {
            return null;
        }

        if (! $ticket->assigned_to) {
            try {
                $actor = $this->assigners()->first() ?: $tech;
                $claim = $this->tag($this->contextualizeNote(
                    EventCatalog::assignNotes()[array_rand(EventCatalog::assignNotes())],
                    $ticket,
                    ['owner' => $tech->name, 'from' => 'new', 'to' => 'assigned']
                ));
                $this->tickets->assignTo($ticket, $tech, $actor, $claim);
                $ticket->refresh();
            } catch (Throwable) {
                // Fall through to transition attempt.
            }
        }

        $from = $ticket->status;
        $preferred = match ($from) {
            'new', 'assigned' => ['in_progress', 'pending'],
            'in_progress' => ['pending'],
            'pending' => ['in_progress'],
            default => ['in_progress'],
        };

        $allowed = $this->tickets->allowedTransitions($ticket->fresh(), $tech);
        $target = null;
        foreach ($preferred as $status) {
            if (in_array($status, $allowed, true) && $status !== $from) {
                $target = $status;
                break;
            }
        }

        if ($target === null) {
            return $this->eventNote('internal');
        }

        $stagePool = EventCatalog::stageAdvanceNotes($from, $target);
        $note = $this->tag($this->contextualizeNote(
            $stagePool[array_rand($stagePool)],
            $ticket,
            ['from' => $from, 'to' => $target, 'owner' => $tech->name]
        ));
        $note = $this->maybePolish($note, 'progress');

        $this->tickets->transition($ticket->fresh(), $tech, $target, [
            'note' => $note,
            'internal_note' => $note,
            'resolution_notes' => $note,
        ]);

        return 'progress '.$ticket->ticket_number.' → '.$target;
    }

    private function eventResolve(): ?string
    {
        // Only close out work that has already been owned and progressed — not brand-new queue items.
        $ticket = SupportTicket::query()
            ->with(['asset.location'])
            ->whereIn('status', ['in_progress', 'pending'])
            ->whereNotNull('assigned_to')
            ->whereHas('comments', fn ($query) => $query->where('visibility', 'internal'))
            ->inRandomOrder()
            ->first();

        if (! $ticket) {
            $ticket = SupportTicket::query()
                ->with(['asset.location'])
                ->whereIn('status', ['in_progress', 'pending'])
                ->whereNotNull('assigned_to')
                ->inRandomOrder()
                ->first();
        }

        if (! $ticket) {
            return null;
        }

        $tech = User::query()->find($ticket->assigned_to) ?: $this->technicians()->first();
        if (! $tech) {
            return null;
        }

        $from = $ticket->status;
        $resolution = $this->maybePolish(
            $this->tag($this->contextualizeNote(
                EventCatalog::resolveNotes()[array_rand(EventCatalog::resolveNotes())],
                $ticket,
                ['from' => $from, 'to' => 'resolved', 'owner' => $tech->name]
            )),
            'resolution'
        );
        $stagePool = EventCatalog::stageAdvanceNotes($from, 'resolved');
        $internal = $this->tag($this->contextualizeNote(
            $stagePool[array_rand($stagePool)],
            $ticket,
            ['from' => $from, 'to' => 'resolved', 'owner' => $tech->name]
        ));

        $target = in_array('resolved', $this->tickets->allowedTransitions($ticket->fresh(), $tech), true)
            ? 'resolved'
            : null;

        if ($target === null) {
            return null;
        }

        $this->tickets->transition($ticket->fresh(), $tech, $target, [
            'resolution_notes' => $resolution,
            'internal_note' => $internal,
            'note' => $internal,
        ]);

        return $target.' '.$ticket->ticket_number;
    }

    private function eventClose(): ?string
    {
        $ticket = SupportTicket::query()
            ->with(['asset.location'])
            ->where('status', 'resolved')
            ->inRandomOrder()
            ->first();
        if (! $ticket) {
            return null;
        }

        $tech = $ticket->assigned_to
            ? (User::query()->find($ticket->assigned_to) ?: $this->technicians()->first())
            : $this->technicians()->random();

        if (! $tech) {
            return null;
        }

        $allowed = $this->tickets->allowedTransitions($ticket, $tech);
        if (! in_array('closed', $allowed, true)) {
            return null;
        }

        $stagePool = EventCatalog::stageAdvanceNotes('resolved', 'closed');
        $closeNote = $this->tag($this->contextualizeNote(
            $stagePool[array_rand($stagePool)],
            $ticket,
            ['from' => 'resolved', 'to' => 'closed', 'owner' => $tech->name]
        ));
        $this->tickets->transition($ticket, $tech, 'closed', [
            'note' => $closeNote,
            'internal_note' => $closeNote,
            'resolution_notes' => $closeNote,
        ]);

        return 'closed '.$ticket->ticket_number;
    }

    /**
     * @param  array{subject?: string, category?: string, asset_hints?: list<string>}  $template
     */
    private function pickAssetForCase(User $requester, array $template): ?Asset
    {
        $hints = $template['asset_hints'] ?? EventCatalog::assetHintsFor($template['subject'] ?? '', $template['category'] ?? '');

        $base = Asset::query()->with(['location', 'category']);

        if ($hints !== []) {
            $matched = (clone $base)->where(function ($query) use ($hints) {
                foreach ($hints as $hint) {
                    $like = '%'.$hint.'%';
                    $query->orWhere('name', 'like', $like)
                        ->orWhere('asset_tag', 'like', $like)
                        ->orWhere('model', 'like', $like)
                        ->orWhere('manufacturer', 'like', $like);
                }
            })->inRandomOrder()->first();

            if ($matched) {
                return $matched;
            }
        }

        $departmentAsset = (clone $base)
            ->when($requester->department_id, fn ($query) => $query->where('department_id', $requester->department_id))
            ->inRandomOrder()
            ->first();

        if ($departmentAsset) {
            return $departmentAsset;
        }

        // Prefer linking a real plant device whenever the register has anything.
        return (clone $base)->inRandomOrder()->first();
    }

    /**
     * @param  array{from?: string, to?: string, owner?: string}  $context
     */
    private function contextualizeNote(string $template, SupportTicket $ticket, array $context = []): string
    {
        $ticket->loadMissing(['asset.location', 'assignee']);
        $device = $ticket->asset
            ? trim($ticket->asset->asset_tag.' ('.$ticket->asset->name.')')
            : 'the reported endpoint (no asset linked yet)';
        $location = $ticket->asset?->location?->name ?: 'the reported area';

        $replacements = [
            '{case}' => $ticket->ticket_number,
            '{subject}' => $ticket->subject,
            '{device}' => $device,
            '{location}' => $location,
            '{owner}' => $context['owner'] ?? ($ticket->assignee?->name ?? 'the on-duty technician'),
            '{from}' => str_replace('_', ' ', (string) ($context['from'] ?? $ticket->status)),
            '{to}' => str_replace('_', ' ', (string) ($context['to'] ?? '')),
            '{priority}' => $ticket->priority,
        ];

        return strtr($template, $replacements);
    }

    private function eventMessage(): ?string
    {
        $people = $this->requesters()->merge($this->technicians())->unique('id')->values();
        if ($people->count() < 2) {
            return null;
        }

        $sender = $people->random();
        $recipient = $people->where('id', '!=', $sender->id)->random();
        $template = EventCatalog::messages()[array_rand(EventCatalog::messages())];
        $body = $this->maybePolish($this->tag($template['body']), 'message');

        DirectMessage::query()->create([
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'subject' => $this->tag($template['subject']),
            'body' => $body,
        ]);

        return 'message '.$sender->name.' → '.$recipient->name;
    }

    private function eventMonitoring(): ?string
    {
        $server = Server::query()->inRandomOrder()->first();
        if (! $server) {
            return null;
        }

        $message = $this->tag(EventCatalog::monitoringMessages()[array_rand(EventCatalog::monitoringMessages())]);

        MonitoringAlert::query()->create([
            'target_type' => 'server',
            'target_id' => $server->id,
            'severity' => mt_rand(0, 1) ? 'warning' : 'critical',
            'message' => $message,
            'observed_value' => mt_rand(70, 98),
            'threshold_value' => 80,
            'status' => 'open',
            'source' => 'live_pulse',
            'triggered_at' => now(),
        ]);

        return 'monitoring_alert '.$server->hostname;
    }

    private function tag(string $text): string
    {
        $marker = $this->marker();
        if (str_contains($text, $marker)) {
            return $text;
        }

        return $marker.' '.$text;
    }

    private function maybePolish(string $text, string $kind): string
    {
        if (! config('plantgrid.live_pulse.use_openai', true)) {
            return $text;
        }

        if (! $this->ai->enabled()) {
            return $text;
        }

        try {
            return $this->ai->paraphraseNote($text);
        } catch (Throwable $exception) {
            Log::info('live_pulse.openai_fallback', [
                'kind' => $kind,
                'message' => $exception->getMessage(),
            ]);

            return $text;
        }
    }

    /**
     * @param  list<string>  $emails
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function demoUsers(array $emails)
    {
        $users = User::query()
            ->where('status', 'active')
            ->whereIn('email', $emails)
            ->get();

        if ($users->isEmpty()) {
            return User::query()->where('status', 'active')->orderBy('id')->limit(8)->get();
        }

        return $users;
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function technicians()
    {
        return $this->demoUsers([
            'faith.cheruiyot@northridge.example',
            'daniel.mwangi@northridge.example',
            'brian.otieno@northridge.example',
            'mercy.chebet@northridge.example',
            'kevin.njoroge@northridge.example',
            'lilian.auma@northridge.example',
            'amina.okello@northridge.example',
        ]);
    }

    /**
     * Users who can assign ownership in the demo RBAC.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function assigners()
    {
        return $this->demoUsers([
            'daniel.mwangi@northridge.example',
            'amina.okello@northridge.example',
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function requesters()
    {
        return $this->demoUsers([
            'grace.wanjiku@northridge.example',
            'peter.kamau@northridge.example',
            'jane.muthoni@northridge.example',
            'david.ochieng@northridge.example',
            'ruth.njeri@northridge.example',
            'naomi.wairimu@northridge.example',
            'francis.kamau@northridge.example',
            'caleb.odhiambo@northridge.example',
            'helen.achieng@northridge.example',
        ]);
    }

    private function underCap(string $type): bool
    {
        $bucket = $this->capBucket($type);
        $caps = config('plantgrid.live_pulse.daily_caps', []);
        $limit = (int) ($caps[$bucket] ?? 99);
        $state = $this->capsState();
        $used = (int) ($state['counts'][$bucket] ?? 0);

        return $used < $limit;
    }

    private function bumpCap(string $type): void
    {
        $bucket = $this->capBucket($type);
        $state = $this->capsState();
        $state['counts'][$bucket] = (int) ($state['counts'][$bucket] ?? 0) + 1;
        SystemSetting::putValue(self::SETTING_CAPS, json_encode($state), null, 'Live Pulse daily caps');
    }

    private function capBucket(string $type): string
    {
        return match ($type) {
            'internal_note', 'visible_note' => 'note',
            'new_case' => 'new_case',
            'message' => 'message',
            'monitoring' => 'monitoring',
            'resolve' => 'resolve',
            'close' => 'close',
            'assign' => 'assign',
            'progress' => 'progress',
            'starve' => 'starve',
            default => $type,
        };
    }

    /**
     * @return array{day: ?string, counts: array<string, int>}
     */
    private function capsState(?Carbon $now = null): array
    {
        $now = $now ?? now();
        $day = $now->copy()->timezone($this->endsAt()->timezone)->toDateString();
        $raw = SystemSetting::getValue(self::SETTING_CAPS);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (! is_array($decoded) || ($decoded['day'] ?? null) !== $day) {
            return ['day' => $day, 'counts' => []];
        }

        return [
            'day' => $day,
            'counts' => is_array($decoded['counts'] ?? null) ? $decoded['counts'] : [],
        ];
    }

    private function refreshCapsIfNeeded(Carbon $now): void
    {
        $state = $this->capsState($now);
        $raw = SystemSetting::getValue(self::SETTING_CAPS);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (! is_array($decoded) || ($decoded['day'] ?? null) !== $state['day']) {
            SystemSetting::putValue(self::SETTING_CAPS, json_encode($state), null, 'Live Pulse daily caps');
        }
    }
}
