<?php

namespace App\Services\LivePulse;

class EventCatalog
{
    /**
     * @return list<array{subject: string, description: string, priority: string, category: string}>
     */
    public static function cases(): array
    {
        return [
            [
                'subject' => 'Packaging labeler dark after power blip',
                'description' => "The Zebra on Packaging Hall A lost power after the morning compressor start. Display stays off; neighbouring PC is fine.\nImpact: outbound cartons cannot be labelled for the next truck window.",
                'priority' => 'high',
                'category' => 'Hardware',
            ],
            [
                'subject' => 'Warehouse VPN token rejected',
                'description' => "Remote access MFA for the warehouse supervisor is rejected after an overnight token rotate in the demo vault.\nImpact: cannot review finished-goods stock remotely.",
                'priority' => 'medium',
                'category' => 'Network connectivity',
            ],
            [
                'subject' => 'FloorWeigh PC dropped from PLT-SW-PROD',
                'description' => "Intake scale PC lost L2; MQTT heartbeats from the scale head stopped briefly then recovered.\nNeed a physical check on the floor patch lead and switch port counters.",
                'priority' => 'high',
                'category' => 'Network connectivity',
            ],
            [
                'subject' => 'Guest SSID voucher for vendor visit',
                'description' => "Maintenance expects Guest-Plant vouchers for a calibration vendor.\nDo not bridge to the plant VLAN.",
                'priority' => 'low',
                'category' => 'Other ICT request',
            ],
            [
                'subject' => 'QA lab desktop cannot open macro workbook',
                'description' => "Certificate macros fail on the QA lab PC after the latest Office patch in the demo image.\nLab techs are using a temporary export workaround.",
                'priority' => 'medium',
                'category' => 'Software',
            ],
            [
                'subject' => 'PackLabel queue backlog on PLT-APP01',
                'description' => "Print jobs queue but never ACK from the floor printer. Spooler service is up; device may be offline at layer 1.",
                'priority' => 'critical',
                'category' => 'Production technology',
            ],
            [
                'subject' => 'Admin block Wi-Fi intermittent on floor 1',
                'description' => "Staff near the finance desks see AP roam flaps every few minutes. Wired desktops are stable.",
                'priority' => 'low',
                'category' => 'Network connectivity',
            ],
            [
                'subject' => 'Backup job warning on PLT-FS01',
                'description' => "Last night's simulated file-server backup completed with warnings on a locked workbook path under Packaging shares.",
                'priority' => 'medium',
                'category' => 'Server and infrastructure',
            ],
            [
                'subject' => 'CCTV NVR disk capacity warning at Main Entrance',
                'description' => "Entrance NVR reported high disk use. Retention still meets demo policy but needs a purge or archive pass.",
                'priority' => 'low',
                'category' => 'Security',
            ],
            [
                'subject' => 'ERP login timeout for finance clerks',
                'description' => "Two finance clerks hit login timeouts opening Northridge ERP from the admin block while warehouse sessions remain open.",
                'priority' => 'high',
                'category' => 'ERP and business application',
            ],
            [
                'subject' => 'Handheld scanner drops near shrink-wrap tunnel',
                'description' => "Receiving handhelds lose association when operators walk past the shrink-wrap tunnel heater cycle.",
                'priority' => 'high',
                'category' => 'Network connectivity',
            ],
            [
                'subject' => 'Accounts payable scanner feeds double pages',
                'description' => "Finance scanner pulls two sheets and duplicates invoice images in the demonstration inbox.",
                'priority' => 'medium',
                'category' => 'Printer or scanner',
            ],
            [
                'subject' => 'Suspicious login alerts for a service account',
                'description' => "Security monitoring flagged repeated failed logins against a demonstration service account overnight from the lab VLAN story.",
                'priority' => 'high',
                'category' => 'Security',
            ],
            [
                'subject' => 'Packaging floor switch port flap reported',
                'description' => "Operators notice brief network drops on packaging terminals connected through PLT-SW-PACK.",
                'priority' => 'critical',
                'category' => 'Network connectivity',
            ],
            [
                'subject' => 'Application server memory warning on PLT-APP01',
                'description' => "Monitoring shows sustained memory pressure on the application server during the morning batch window.",
                'priority' => 'critical',
                'category' => 'Server and infrastructure',
            ],
            [
                'subject' => 'QA checklist printer prints faint pages',
                'description' => "Checklist sheets from the QA lab printer are too faint to sign even after a recent toner swap in the demo record.",
                'priority' => 'medium',
                'category' => 'Printer or scanner',
            ],
            [
                'subject' => 'Maintenance shared mailbox bouncing after reset',
                'description' => "The maintenance team mailbox stopped accepting mail after a password reset for the shared account.",
                'priority' => 'medium',
                'category' => 'User account and access',
            ],
            [
                'subject' => 'Warehouse desktop cannot open inventory spreadsheet',
                'description' => "Excel reports a file lock when opening the finished-goods inventory workbook on the warehouse PC.",
                'priority' => 'low',
                'category' => 'Software',
            ],
            [
                'subject' => 'QA lab desktop cannot reach the file share',
                'description' => "The QA laboratory desktop cannot map the shared drive used for certificate templates.",
                'priority' => 'high',
                'category' => 'Network connectivity',
            ],
            [
                'subject' => 'Shift supervisor laptop battery drains quickly',
                'description' => "The production supervisor laptop drops to low charge within an hour even when idle on the desk.",
                'priority' => 'low',
                'category' => 'Hardware',
            ],
            [
                'subject' => 'New warehouse starter needs system access',
                'description' => "A new warehouse clerk needs ERP read access and the finished-goods share mapped on day one.",
                'priority' => 'medium',
                'category' => 'User account and access',
            ],
            [
                'subject' => 'Dispatch desk label rolls nearly empty',
                'description' => "The dispatch desk has only one spare label roll left for outbound cases.",
                'priority' => 'low',
                'category' => 'Other ICT request',
            ],
            [
                'subject' => 'Purchase order export fails for supplier pack',
                'description' => "Finance cannot export the supplier purchase-order pack from Northridge ERP for the weekly close.",
                'priority' => 'high',
                'category' => 'ERP and business application',
            ],
            [
                'subject' => 'File share latency for admin block users',
                'description' => "Admin block users report slow opens on department file shares hosted on PLT-FS01.",
                'priority' => 'medium',
                'category' => 'Server and infrastructure',
            ],
            [
                'subject' => 'Intake scale freezes after twenty minutes',
                'description' => "FloorWeigh stops updating weight readings after about twenty minutes on the intake line. QA sees stale-weight rejects.",
                'priority' => 'critical',
                'category' => 'Production technology',
            ],
            [
                'subject' => 'Spare USB headset request for ICT office',
                'description' => "Request for a spare USB headset for the ICT office. Not urgent but needed for vendor calls.",
                'priority' => 'low',
                'category' => 'Other ICT request',
            ],
            [
                'subject' => 'Certificate template macros blocked on QA PCs',
                'description' => "Macro prompts appear when opening the certificate workbook after the latest Office update in the demo environment.",
                'priority' => 'medium',
                'category' => 'Software',
            ],
            [
                'subject' => 'Packaging PC black screen after power dip',
                'description' => "The packaging terminal stays on a black screen after the morning power dip. Operators cannot print dispatch notes.",
                'priority' => 'high',
                'category' => 'Hardware',
            ],
            [
                'subject' => 'Directory bind delays for finance VLAN',
                'description' => "Intermittent LDAP bind delays from the finance VLAN are stretching ERP login times in the simulation log.",
                'priority' => 'high',
                'category' => 'Server and infrastructure',
            ],
            [
                'subject' => 'Firewall policy review for guest SSID',
                'description' => "Audit asked ICT to confirm Guest-Plant still cannot reach plant VLAN 20 before the next vendor visit.",
                'priority' => 'medium',
                'category' => 'Security',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function internalNotes(): array
    {
        return [
            <<<'TXT'
[{pack}]
Internal field checklist — {case}

• Symptom: "{subject}" still reported against {device} at {location}
• Checks done: interface counters + physical reseat on the floor patch; neighbour ports healthy
• Evidence: old lead bagged; faceplate port tagged; counters noted before/after reseat
• Interconnections documented: {related}
• Priority: {priority} — watch one full production cycle before permanent lead swap
• Owner: {owner} — escalate to ICT Manager only if flaps return this shift
TXT,
            <<<'TXT'
[{pack}]
Internal impact checklist — {case}

• Area contact at {location}: can absorb one truck delay, not two
• Confirmed endpoint: {device} (neighbouring cabinet kit OK)
• Workaround: none lasting — keep truck window risk visible to Packaging lead
• Interconnections documented: {related}
• Next: {owner} posts requester-visible ETA before next dispatch cut-off
• Do not resolve until after the next production wave proves the link
TXT,
            <<<'TXT'
[{pack}]
Internal diagnostic checklist — {case}

• Root cause lean: application-layer freeze under high polling (not dead PSU)
• Power rail at junction: steady; on-screen state captured before last drop
• Device in scope: {device_tag} / {device} @ {location}
• Evidence: operator timeline + photo of freeze state filed on this case
• Interconnections documented: {related}
• Plan: stage hot-spare from ICT cage bay 3; cut over in approved lull
• Hold: no full-line rollback without Engineering review
TXT,
            <<<'TXT'
[{pack}]
Internal print-path checklist — {case}

• Root cause lean: jobs leave PackLabel queue but {device} never ACKs
• Host side: cleared zombie Rendering jobs; memory pressure correlated on app server story
• Evidence: queue drain timestamps + missing ACK from {device_tag}
• Interconnections documented: {related}
• Spare path: prepared; original job IDs stay valid until cutover
• Owner: {owner} — keep requester updates calm; no resolve until ACK proven under load
TXT,
            <<<'TXT'
[{pack}]
Internal identity checklist — {case}

• Root cause lean: stale MFA / account state — not a plant-wide directory outage
• Host / directory touchpoint: {host}
• Evidence: warehouse sessions healthy; one stale MFA method still registered
• Temporary control: SMS offered 48h while primary method cleaned
• Interconnections documented: {related}
• Await {requester} clean login from {location} → then {owner} may progress/resolve
TXT,
            <<<'TXT'
[{pack}]
Internal network checklist — {case}

• Root cause lean: physical / optics seating on path to {device}
• Fix so far: LC patch reseated server-room side; CRC growth stopped
• Evidence: old lead retained; uplink monitoring window started
• Interconnections documented: {related}
• If flaps return at intake rush: 2.4 GHz walk-test on edge kit only (not whole fleet)
• Owner: {owner} — 24h watch before calling physical layer closed
TXT,
            <<<'TXT'
[{pack}]
Internal desktop/share checklist — {case}

• Root cause lean: search suffix / trusted-location drift after Office update
• Fix so far: plant suffix added; templates share remapped on linked desktop
• Device: {device} @ {location}
• Evidence: certificate macros open without block prompt on retest
• Interconnections documented: {related}
• Await {requester} reopen-and-confirm → keep asset link for Assets history
TXT,
            <<<'TXT'
[{pack}]
Internal shift-handoff checklist — {case}

• Status narrative: work documented against {device}; pending items called out above
• Do not resolve overnight — next production wave must prove the fix
• Interconnections documented: {related}
• Evening desk: take ownership if free; ping {owner} only for parts/vendor blockers
• Requester contact: {requester} — keep visible notes factual, internals detailed
TXT,
        ];
    }

    /**
     * @return list<string>
     */
    public static function visibleNotes(): array
    {
        return [
            'We are investigating on site for case {case}. You will get another update within the hour. Please keep {device} powered if it is safe to do so.',
            'A technician ({owner}) is assigned to "{subject}". We will update this case as soon as the first on-site check at {location} is complete.',
            'Temporary workaround: use the spare unit from ICT cage bay 3 until the primary ({device}) is repaired. Keep using your original job references.',
            'Please confirm whether the issue on {device} still occurs after the lunch break restart, then reply on this case.',
            'Guest vouchers will be left at Maintenance reception before 08:30. They remain isolated from the plant VLAN.',
            'We recycled the PackLabel service briefly to clear memory pressure. Queued jobs for {device} should resume automatically — tell us if labels stay stuck.',
            'Network path restored after reseating the floor patch at {location}. Please retry {device} and reply if it drops again.',
            'Trusted location for QA templates has been restored. Please reopen the certificate workbook and confirm on this case.',
            'Access work for "{subject}" is in progress. You will receive confirmation when ERP read access and the share mapping are ready.',
            'We are monitoring the path for {device} for a short window before we close {case}. Thank you for the patience.',
        ];
    }

    /**
     * @return list<string>
     */
    public static function assignNotes(): array
    {
        return [
            <<<'TXT'
[{pack}]
Internal assign checklist — {case}

• Stage move: unowned / new → assigned
• Owner now: {owner}
• Why: priority {priority}; requester ({requester}) needs a named owner before the next production window
• Linked device: {device} @ {location}
• First actions: confirm symptom on the floor, capture device/interface state, post next internal update before another status change
• Interconnections documented: {related}
• Rule: either start in progress or park pending with a dated reason — do not leave silent
TXT,
            <<<'TXT'
[{pack}]
Internal ownership checklist — {case}

• Assigning "{subject}" to {owner}
• Scope: {device_tag} / {device} remains linked for Assets + case history
• Location: {location}
• Interconnections documented: {related}
• Expected within this shift: diagnostic dump or pending dependency note
• Escalation: ICT Manager only if parts/vendor block past 14:00 handoff
TXT,
        ];
    }

    /**
     * Rich internal notes used whenever Live Pulse advances a case stage.
     *
     * @return list<string>
     */
    public static function stageAdvanceNotes(string $from, string $to): array
    {
        $key = $from.'>'.$to;

        $map = [
            'new>assigned' => self::assignNotes(),
            'assigned>in_progress' => [
                <<<'TXT'
[{pack}]
Internal stage checklist — assigned → in progress

• Case: {case} — "{subject}"
• Why advancing: first diagnostic pass complete; symptom reproducible enough for active work
• Device: {device} @ {location}
• Evidence: counters / device state captured; spare path identified if hardware fails mid-shift
• Interconnections documented: {related}
• Owner: {owner} — requester-visible update if downtime extends past current wave
• Next: finish remediation under load, or move pending with a dated blocker (parts / window / confirmation)
TXT,
                <<<'TXT'
[{pack}]
Internal stage checklist — starting active work

• Case: {case}
• Stage: assigned → in progress
• Hands-on now against {device_tag} ({device})
• Priority: {priority}
• Interconnections documented: {related}
• Guardrail: if parts or recycle window required, park pending — do not leave status ambiguous
• Owner accountable: {owner}
TXT,
            ],
            'assigned>pending' => [
                <<<'TXT'
[{pack}]
Internal stage checklist — assigned → pending

• Case: {case} — "{subject}"
• Why parking: external dependency (parts, requester confirmation, or approved change window)
• Device held: {device} @ {location}
• Interconnections documented: {related}
• Clear next step: {owner} re-checks when dependency clears → resume in progress or resolve after verify
• Do not treat pending as abandoned
TXT,
            ],
            'new>in_progress' => [
                <<<'TXT'
[{pack}]
Internal stage checklist — new → in progress

• Case: {case} claimed by {owner}
• Why: skipping idle queue time; floor impact warrants immediate diagnosis
• First checks: power, link, application ACK on {device} @ {location}
• Interconnections documented: {related}
• Evidence to capture this visit: photos / counters / exact error text from {requester}
TXT,
            ],
            'in_progress>pending' => [
                <<<'TXT'
[{pack}]
Internal stage checklist — in progress → pending

• Case: {case} — "{subject}"
• Why pause: waiting on vendor parts, controlled recycle window, or requester confirmation before touching {device} again
• Floor impact / workaround: understood and noted for Packaging / area lead
• Evidence so far: filed on prior notes; device {device_tag} still linked
• Interconnections documented: {related}
• Next check owner: {owner} — resolve only after dependency clears and production wave looks clean
TXT,
                <<<'TXT'
[{pack}]
Internal hold checklist — pending dependency

• Case: {case}
• Stage: in progress → pending
• Blocker tied to {device} @ {location}
• Monitoring: continues where relevant
• Interconnections documented: {related}
• Resume rule: pending item closed → back to in progress → verify → then resolve
TXT,
            ],
            'pending>in_progress' => [
                <<<'TXT'
[{pack}]
Internal stage checklist — pending → in progress

• Case: {case}
• Why resuming: blocking dependency cleared (parts / window / confirmation)
• Back on: {device} @ {location} for "{subject}"
• Interconnections documented: {related}
• Owner: {owner} — verify under load before any resolve
TXT,
                <<<'TXT'
[{pack}]
Internal resume checklist — {case}

• Stage: pending → in progress
• Hold lifted; continuing remediation on {device_tag}
• Interconnections documented: {related}
• Resolve gate: clean verification pass only — no silent close
TXT,
            ],
            'in_progress>resolved' => [
                <<<'TXT'
[{pack}]
Internal wrap checklist — resolve

• Case: {case} — "{subject}"
• Root cause: addressed on {device} @ {location} in the demonstration environment
• Fix: remediation complete; verified under current production conditions
• Evidence: validation pass + prior diagnostic notes on this case
• Interconnections documented: {related}
• Await short monitoring only if useful → then close; asset link retained for history
• Owner sign-off: {owner} — nothing else outstanding
TXT,
                <<<'TXT'
[{pack}]
Internal wrap checklist — ready to leave active work

• Case: {case}
• Stage: in progress → resolved
• Device: {device_tag} / {device}
• Fix verified; no outstanding dependency for "{subject}"
• Interconnections documented: {related}
• Requester path: resolution text summarises fix; reopen only if symptom returns
TXT,
            ],
            'pending>resolved' => [
                <<<'TXT'
[{pack}]
Internal wrap checklist — pending cleared → resolve

• Case: {case}
• Root cause / blocker: cleared; final check on {device} succeeded
• Fix: complete for "{subject}" — not bouncing back through idle assigned/new
• Evidence: dependency clearance + verification note
• Interconnections documented: {related}
• Owner: {owner} — queue item can leave open work
TXT,
            ],
            'resolved>closed' => [
                <<<'TXT'
[{pack}]
Internal close checklist — resolved → closed

• Case: {case}
• Why close now: requester confirmation / monitoring window complete for {device}
• Evidence: no reopen signals after resolve
• Interconnections documented: {related}
• Archive: history + device link remain for audit
TXT,
                <<<'TXT'
[{pack}]
Internal close checklist — {case}

• Stage: resolved → closed
• Subject: "{subject}" stayed stable post-fix
• Device retained on case: {device}
• Interconnections documented: {related}
• Closing to keep the open queue honest
TXT,
            ],
        ];

        if (isset($map[$key])) {
            return $map[$key];
        }

        return [
            <<<'TXT'
[{pack}]
Internal stage checklist — {from} → {to}

• Case: {case} — "{subject}"
• Device: {device} @ {location}
• Why: continuing ICT workflow; {owner} accountable for next verification
• Interconnections documented: {related}
• Notes + Activity must stay aligned on this stage change
TXT,
        ];
    }

    /**
     * @return list<string>
     */
    public static function progressNotes(): array
    {
        return self::stageAdvanceNotes('assigned', 'in_progress');
    }

    /**
     * Keyword hints used to attach a real asset when opening a Live Pulse case.
     *
     * @return list<string>
     */
    public static function assetHintsFor(string $subject, string $category = ''): array
    {
        $haystack = strtolower($subject.' '.$category);
        $hints = [];

        foreach ([
            'label' => ['zebra', 'label', 'zt411', 'printer'],
            'scanner' => ['scanner', 'handheld', 'scan'],
            'switch' => ['switch', 'plt-sw', 'uplink'],
            'server' => ['server', 'plt-app', 'plt-fs', 'plt-ad'],
            'wifi' => ['wifi', 'wi-fi', 'ap ', 'ssid', 'wireless'],
            'erp' => ['erp', 'desktop', 'pc', 'laptop'],
            'camera' => ['cctv', 'nvr', 'camera'],
            'scale' => ['scale', 'weigh', 'floorweigh'],
        ] as $needles) {
            foreach ($needles as $needle) {
                if (str_contains($haystack, trim($needle))) {
                    array_push($hints, ...$needles);
                    break;
                }
            }
        }

        return array_values(array_unique($hints));
    }

    /**
     * @return list<array{subject: string, body: string}>
     */
    public static function messages(): array
    {
        return [
            [
                'subject' => 'Packaging hall update',
                'body' => 'Quick check — is the labeler still dark after the compressor start, or did it recover?',
            ],
            [
                'subject' => 'VPN follow-up',
                'body' => 'Please try the new demo token once and reply with the exact MFA error text if it fails again.',
            ],
            [
                'subject' => 'Parts for PG-LBL',
                'body' => 'Can you bring the hot-spare ZT411 from cage bay 3 before 13:30? Packaging needs a firm ETA.',
            ],
            [
                'subject' => 'Guest vouchers',
                'body' => 'Two Guest-Plant vouchers for the vendor visit — confirm voucher logging is still on 90-day retention.',
            ],
            [
                'subject' => 'FloorWeigh link',
                'body' => 'Gi1/0/12 looked clean after reseat. Watch for another flap during the afternoon intake rush.',
            ],
            [
                'subject' => 'Shift handoff',
                'body' => 'Leaving the open packaging cases with notes. Please take ownership if you are on the evening desk.',
            ],
            [
                'subject' => 'ERP timeout',
                'body' => 'Finance still sees login timeouts from the admin block. Warehouse sessions look fine — please confirm AD bind health.',
            ],
            [
                'subject' => 'Scanner double-feed',
                'body' => 'AP desk says the separation pad cleaning helped, but curled stock still double-feeds once in ten.',
            ],
            [
                'subject' => 'QA share mapping',
                'body' => 'After the suffix fix, can you confirm \\\\PLT-FS01\\qa-templates opens on both lab desktops?',
            ],
            [
                'subject' => 'Memory recycle window',
                'body' => 'Approved 12:30 PackLabel recycle. Post a requester-visible note ten minutes before so packaging knows labels may pause.',
            ],
            [
                'subject' => 'Mailbox permissions',
                'body' => 'Maintenance shared mailbox Full Access + Send As restored. Ask the team to remove/re-add the mailbox in Outlook.',
            ],
            [
                'subject' => 'NVR archive',
                'body' => 'Entrance NVR is above the soft disk warn. Can ICT schedule an archive pass this week?',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function monitoringMessages(): array
    {
        return [
            'Demo metric spike: CPU on PLT-FS01 above soft threshold for 5 minutes.',
            'Demo metric: packet loss probe to PLT-SW-PACK briefly elevated.',
            'Demo metric: disk warn on entrance NVR volume crossed 80%.',
            'Demo metric: memory pressure on PLT-APP01 during PackLabel batch window.',
            'Demo metric: response-time climb on PLT-AD01 LDAP binds from finance VLAN.',
            'Demo metric: availability flap on PLT-AP-PACK during shrink-wrap heater cycle.',
            'Demo metric: disk utilisation warning on PLT-FS01 volume D: after archive job.',
            'Demo metric: critical alert cleared after stuck backup job was cancelled on PLT-FS01.',
        ];
    }

    /**
     * Requester-facing / resolution_notes field (still multi-line, but shorter than internals).
     *
     * @return list<string>
     */
    public static function resolveNotes(): array
    {
        return [
            "Resolved {case} — \"{subject}\".\n\nRoot cause confirmed and restored on {device} at {location}. Monitoring for recurrence this shift; reopen only if the symptom returns.",
            "Resolved {case}.\n\nReplaced or reseated the failing component on {device}. Service verified with the area contact. Asset history retained.",
            "Resolved {case}.\n\nConfiguration corrected and validated for {device}. Closing path opened after acknowledgement in the demo thread.",
            "Resolved {case}.\n\nHot-spare cutover completed; jobs ACK again on the replacement path. Original unit tagged for vendor collection.",
            "Resolved {case}.\n\nTrusted location / preference restored on the linked desktop. Macros and workbooks open cleanly.",
            "Resolved {case}.\n\nPatch lead replaced; CRC growth stopped toward {device}. Short monitoring window complete with no further flaps.",
            "Resolved {case}.\n\nWorker concurrency capped; memory pressure stayed under warning through the afternoon wave.",
            "Resolved {case}.\n\nShare path remapped after DNS suffix fix. Templates open normally from the lab desktop on this case.",
            "Resolved {case}.\n\nShared mailbox permissions rebuilt; Outlook profiles re-added. Mail flow restored in the simulation.",
            "Resolved {case}.\n\nScanner separation pad cleaned and adjusted on {device}. Finance confirmed clean single-sheet feeds on flat stock.",
        ];
    }
}
