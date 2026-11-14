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
            "Case {case} — field update on {device} at {location}.\n\nChecked interface counters and physical seating on the floor patch. Link lights are stable after reseat, but error counters had already climbed during the morning wave. Left the old lead bagged for evidence and tagged the port on the switch faceplate.\n\nNext: watch one full production cycle before we decide on a permanent lead swap. Priority remains {priority}.",
            "Case {case} ({subject}).\n\nSpoke with the area supervisor at {location}. They can absorb a short delay on one truck window but not a second miss. Confirmed {device} is still the affected endpoint and that neighbouring kit on the same cabinet is healthy.\n\nAction: keep this case owned by {owner}; escalate to ICT Manager only if parts are unconfirmed before the 14:00 handoff.",
            "Diagnostic pass for {case} on {device}.\n\nPower rail at the junction measured steady. Application-layer behaviour still matches a freeze under high polling rather than a dead PSU. Captured the on-screen state and a short note of what the operator saw before the drop.\n\nPlan: stage a hot-spare from ICT cage bay 3 and cut over during the next approved lull. Do not full-line rollback without Engineering review.",
            "PackLabel / print path check for {case}.\n\nService on the app host accepts jobs and the queue drains, but {device} never ACKs. Cleared two zombie Rendering jobs and correlated with memory pressure on the application server story.\n\nInternal: keep requester updates calm — spare printer path is prepared; original job IDs stay valid until cutover is confirmed.",
            "Identity / access note for {case}.\n\nAccount state reviewed in the demo directory. One stale MFA method was still registered; temporary SMS offered for 48 hours while the primary method is cleaned. No evidence of a broader directory outage — warehouse sessions remain healthy.\n\n{owner} retains ownership until the requester confirms a clean login from {location}.",
            "Network path note for {case} / {device} at {location}.\n\nCRC growth stopped after reseating the LC patch in the server room side. Old lead retained. Will leave a 24-hour monitoring window on the uplink before we call the physical layer closed.\n\nIf flaps return during the afternoon intake rush, force the handheld / edge kit to 2.4 GHz for a controlled walk-test only — do not move the whole fleet yet.",
            "Desktop / share remediation for {case}.\n\nAdded the plant search suffix on the QA desktop and remapped the templates share. Certificate macros open without the trusted-location block after the Office update preference restore.\n\nAsk the requester to reopen the workbook once and reply on this case. Keep {device} linked so Assets history stays accurate.",
            "Shift handoff — {case} ({subject}).\n\nWork so far is documented against {device}. Evening desk should take ownership if free; do not resolve until the next production wave proves the fix. Pending items and parts are called out in prior notes.\n\nMarker kept for Live Pulse audit scrub.",
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
            "Assigning {case} ({subject}) to {owner}.\n\nWhy now: the case is unowned in the ICT queue and matches this technician's plant area / skill. Linked device is {device} at {location}.\n\nExpected first actions: confirm the symptom on the floor, capture interface or device state, and post the next internal update before changing status again.",
            "Ownership for {case} → {owner}.\n\nRationale: priority is {priority} and the requester needs a named owner before the next production window. {device} stays linked so Asset and case history stay aligned.\n\n{owner} should either start active work (in progress) or park with a clear pending reason — do not leave the case silent.",
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
                "Moving {case} from assigned → in progress.\n\nWhy: {owner} has confirmed ownership and completed the first diagnostic pass on {device} at {location}. Symptom for \"{subject}\" is reproducible enough to work actively now.\n\nEvidence / next checks: counters or device state captured; spare path identified if hardware fails mid-shift. Requester-visible update will follow if downtime extends past the current wave.",
                "Status advance {case}: assigned → in progress.\n\nWe are no longer waiting in the queue — hands-on work has started against {device}. Priority remains {priority}. If parts or a recycle window are required, we will move to pending with a dated next check rather than leaving the status ambiguous.",
            ],
            'assigned>pending' => [
                "Parking {case} assigned → pending.\n\nWhy: work cannot finish without an external dependency (parts, requester confirmation, or an approved change window) related to {device} at {location}.\n\nClear next step: {owner} will re-check after the dependency clears and either resume in progress or resolve once verified. Do not treat pending as abandoned.",
            ],
            'new>in_progress' => [
                "Claiming and starting {case} (new → in progress).\n\n{owner} takes ownership and begins active diagnosis on {device} for \"{subject}\". First checks focus on power, link, and application ACK behaviour at {location}.",
            ],
            'in_progress>pending' => [
                "Moving {case} in progress → pending.\n\nWhy we are pausing active work: waiting on vendor parts, a controlled recycle window, or requester confirmation before touching {device} again. Floor impact and workaround (if any) are understood.\n\nNext check is owned by {owner}; case stays open until the dependency is cleared and service is re-validated.",
                "Status {case}: in progress → pending.\n\nActive troubleshooting on {device} is paused with a documented reason. Monitoring continues where relevant. We will not resolve until the pending item is closed out and the production wave looks clean.",
            ],
            'pending>in_progress' => [
                "Resuming {case} pending → in progress.\n\nThe blocking dependency is cleared (parts / window / confirmation). {owner} is back on {device} at {location} to finish \"{subject}\" and verify under load before any resolve.",
                "Re-opening active work on {case}.\n\nPending hold lifted. Continuing remediation on {device}; will post resolve notes only after a clean verification pass.",
            ],
            'in_progress>resolved' => [
                "Resolving {case} (in progress → resolved).\n\nWhy it is safe to resolve now: remediation on {device} is complete, verification under the current production conditions looks clean, and there is no outstanding dependency for \"{subject}\".\n\nClose-out: leave monitoring notes if a short watch window is still useful; requester-facing resolution text summarises the fix. Asset link retained for history.",
                "Case {case} ready to resolve.\n\nWork finished on {device} at {location}. Root cause addressed in the demonstration environment; no further ICT action required unless the symptom returns. {owner} confirms the queue item can leave active work.",
            ],
            'pending>resolved' => [
                "Resolving {case} (pending → resolved).\n\nThe pending dependency cleared and the final check on {device} succeeded. Nothing else remains for \"{subject}\" — resolving rather than sending the case back through idle assigned/new states.",
            ],
            'resolved>closed' => [
                "Closing {case} (resolved → closed).\n\nRequester confirmation / monitoring window complete for {device}. No reopen signals. Archiving the case after resolve verification in the demonstration environment.",
                "Final close for {case}.\n\nResolved work on \"{subject}\" stayed stable. Closing to keep the open queue honest; history and device link remain available for audit.",
            ],
        ];

        if (isset($map[$key])) {
            return $map[$key];
        }

        return [
            "Advancing {case} from {from} → {to}.\n\nReason: continuing the ICT workflow for \"{subject}\" on {device} at {location}. {owner} remains accountable for the next verification step. This note records why the stage changed so the Activity and Notes panels stay aligned.",
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
     * @return list<string>
     */
    public static function resolveNotes(): array
    {
        return [
            "Resolved {case}: root cause for \"{subject}\" confirmed and restored in the demonstration environment on {device} at {location}. Monitoring for recurrence this shift; no further ICT action unless the symptom returns.",
            "Resolved {case}: replaced or reseated the failing component on {device}. Service verified with the area contact. Asset history retained for audit.",
            "Resolved {case}: configuration corrected and validated for {device}. Closing path opened after acknowledgement in the demo thread.",
            "Resolved {case}: hot-spare cutover completed; PackLabel / device jobs ACK again on the replacement path. Original unit tagged for vendor collection.",
            "Resolved {case}: trusted location / preference restored. Workbooks and macros open cleanly on the linked desktop. Confirm once more if anything regresses after the next Office patch cycle.",
            "Resolved {case}: patch lead replaced and CRC growth stopped on the path to {device}. Short monitoring window complete with no further flaps.",
            "Resolved {case}: worker concurrency capped and memory pressure stayed under the warning line through the afternoon wave affecting this service.",
            "Resolved {case}: share path remapped after DNS suffix fix. Templates open normally from the lab desktop linked to this case.",
            "Resolved {case}: shared mailbox permissions rebuilt and Outlook profiles re-added. Mail flow restored in the simulation.",
            "Resolved {case}: scanner separation pad cleaned and thickness adjusted on {device}. Finance confirmed clean single-sheet feeds on flat stock.",
        ];
    }
}
