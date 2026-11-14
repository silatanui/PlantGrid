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
                'slug' => 'getting-started',
                'title' => 'Getting started with PlantGrid',
                'summary' => 'A short orientation for first-time visitors: what this demo is, what you will see after sign-in, and where to click next.',
                'audience' => 'Novice users',
                'keywords' => ['getting started', 'beginner', 'intro', 'overview', 'new user', 'start here', 'welcome', 'demo'],
                'sections' => [
                    [
                        'heading' => 'What PlantGrid is',
                        'body' => "PlantGrid is a portfolio demonstration of manufacturing ICT operations. The sample plant is Northridge Processing - packaging halls, intake scales, servers, network gear and helpdesk cases that behave like a real operations console.\n\nYou are not managing a live factory. The data is seeded for learning and walkthroughs, and some activity continues on its own so the desk still feels alive.",
                    ],
                    [
                        'heading' => 'What you can do in a few minutes',
                        'body' => "1. Sign in with a demo account (every account uses the password PlantGrid-Demo).\n2. Open the Dashboard to see open work and plant health at a glance.\n3. Open My cases or the Case queue to read and update helpdesk work.\n4. Try Documentation (this library) or ask the Assistant a question about open cases or an asset tag.\n5. Switch to a different demo role later if you want to see how permissions change.",
                    ],
                    [
                        'heading' => 'Suggested first path',
                        'body' => "• New to ICT tools? Start with Finding your way around, then How to report an issue.\n• ICT / technician role? Read How to take ownership of a case and How to write internal notes.\n• Manager or admin? Open Choosing a demo role, then Online users or Performance when you are ready.",
                    ],
                ],
                'screenshots' => [],
            ],
            [
                'slug' => 'demo-roles',
                'title' => 'Choosing a demo role',
                'summary' => 'Pick the right sample account so the menus and buttons match the story you want to walk through.',
                'audience' => 'Novice users',
                'keywords' => ['roles', 'permissions', 'demo account', 'sign in', 'amina', 'faith', 'employee', 'administrator'],
                'sections' => [
                    [
                        'heading' => 'Why roles matter',
                        'body' => 'PlantGrid shows different menus and actions depending on who you are. A general employee mainly reports and follows their own cases. An ICT technician works the queue. A system administrator sees users, settings and plant-wide controls.',
                    ],
                    [
                        'heading' => 'Accounts to try',
                        'body' => "• System Administrator (Amina Okello) - best starting point for the full console: users, settings, cases, assets.\n• ICT Manager (Daniel Mwangi) - operations view across assets, network, cases and monitoring.\n• ICT Technician (Faith Cheruiyot) - day-to-day case work, notes and ownership.\n• Department Manager (Peter Kamau) - department-facing view of requests and people.\n• General Employee (Grace Wanjiku) - self-service: report issues and follow My cases.\n• Auditor (Helen Achieng) - read-focused review of records and assurance areas.\n\nPassword for every demo account: PlantGrid-Demo.",
                    ],
                    [
                        'heading' => 'How to switch',
                        'body' => "1. Open your account menu (initials in the app bar) and Sign out.\n2. On the sign-in page, choose another role card - the email fills in automatically.\n3. Sign in again and notice which modules appear in the left navigation.",
                    ],
                ],
                'screenshots' => [],
            ],
            [
                'slug' => 'find-your-way',
                'title' => 'Finding your way around',
                'summary' => 'Learn the app bar, left navigation, search, messages bell and footer so you always know where you are.',
                'audience' => 'Novice users',
                'keywords' => ['navigation', 'menu', 'sidebar', 'app bar', 'search', 'where is', 'layout', 'mobile'],
                'sections' => [
                    [
                        'heading' => 'The main chrome',
                        'body' => "• Top app bar - PlantGrid logo, global search, online users (when allowed), notifications, messages, alerts and your account menu.\n• Second row - quick tabs such as Dashboard, My cases, Messages and Documentation.\n• Left navigation - modules grouped into Operations, Infrastructure, Assurance and Administration. On a phone, open it with the menu (hamburger) button.\n• Right utility panel - shortcuts and guidance on wider screens; open it with the panel button on smaller screens.\n• Footer - Home, Dashboard, Cases and About links that stay available while you scroll.",
                    ],
                    [
                        'heading' => 'Search',
                        'body' => "Type in the top search box to find people, cases, assets, servers, network devices, documentation and named pages. On phones the search box is hidden - use Find from search results pages or open Documentation and search guides there.",
                    ],
                    [
                        'heading' => 'If a menu item is missing',
                        'body' => 'That usually means your demo role does not have permission for it. Sign out and try System Administrator or ICT Manager to see the full set of modules.',
                    ],
                ],
                'screenshots' => [],
            ],
            [
                'slug' => 'report-an-issue',
                'title' => 'How to report an issue',
                'summary' => 'For employees and first-time users: open a clear helpdesk case without ICT jargon.',
                'audience' => 'Novice users',
                'keywords' => ['report issue', 'employee', 'help', 'broken printer', 'request', 'new case', 'self service'],
                'sections' => [
                    [
                        'heading' => 'Before you start',
                        'body' => "Have ready: where you are (for example Packaging Hall), what stopped working, who is blocked, and any asset tag on a sticker if you can see one. One problem per case keeps ICT work clear.",
                    ],
                    [
                        'heading' => 'Steps',
                        'body' => "1. Open New case from the left navigation or Cases.\n2. Choose your department if asked.\n3. Pick the closest category (printer, network, access, and so on).\n4. Write a short subject people can scan in a list - for example “Packaging label printer offline”.\n5. In the description, say what you tried, what you see, and how urgent it is for the line or office.\n6. Set priority honestly. Use Critical only when production or safety is blocked now.\n7. Save. Note the case number (for example PG-2026-0180) so you can find it later under My cases.",
                    ],
                    [
                        'heading' => 'After you submit',
                        'body' => "Watch My cases for status changes and notes visible to you. You may get in-app notifications when ICT assigns the work or posts an update. For a more detailed form walkthrough, see How to create a case.",
                    ],
                ],
                'screenshots' => [
                    ['file' => 'docs-create-case.png', 'caption' => 'New case form used when reporting an issue'],
                ],
            ],
            [
                'slug' => 'my-cases',
                'title' => 'How to follow your cases',
                'summary' => 'Use My cases, Queue and filters to see what you opened, what is assigned to you, and what still needs work.',
                'audience' => 'Novice users',
                'keywords' => ['my cases', 'queue', 'filter', 'status', 'follow up', 'open cases', 'list'],
                'sections' => [
                    [
                        'heading' => 'Views you will see',
                        'body' => "• My cases - work tied to you (often as requester or assignee, depending on role).\n• Queue - broader open work for ICT staff.\n• Assigned to me - cases you own as the technician.\n\nTabs and badges on the navigation help you jump back without hunting.",
                    ],
                    [
                        'heading' => 'Reading the list',
                        'body' => "Each row shows the case number, subject and status. On a phone, PlantGrid keeps those three columns so the list stays readable; other columns hide until you use a wider screen.\n\nOpen a row to see ownership, SLA, notes and status updates.",
                    ],
                    [
                        'heading' => 'Filters',
                        'body' => "Use Search for a case number or subject words. Status and Priority narrow the list. Apply runs the filter. Clear the fields and Apply again to return to the full view.",
                    ],
                ],
                'screenshots' => [],
            ],
            [
                'slug' => 'messages-basics',
                'title' => 'How to use Messages',
                'summary' => 'Open the inbox, read plant messages, and delete items you no longer need.',
                'audience' => 'Novice users',
                'keywords' => ['messages', 'inbox', 'mail', 'notification', 'delete message'],
                'sections' => [
                    [
                        'heading' => 'Open Messages',
                        'body' => "Choose Messages from the top tabs or left navigation, or use the messages icon in the app bar. Unread counts appear as badges when new items arrive.",
                    ],
                    [
                        'heading' => 'What to do with a message',
                        'body' => "1. Open a message to read the full text.\n2. Follow any case or page links inside it.\n3. Delete a message when you are finished - PlantGrid asks you to confirm before it is removed.\n\nMessages are part of the demonstration story. They are not a real mailbox outside PlantGrid.",
                    ],
                ],
                'screenshots' => [],
            ],
            [
                'slug' => 'search-people-assets',
                'title' => 'How to find people, assets and pages',
                'summary' => 'Use global search and record links to jump from a name, tag or case number to the right screen.',
                'audience' => 'Novice users',
                'keywords' => ['search', 'people', 'assets', 'tag', 'hostname', 'find', 'lookup'],
                'sections' => [
                    [
                        'heading' => 'Global search',
                        'body' => "In the app bar, type a person name, email, case number (PG-2026-…), asset tag, hostname or page name such as “My cases”. Suggestions appear as you type; Enter opens the full results page grouped by type.",
                    ],
                    [
                        'heading' => 'People and assets',
                        'body' => "• People records show role, contact details and related cases.\n• Assets are plant devices and equipment with tags like PG-LBL-0142. Open an asset to see status, location and linked cases.\n• From a case, person chips and asset links jump you straight to those records.",
                    ],
                    [
                        'heading' => 'Tip',
                        'body' => 'If you only remember part of a word, search that fragment. Documentation guides are included in search results too.',
                    ],
                ],
                'screenshots' => [],
            ],
            [
                'slug' => 'plant-map-basics',
                'title' => 'Plant map for beginners',
                'summary' => 'See where halls, rooms and equipment sit in the Northridge demonstration plant.',
                'audience' => 'Novice users',
                'keywords' => ['plant map', 'locations', 'floor', 'packaging', 'map', 'where is'],
                'sections' => [
                    [
                        'heading' => 'What the map is for',
                        'body' => 'The plant map helps you place issues in space: which hall, room or zone an asset or case belongs to. It is a demonstration layout for Northridge Processing, not a live CAD drawing.',
                    ],
                    [
                        'heading' => 'How to use it',
                        'body' => "1. Open Plant map from Infrastructure in the left navigation (you need asset view permission).\n2. Pan and zoom to find packaging, intake, ICT or office areas.\n3. Select locations or devices to open their records.\n4. When you raise a case, mention the area you saw on the map so ICT can go to the right place.",
                    ],
                ],
                'screenshots' => [],
            ],
            [
                'slug' => 'monitoring-basics',
                'title' => 'Monitoring alerts for beginners',
                'summary' => 'Read the Monitoring board without deep networking knowledge: what green, warning and red mean in this demo.',
                'audience' => 'Novice users',
                'keywords' => ['monitoring', 'alerts', 'outage', 'red', 'network health', 'pulse'],
                'sections' => [
                    [
                        'heading' => 'What you are looking at',
                        'body' => "Monitoring shows simulated health for plant network and related devices. Most of the time the board stays calm. Occasionally the demo runs a short outage story so you can practise reading critical alerts.",
                    ],
                    [
                        'heading' => 'How to respond as a learner',
                        'body' => "1. Open Monitoring from Operations (permission required).\n2. Read the alert title, severity and related device name.\n3. Open the device or linked case if one exists.\n4. Ask the Assistant something like “Which alerts are open?” if you want a spoken summary.\n\nYou do not need to fix real infrastructure - use the board to practise triage language with the rest of the console.",
                    ],
                ],
                'screenshots' => [],
            ],
            [
                'slug' => 'glossary',
                'title' => 'Glossary of common PlantGrid words',
                'summary' => 'Plain definitions for case, SLA, asset, owner, priority and other words used across the console.',
                'audience' => 'Novice users',
                'keywords' => ['glossary', 'dictionary', 'definition', 'what is', 'terms', 'vocabulary', 'SLA', 'asset'],
                'sections' => [
                    [
                        'heading' => 'Cases and people',
                        'body' => "• Case / ticket - a tracked request for ICT work, with a number like PG-2026-0150.\n• Requester - the person who reported the issue.\n• Owner / assignee - the technician responsible for the current work.\n• Partner - someone kept in the loop on a case (requester, owner, mentioned people, or added partners).\n• Internal note - ICT-only commentary; not shown to the requester.",
                    ],
                    [
                        'heading' => 'Priority and time',
                        'body' => "• Priority - Critical, High, Medium or Low. Higher priority means a shorter SLA target.\n• SLA - service level agreement: the target time to handle the case before it is At risk or Breached.\n• Status - where the case sits on the path (New, Assigned, In progress, Pending, Resolved, Closed).",
                    ],
                    [
                        'heading' => 'Plant records',
                        'body' => "• Asset - a tracked device or equipment item with a tag.\n• Location - a place on the plant map (hall, room, zone).\n• Network device / server - infrastructure records used in Network, Servers and Monitoring.\n• Assistant - the in-app helper that answers from live demo data and can propose actions you must confirm.",
                    ],
                ],
                'screenshots' => [],
            ],
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
                        'body' => "1. Open Cases from the navigation.\n2. Choose New case.\n3. Select the department that owns the issue (for example Production / Packaging maps to Production).\n4. Pick a category that matches the work (printer, network, production technology, and so on).\n5. Write a short subject and a clear description of impact.\n6. Set priority honestly - critical only when production is blocked.\n7. Link an asset tag when you know it (for example PG-LBL-0142).\n8. Save. PlantGrid assigns a case number such as PG-2026-0150 and starts the SLA clock.",
                    ],
                    [
                        'heading' => 'Tips',
                        'body' => "• Mention location and who is affected.\n• Use @people and asset tags in the description when helpful.\n• You can also ask the Assistant to propose a case, then confirm before it is created.\n• New to PlantGrid? Start with How to report an issue for a simpler walkthrough.",
                    ],
                ],
                'screenshots' => [
                    ['file' => 'docs-create-case.png', 'caption' => 'New case form - department, category, priority and description'],
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
                        'body' => "• Internal ICT note - only staff with update access see it.\n• Visible to requester - the person who opened the case can read it.",
                    ],
                    [
                        'heading' => 'Steps',
                        'body' => "1. Open the case and scroll to Notes.\n2. Click Add a note to open the composer.\n3. Write the note. Use @ to mention people and # or asset/case IDs to link records.\n4. Choose Internal ICT note or Visible to requester.\n5. Attach a file if needed, then Post.\n6. Case partners (requester, owner, @mentioned people, and manually added partners) get in-app notifications. @mentions get a dedicated “You were mentioned” alert. Internal notes notify ICT partners only.\n7. Only the author can Edit a note. The author or a System Administrator can Delete it.",
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
                        'body' => "1. Open the case that should be closed.\n2. Under Update status, choose Closed (duplicate).\n3. Enter the original case number (for example PG-2026-0146).\n4. Add a resolution / next-step note if needed.\n5. Save update. The case shows a Duplicate badge and links to the original.",
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
                        'body' => "1. Confirm business impact (line down, safety, compliance, many users).\n2. Raise priority if the current level is too low.\n3. Assign or take ownership so someone is accountable.\n4. Move status to In progress or Pending with a clear resolution / next-step note.\n5. Use Add a note to post an ICT-only update that states why it escalated and who was informed.\n6. Watch the SLA badge - critical cases have a 4-hour target.",
                    ],
                    [
                        'heading' => 'Escalation note template',
                        'body' => "Escalation reason: [impact]\nCurrent blocker: [what is stopping work]\nAsked of: [@manager or vendor]\nNeeded by: [time]\nCustomer update: [what requesters were told]",
                    ],
                    [
                        'heading' => 'What not to do',
                        'body' => 'Do not silently change priority without a note. Do not open a second case for the same outage - link or close duplicates instead.',
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
                        'body' => "• Critical - 4 hours\n• High - 8 hours\n• Medium - 24 hours\n• Low - 72 hours\n\nTargets start from the reported time. Badges show On track, At risk, or Breached on open cases.",
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
                        'body' => "• Open - cases currently assigned and not resolved/closed.\n• Intake - assigned cases reported in the selected range.\n• Resolved / CPM - finished work and Cases Per Month (resolved ÷ months in range).\n• Avg h - average hours from reported_at to resolved_at.\n• SLA % - share of resolved cases finished on or before sla_due_at without a breach.\n• Reopen % - closed/resolved cases that later moved back to an open status.",
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
        $tokens = collect(preg_split('/[^a-z0-9]+/i', $needle) ?: [])
            ->map(fn ($token) => trim((string) $token))
            ->filter(fn ($token) => strlen($token) >= 3)
            ->unique()
            ->values();

        return self::collection()
            ->map(function (array $doc) use ($needle, $tokens) {
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

                foreach ($tokens as $token) {
                    if (in_array($token, ['doc', 'docs', 'documentation', 'guide', 'guides', 'manual', 'tutorial'], true)) {
                        $score += 2;
                        continue;
                    }
                    if (str_contains(Str::lower($doc['title']), $token)) {
                        $score += 2;
                    } elseif (str_contains($haystack, $token)) {
                        $score += 1;
                    }
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
