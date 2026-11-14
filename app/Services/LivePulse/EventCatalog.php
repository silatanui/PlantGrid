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
            'Checked switch counters and reseated the floor patch. Still waiting on a hot-spare if the PSU is dead.',
            'Synced with Packaging supervisor — they can hold one truck but not two. Priority stays high.',
            'Left a spare IEC lead at the packaging bench. Will confirm after the next compressor cycle.',
            'Escalating to ICT Manager if parts are not confirmed before the 14:00 shift change.',
            'Verified PackLabel service on PLT-APP01; jobs leave the queue but never ACK from the device.',
            'Captured a photo of the AP LED pattern for the admin-block roam complaint.',
            'Status: Tier-1 complete. Rolling two test units back to the previous firmware build for comparison.',
            'Power rail at the junction box is steady at 24V DC. Suspect application-layer freeze under high polling.',
            'Cleared two zombie jobs stuck in Rendering. Correlating with the application-server memory case.',
            'AD account unlocked; MFA shows one stale phone method. Offering temporary SMS for 48 hours.',
            'CRC growth stopped after reseating the LC patch in SRV-RM. Leaving the old lead in the evidence bag.',
            'Added plant.local search suffix on the QA desktop and remapped the templates share successfully.',
            'Memory dropped to 64% after worker-pool recycle, then climbed again — concurrency cap still needed.',
            'Drum life near end-of-life on this model family. Issuing a drum kit from ICT stores.',
            'Vendor voucher logging confirmed for 90 days. Two vouchers printed for Maintenance reception.',
            'Partnered with QA lead for certificate macro trusted-location restore after the Office update.',
            'Assigned spare handheld forced to 2.4 GHz for the tunnel walk-test; do not move the whole fleet yet.',
            'RMA logged against the demo vendor record. Hot-spare powered on first try.',
            'Internal: do not authorize full-line rollback until Engineering reviews the diagnostic dump.',
            'Shift handoff — open packaging cases have notes. Evening desk should take ownership if free.',
            'Confirmed backup contention on D: was cleared; share latency returned to baseline in the simulation.',
            'Service-account password rotated in the demo vault after confirming no scheduled job depends on it tonight.',
        ];
    }

    /**
     * @return list<string>
     */
    public static function visibleNotes(): array
    {
        return [
            'We are investigating on site. You will get another update within the hour.',
            'A technician is assigned. Please keep the affected device powered if safe to do so.',
            'Temporary workaround: use the spare unit from ICT cage bay 3 until the primary is repaired.',
            'Please confirm whether the issue still occurs after the lunch break restart.',
            'Guest vouchers will be left at Maintenance reception before 08:30.',
            'We recycled the PackLabel service briefly to clear memory pressure. Queued jobs should resume automatically.',
            'Network path restored after reseating the floor patch. Please retry the scale PC and reply if it drops again.',
            'Trusted location for QA templates has been restored. Please reopen the certificate workbook and confirm.',
            'A spare printer is online. Keep using the original job IDs until PackLabel cutover is confirmed.',
            'Access request is in progress. You will receive a confirmation when ERP read access and the share mapping are ready.',
            'We are monitoring the packaging switch uplink for 24 hours before closing this case.',
            'Please flatten curled invoices overnight so the scanner separation pad can feed single sheets.',
            'Desktop suffix fix is applied. Map \\\\PLT-FS01\\qa-templates again and tell us if it fails.',
            'Battery health shows end-of-life. A spare laptop can be issued from ICT stock on request.',
            'Thank you for the update — we can keep weighing manually for now, but need a firm ETA before afternoon dispatch.',
        ];
    }

    /**
     * @return list<string>
     */
    public static function progressNotes(): array
    {
        return [
            'Moving to in progress after confirming ownership and gathering the first diagnostic dump.',
            'Waiting on vendor parts / requester confirmation — parking in pending with a clear next check time.',
            'On-site checks completed; advancing status while we validate the fix under the next production wave.',
            'Handing into active work now that the spare is staged and PackLabel alias cutover is prepared.',
            'Pending a controlled recycle window approved by the floor lead before we touch the app pool.',
        ];
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
            'Root cause confirmed and restored in the demonstration environment. Monitoring for recurrence this shift.',
            'Replaced / reseated the failing component in the demo story. Service verified with the requester.',
            'Configuration corrected and validated. Closing path opened after requester acknowledgement in the demo thread.',
            'Hot-spare cutover completed and PackLabel jobs ACK again. Original unit tagged for vendor collection.',
            'Trusted location / GPO preference restored. Macros open on two QA PCs without the block prompt.',
            'Patch lead replaced and CRC growth stopped. Monitoring window complete with no further flaps.',
            'Worker concurrency capped and memory pressure stayed under the warning line through the afternoon wave.',
            'Share path remapped after DNS suffix fix. Certificate templates open normally from the lab desktop.',
            'Shared mailbox permissions rebuilt and Outlook profiles re-added. Vendor mail flow restored in the simulation.',
            'Scanner separation pad cleaned and thickness adjusted. Finance confirmed clean single-sheet feeds on flat stock.',
        ];
    }
}
