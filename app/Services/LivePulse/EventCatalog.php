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
                'description' => "The Zebra on Packaging Hall A lost power after the morning compressor start. Display stays off; neighbouring PC is fine.\nImpact: outbound cartons cannot be labelled for the 11:00 truck.",
                'priority' => 'high',
                'category' => 'Hardware',
            ],
            [
                'subject' => 'Warehouse VPN token rejected',
                'description' => "Remote access MFA for the warehouse supervisor is rejected after the overnight token rotate in the demo vault.\nImpact: cannot review finished-goods stock from home.",
                'priority' => 'medium',
                'category' => 'Network connectivity',
            ],
            [
                'subject' => 'FloorWeigh PC dropped from PLT-SW-PROD',
                'description' => "Intake scale PC lost L2; MQTT heartbeats from the scale head stopped for ~3 minutes then recovered.\nNeed a physical check on Gi1/0/12 and the floor patch lead.",
                'priority' => 'high',
                'category' => 'Network connectivity',
            ],
            [
                'subject' => 'Guest SSID voucher for vendor visit',
                'description' => "Maintenance expects two 48-hour Guest-Plant vouchers for a calibration vendor tomorrow morning.\nDo not bridge to VLAN 20.",
                'priority' => 'low',
                'category' => 'Software',
            ],
            [
                'subject' => 'QA lab desktop cannot open macro workbook',
                'description' => "Certificate macros fail on the QA lab PC after the last Office patch in the demo image.\nLab techs are using a USB export workaround.",
                'priority' => 'medium',
                'category' => 'Software',
            ],
            [
                'subject' => 'Server room CRAC setpoint drift alarm',
                'description' => "Demo CRAC controller reported a 2°C drift above setpoint for 12 minutes. Temperature recovered; please confirm sensor cable seating.",
                'priority' => 'medium',
                'category' => 'Server and infrastructure',
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
                'description' => "Entrance NVR reported 85% disk use. Retention still meets the demo policy but needs a purge or archive pass this week.",
                'priority' => 'low',
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
            "Checked switch counters and reseated the floor patch. Still waiting on a hot-spare if the PSU is dead.",
            "Synced with Packaging supervisor — they can hold one truck but not two. Priority stays high.",
            "Left a spare IEC lead at the packaging bench. Will confirm after the next compressor cycle.",
            "Escalating to ICT Manager if parts are not confirmed before 14:00 shift change.",
            "Verified PackLabel service on PLT-APP01; jobs leave the queue but never ACK from the device.",
            "Captured a photo of the AP LED pattern for the admin-block roam complaint.",
        ];
    }

    /**
     * @return list<string>
     */
    public static function visibleNotes(): array
    {
        return [
            "We are investigating on site. You will get another update within the hour.",
            "A technician is assigned. Please keep the affected device powered if safe to do so.",
            "Temporary workaround: use the spare unit from ICT cage bay 3 until the primary is repaired.",
            "Please confirm whether the issue still occurs after the lunch break restart.",
            "Guest vouchers will be left at Maintenance reception before 08:30.",
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
                'body' => "Quick check — is the labeler still dark after the compressor start, or did it recover?",
            ],
            [
                'subject' => 'VPN follow-up',
                'body' => "Please try the new demo token once and reply with the exact MFA error text if it fails again.",
            ],
            [
                'subject' => 'Parts for PG-LBL',
                'body' => "Can you bring the hot-spare ZT411 from cage bay 3 before 13:30? Packaging needs a firm ETA.",
            ],
            [
                'subject' => 'Guest vouchers',
                'body' => "Two Guest-Plant vouchers for tomorrow's vendor — confirm voucher logging is still on 90-day retention.",
            ],
            [
                'subject' => 'FloorWeigh link',
                'body' => "Gi1/0/12 looked clean after reseat. Watch for another flap during the afternoon intake rush.",
            ],
            [
                'subject' => 'Shift handoff',
                'body' => "Leaving the open packaging cases with notes. Please take ownership if you are on the evening desk.",
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
            'Configuration corrected and validated. Closing after requester acknowledgement in the demo thread.',
        ];
    }
}
