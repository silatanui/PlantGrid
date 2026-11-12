<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DocumentationCatalog
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            [
                'slug' => 'create-a-case',
                'title' => 'How to create a case',
                'summary' => 'Open a new helpdesk case with the right department, category, priority and optional asset.',
                'audience' => 'All users',
                'keywords' => ['create case', 'new ticket', 'open case', 'helpdesk', 'report issue', 'template'],
                'sections' => [
                    [
                        'heading' => 'When to use this',
                        'body' => 'Use a case whenever something needs ICT attention: printers, network, servers, production systems, or a general request. Prefer one clear request per case.',
                    ],
                    [
                        'heading' => 'Steps',
                        'body' => "1. Open Cases from the navigation.\n2. Choose New case.\n3. Select the department that owns the issue (for example Production / Packaging maps to Production).\n4. Pick a category that matches the work (printer, network, production technology, and so on).\n5. Write a short subject and a clear description of impact.\n6. Set priority honestly — critical only when production is blocked.\n7. Link an asset tag when you know it (for example PG-LBL-0142).\n8. Save. PlantGrid assigns a case number such as PG-2026-0150 and starts the SLA clock.",
                    ],
                    [
                        'heading' => 'Tips',
                        'body' => "• Mention location and who is affected.\n• Use @people and asset tags in the description when helpful.\n• You can also ask the Assistant to propose a case, then confirm before it is created.",
                    ],
                ],
                'screenshots' => [
                    ['file' => 'docs-create-case.png', 'caption' => 'New case form — department, category, priority and description'],
                ],
            ],
            [
                'slug' => 'take-ownership',
                'title' => 'How to take ownership of a case',
                'summary' => 'Assign an open case to yourself in one step when you are ready to own the work.',
                'audience' => 'ICT technicians and managers',
                'keywords' => ['take ownership', 'accept case', 'assign myself', 'owner', 'claim'],
                'sections' => [
                    [
                        'heading' => 'When to use this',
                        'body' => 'Use Take ownership when you are ready to work an unassigned or reassigned case. You need tickets.assign or tickets.update permission.',
                    ],
                    [
                        'heading' => 'Steps',
                        'body' => "1. Open the case record.\n2. In the Ownership card, choose Take ownership.\n3. If the case was New, status moves to Assigned and you become Owner.\n4. Continue with notes and status updates as you work.",
                    ],
                    [
                        'heading' => 'Assistant option',
                        'body' => 'You can ask the Assistant: “Take ownership of PG-2026-0148”. Review the proposal card, then Confirm.',
                    ],
                ],
                'screenshots' => [
                    ['file' => 'docs-take-ownership.png', 'caption' => 'Case record with Take ownership action'],
                ],
            ],
            [
                'slug' => 'internal-notes',
                'title' => 'How to write internal notes',
                'summary' => 'Add ICT-only or requester-visible notes, with @mentions, tags, and live edit.',
                'audience' => 'ICT staff',
                'keywords' => ['internal note', 'comment', 'mention', 'ICT note', 'visibility'],
                'sections' => [
                    [
                        'heading' => 'Visibility',
                        'body' => "• Internal ICT note — only staff with update access see it.\n• Visible to requester — the person who opened the case can read it.",
                    ],
                    [
                        'heading' => 'Steps',
                        'body' => "1. Open the case and scroll to Notes.\n2. Write the note. Use @ to mention people and # or asset/case IDs to link records.\n3. Choose Internal ICT note or Visible to requester.\n4. Attach a file if needed, then Post note.\n5. Case partners (requester, owner, @mentioned people, and manually added partners) get in-app notifications. @mentions get a dedicated “You were mentioned” alert. Internal notes notify ICT partners only.\n6. Only the author can Edit a note. The author or a System Administrator can Delete it.",
                    ],
                    [
                        'heading' => 'Writing template',
                        'body' => "Situation: what was observed\nAction: what you did or asked for\nNext: what still needs to happen / who owns it\n\nExample: “Printer PG-LBL-0142 offline at Packaging Hall. Power LED dark. Asked @Faith Cheruiyot to check the floor outlet. Holding for parts if fuse failed.”",
                    ],
                ],
                'screenshots' => [
                    ['file' => 'docs-internal-notes.png', 'caption' => 'Notes panel with visibility options and mention hint'],
                ],
            ],
            [
                'slug' => 'close-duplicates',
                'title' => 'How to close a case as a duplicate',
                'summary' => 'Close an open case against the surviving original case without forcing a full resolve path.',
                'audience' => 'ICT staff with close permission',
                'keywords' => ['duplicate', 'close duplicate', 'merged case', 'duplicate of'],
                'sections' => [
                    [
                        'heading' => 'When to use this',
                        'body' => 'Use duplicate close when the same issue already has an active case. Keep the stronger/surviving case open and close the copy.',
                    ],
                    [
                        'heading' => 'Steps',
                        'body' => "1. Open the case that should be closed.\n2. Under Update status, choose Closed (duplicate).\n3. Enter the original case number (for example PG-2026-0146).\n4. Optionally add resolution or history notes.\n5. Save update. The case shows a Duplicate badge and links to the original.",
                    ],
                    [
                        'heading' => 'Assistant option',
                        'body' => 'Ask: “Close PG-2026-0152 as a duplicate of PG-2026-0149”, then Confirm on the proposal card.',
                    ],
                ],
                'screenshots' => [
                    ['file' => 'docs-close-duplicate.png', 'caption' => 'Update status with Closed (duplicate) and original case field'],
                ],
            ],
            [
                'slug' => 'escalations',
                'title' => 'How to escalate a case',
                'summary' => 'Raise urgency, reassign, and communicate clearly when a case needs stronger attention.',
                'audience' => 'ICT technicians and managers',
                'keywords' => ['escalate', 'escalation', 'priority', 'critical', 'manager', 'SLA'],
                'sections' => [
                    [
                        'heading' => 'Escalation checklist',
                        'body' => "1. Confirm business impact (line down, safety, compliance, many users).\n2. Raise priority if the current level is too low.\n3. Assign or take ownership so someone is accountable.\n4. Move status to In progress or Pending with a clear history note.\n5. Add an internal note that states why it escalated and who was informed.\n6. Watch the SLA badge — critical cases have a 4-hour target.",
                    ],
                    [
                        'heading' => 'Escalation note template',
                        'body' => "Escalation reason: [impact]\nCurrent blocker: [what is stopping work]\nAsked of: [@manager or vendor]\nNeeded by: [time]\nCustomer update: [what requesters were told]",
                    ],
                    [
                        'heading' => 'What not to do',
                        'body' => 'Do not silently change priority without a note. Do not open a second case for the same outage — link or close duplicates instead.',
                    ],
                ],
                'screenshots' => [
                    ['file' => 'docs-escalation.png', 'caption' => 'Case highlights showing priority and SLA status during escalation'],
                ],
            ],
            [
                'slug' => 'assign-and-update-status',
                'title' => 'How to assign and update case status',
                'summary' => 'Move a case through New → Assigned → In progress → Pending → Resolved → Closed.',
                'audience' => 'ICT staff',
                'keywords' => ['assign', 'status', 'resolve', 'workflow', 'technician'],
                'sections' => [
                    [
                        'heading' => 'Status path',
                        'body' => 'The chevron path on the case shows where work sits. Available next steps depend on your permissions.',
                    ],
                    [
                        'heading' => 'Assign',
                        'body' => "1. Open Update status.\n2. Choose Assigned or In progress.\n3. Select a technician.\n4. Save update. The assignee receives a notification.",
                    ],
                    [
                        'heading' => 'Resolve and close',
                        'body' => "• Resolved requires resolution notes.\n• Closed normally follows Resolved.\n• Closed (duplicate) can be used from open statuses when you supply the original case.",
                    ],
                ],
                'screenshots' => [
                    ['file' => 'docs-status-path.png', 'caption' => 'Case status path and Update status panel'],
                ],
            ],
            [
                'slug' => 'assistant-actions',
                'title' => 'How to use the PlantGrid Assistant',
                'summary' => 'Ask live questions and confirm agent actions such as create case, take ownership, or close duplicate.',
                'audience' => 'All signed-in users (actions depend on permissions)',
                'keywords' => ['assistant', 'AI', 'chatbot', 'propose', 'confirm', 'create case'],
                'sections' => [
                    [
                        'heading' => 'Chat',
                        'body' => 'Open Assistant from the sparkles button. Ask about open cases, alerts, assets, or people. Answers use the live demo snapshot.',
                    ],
                    [
                        'heading' => 'Confirmed actions',
                        'body' => "When the Assistant proposes an action you will see a card with details.\n• Confirm create / Confirm take ownership / Confirm close duplicate runs the change.\n• Cancel leaves the system unchanged.\nNothing is written until you confirm.",
                    ],
                    [
                        'heading' => 'Example prompts',
                        'body' => "• Create a high-priority Packaging case for the label printer, asset PG-LBL-0142\n• Take ownership of PG-2026-0148\n• Close PG-2026-0152 as a duplicate of PG-2026-0149\n• Which cases are still open and who owns them?",
                    ],
                ],
                'screenshots' => [
                    ['file' => 'docs-assistant.png', 'caption' => 'Assistant chat with a confirmable action proposal'],
                ],
            ],
            [
                'slug' => 'sla-and-notifications',
                'title' => 'SLA targets and notifications',
                'summary' => 'Understand priority SLA windows, at-risk/breached badges, and the notification bell.',
                'audience' => 'ICT staff',
                'keywords' => ['SLA', 'due', 'breach', 'notification', 'bell', 'at risk'],
                'sections' => [
                    [
                        'heading' => 'SLA targets',
                        'body' => "• Critical — 4 hours\n• High — 8 hours\n• Medium — 24 hours\n• Low — 72 hours\n\nTargets start from the reported time. Badges show On track, At risk, or Breached on open cases.",
                    ],
                    [
                        'heading' => 'Notifications',
                        'body' => 'The appbar bell shows unread items for case assignment, new notes, and SLA at-risk or breached alerts. Open an item to jump to the case, or mark all read.',
                    ],
                ],
                'screenshots' => [
                    ['file' => 'docs-sla-notifications.png', 'caption' => 'SLA badge on a case and notification bell in the app bar'],
                ],
            ],
            [
                'slug' => 'online-users',
                'title' => 'How to see who is online and assign cases',
                'summary' => 'System administrators can view live presence and assign open cases to people who are online.',
                'audience' => 'System administrators',
                'keywords' => ['online', 'presence', 'assign', 'who is online', 'last seen'],
                'sections' => [
                    [
                        'heading' => 'Where to look',
                        'body' => "1. Open Online users from the top navigation or Administration.\n2. The radio icon in the app bar also shows who is online right now.\n3. Presence updates live: signed-in people send a heartbeat every few seconds, and they drop offline within about a minute after they leave or close the tab.",
                    ],
                    [
                        'heading' => 'Assign a case',
                        'body' => "1. On the Online users page, choose an open case next to the person.\n2. Click Assign.\n3. New cases move to Assigned; other open cases keep their status and change owner.\n4. The assignee receives a notification.",
                    ],
                    [
                        'heading' => 'On a case record',
                        'body' => 'In Update status → Technician, online staff are marked with “Online” so you can prefer people who are available.',
                    ],
                ],
                'screenshots' => [],
            ],
            [
                'slug' => 'worker-performance',
                'title' => 'How to read worker performance',
                'summary' => 'Use the Performance board to compare ICT case throughput (CPM), open load, resolution time, and SLA quality.',
                'audience' => 'ICT managers and supervisors',
                'keywords' => ['performance', 'CPM', 'cases per month', 'SLA', 'workload', 'leaderboard', 'productivity'],
                'sections' => [
                    [
                        'heading' => 'Open the board',
                        'body' => "1. Open Performance under Assurance (or Worker performance from Reports).\n2. You need reports.view. Export CSV needs reports.export.\n3. Set the date range, department, role, or person, then Apply.",
                    ],
                    [
                        'heading' => 'What the metrics mean',
                        'body' => "• Open — cases currently assigned and not resolved/closed.\n• Intake — assigned cases reported in the selected range.\n• Resolved / CPM — finished work and Cases Per Month (resolved ÷ months in range).\n• Avg h — average hours from reported_at to resolved_at.\n• SLA % — share of resolved cases finished on or before sla_due_at without a breach.\n• Reopen % — closed/resolved cases that later moved back to an open status.",
                    ],
                    [
                        'heading' => 'How to use it well',
                        'body' => "• Compare similar roles (for example ICT Technician with ICT Technician).\n• Pair CPM with SLA and reopen so speed is not rewarded without quality.\n• Use Overloaded (open above the threshold) to rebalance intake before coaching on throughput.\n• Click a worker for a six-month resolved trend and their live open queue.",
                    ],
                ],
                'screenshots' => [],
            ],
        ];
    }

    public static function collection(): Collection
    {
        return collect(self::all());
    }

    public static function find(string $slug): ?array
    {
        return self::collection()->firstWhere('slug', $slug);
    }

    public static function search(string $term, int $limit = 8): Collection
    {
        $term = trim($term);
        if ($term === '') {
            return collect();
        }

        $needle = Str::lower($term);

        return self::collection()
            ->map(function (array $doc) use ($needle) {
                $haystack = Str::lower(implode(' ', [
                    $doc['title'],
                    $doc['summary'],
                    $doc['audience'],
                    implode(' ', $doc['keywords'] ?? []),
                    collect($doc['sections'] ?? [])->pluck('body')->implode(' '),
                ]));

                $score = 0;
                if (str_contains(Str::lower($doc['title']), $needle)) {
                    $score += 5;
                }
                foreach ($doc['keywords'] ?? [] as $keyword) {
                    if (str_contains(Str::lower((string) $keyword), $needle) || str_contains($needle, Str::lower((string) $keyword))) {
                        $score += 3;
                    }
                }
                if (str_contains($haystack, $needle)) {
                    $score += 1;
                }

                $doc['_score'] = $score;

                return $doc;
            })
            ->filter(fn (array $doc) => ($doc['_score'] ?? 0) > 0)
            ->sortByDesc('_score')
            ->take($limit)
            ->values();
    }
}
