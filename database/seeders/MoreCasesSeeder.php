<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\Department;
use App\Models\SupportTicket;
use App\Models\SystemApplication;
use App\Models\TicketCategory;
use App\Models\TicketComment;
use App\Models\TicketStatusHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class MoreCasesSeeder extends Seeder
{
    public function run(): void
    {
        $departments = Department::query()->get()->keyBy('code');
        $categories = TicketCategory::query()->get()->keyBy('name');
        $assets = Asset::query()->get()->keyBy('asset_tag');
        $applications = SystemApplication::query()->get()->keyBy('name');

        $requesters = User::query()
            ->whereIn('email', [
                'grace.wanjiku@northridge.example',
                'peter.kamau@northridge.example',
                'jane.muthoni@northridge.example',
                'david.ochieng@northridge.example',
                'ann.nyambura@northridge.example',
                'joseph.kariuki@northridge.example',
                'esther.chepkoech@northridge.example',
                'naomi.wairimu@northridge.example',
                'paul.kipchumba@northridge.example',
                'ruth.njeri@northridge.example',
                'collins.barasa@northridge.example',
                'alex.maina@northridge.example',
                'beatrice.chelagat@northridge.example',
                'francis.kamau@northridge.example',
                'gladys.wanjiru@northridge.example',
                'catherine.atieno@northridge.example',
                'george.mutiso@northridge.example',
                'evans.kiprotich@northridge.example',
                'diana.jepchirchir@northridge.example',
                'ivy.cherop@northridge.example',
            ])
            ->get()
            ->values();

        $assignees = User::query()
            ->whereIn('email', [
                'faith.cheruiyot@northridge.example',
                'brian.otieno@northridge.example',
                'mercy.chebet@northridge.example',
                'kevin.njoroge@northridge.example',
                'lilian.auma@northridge.example',
                'daniel.mwangi@northridge.example',
            ])
            ->get()
            ->values();

        if ($requesters->isEmpty() || $departments->isEmpty() || $categories->isEmpty()) {
            return;
        }

        foreach ($this->cases() as $index => $case) {
            [
                $number,
                $departmentCode,
                $categoryName,
                $assetTag,
                $applicationName,
                $subject,
                $description,
                $priority,
                $status,
                $assigneeIndex,
                $reportedHoursAgo,
                $dueDays,
            ] = array_pad($case, 12, null);

            $resolution = $case[12] ?? null;
            $requester = $requesters[$index % $requesters->count()];
            $assignee = $assigneeIndex === null
                ? null
                : ($assignees[$assigneeIndex % max(1, $assignees->count())] ?? null);
            $department = $departments->get($departmentCode) ?? $requester->department;
            $category = $categories->get($categoryName) ?? $categories->first();
            $asset = $assetTag ? $assets->get($assetTag) : null;
            $application = $applicationName ? $applications->get($applicationName) : null;
            $reported = now()->subHours((int) $reportedHoursAgo);
            $due = $dueDays === null ? null : now()->addDays((int) $dueDays);
            $resolvedAt = in_array($status, ['resolved', 'closed'], true)
                ? $reported->copy()->addHours($this->resolveHours($priority, $status))
                : null;
            $closedAt = $status === 'closed'
                ? ($resolvedAt?->copy()->addHours(3) ?? $reported->copy()->addHours(10))
                : null;

            $ticket = SupportTicket::query()->updateOrCreate(
                ['ticket_number' => $number],
                [
                    'requester_id' => $requester->id,
                    'department_id' => $department->id,
                    'ticket_category_id' => $category->id,
                    'asset_id' => $asset?->id,
                    'system_application_id' => $application?->id,
                    'subject' => $subject,
                    'description' => $description.' This is a fictional demonstration case for PlantGrid.',
                    'priority' => $priority,
                    'status' => $status,
                    'assigned_to' => $assignee?->id,
                    'reported_at' => $reported,
                    'due_at' => $due,
                    'resolved_at' => $resolvedAt,
                    'closed_at' => $closedAt,
                    'resolution_notes' => $resolution,
                ]
            );

            if (! $ticket->wasRecentlyCreated && $ticket->comments()->exists()) {
                continue;
            }

            $this->seedTrail($ticket, $requester, $assignee, $status, $reported, $resolvedAt);
        }
    }

    /**
     * @return list<array<int, mixed>>
     */
    private function cases(): array
    {
        return [
            // Existing batch (kept stable for demo continuity)
            ['PG-2026-0153', 'PRD', 'Hardware', 'PG-PC-0101', null, 'Packaging PC fails to start after power dip', 'The packaging terminal stays on a black screen after the morning power dip. Operators cannot print dispatch notes.', 'high', 'new', null, 2, 1],
            ['PG-2026-0154', 'WHS', 'Network connectivity', null, null, 'Handheld scanner cannot join warehouse Wi-Fi', 'Receiving staff report the handheld scanner keeps dropping from the packaging wireless access point.', 'high', 'assigned', 0, 5, 2],
            ['PG-2026-0155', 'FIN', 'ERP and business application', null, 'Northridge ERP', 'ERP login timeout for finance clerks', 'Two finance clerks see a login timeout when opening Northridge ERP from the admin block.', 'medium', 'in_progress', 1, 8, 3],
            ['PG-2026-0156', 'QA', 'Printer or scanner', 'PG-PRT-0401', null, 'QA checklist printer prints faint pages', 'Checklist sheets from the QA lab printer are too faint to sign. Toner was replaced last week in the demonstration record.', 'medium', 'pending', 2, 12, 4],
            ['PG-2026-0157', 'ICT', 'Server and infrastructure', 'PG-SRV-0003', null, 'Application server memory warning on PLT-APP01', 'Monitoring shows sustained memory pressure on the application server during the morning batch window.', 'critical', 'in_progress', 3, 4, null],
            ['PG-2026-0158', 'PRD', 'Production technology', 'PG-LBL-0142', 'PackLabel', 'PackLabel job queue stuck on line 2', 'Label jobs remain queued in PackLabel and do not reach the packaging printer.', 'high', 'assigned', 4, 6, 1],
            ['PG-2026-0159', 'MNT', 'User account and access', null, null, 'Maintenance shared mailbox missing after password reset', 'The maintenance team mailbox stopped accepting mail after a password reset for the shared account.', 'medium', 'new', null, 3, 2],
            ['PG-2026-0160', 'WHS', 'Software', 'PG-PC-0104', null, 'Warehouse desktop cannot open inventory spreadsheet', 'Excel reports a file lock when opening the finished-goods inventory workbook on the warehouse PC.', 'low', 'pending', 0, 10, null],
            ['PG-2026-0161', 'QA', 'Network connectivity', 'PG-PC-0103', null, 'QA lab desktop cannot reach the file share', 'The QA laboratory desktop cannot map the shared drive used for certificate templates.', 'high', 'in_progress', 1, 7, 3],
            ['PG-2026-0162', 'FIN', 'Hardware', 'PG-SCN-0201', null, 'Accounts payable scanner feeds double pages', 'The finance scanner pulls two sheets at once and duplicates invoice images in the demonstration inbox.', 'medium', 'assigned', 2, 9, 1],
            ['PG-2026-0163', 'PRD', 'Production technology', 'PG-SCL-0301', 'FloorWeigh', 'FloorWeigh readings freeze during intake', 'Intake operators report that FloorWeigh stops updating weight readings after about twenty minutes.', 'critical', 'new', null, 1, 1],
            ['PG-2026-0164', 'ICT', 'Security', null, null, 'Suspicious login alerts for a service account', 'Security monitoring flagged repeated failed logins against a demonstration service account overnight.', 'high', 'in_progress', 5, 14, null],
            ['PG-2026-0165', 'MNT', 'Other ICT request', null, null, 'Request for temporary guest Wi-Fi for vendor visit', 'A maintenance vendor needs guest wireless access for a planned calibration visit next week.', 'low', 'assigned', 3, 15, 5],
            ['PG-2026-0166', 'WHS', 'Printer or scanner', null, null, 'Dispatch desk label rolls need restocking', 'The dispatch desk has only one spare label roll left for outbound cases.', 'low', 'pending', 4, 11, null],
            ['PG-2026-0167', 'PRD', 'Network connectivity', 'PG-NET-0004', null, 'Packaging floor switch port flap reported', 'Operators notice brief network drops on packaging terminals connected through PLT-SW-PACK.', 'critical', 'assigned', 0, 4, 2],
            ['PG-2026-0168', 'QA', 'Software', null, null, 'Certificate template macros blocked on QA PCs', 'Macro prompts appear when opening the certificate workbook after the latest Office update in the demo environment.', 'medium', 'resolved', 1, 18, null, 'Trusted location was re-applied for the QA templates folder. Macros open without the block prompt.'],
            ['PG-2026-0169', 'FIN', 'ERP and business application', null, 'Northridge ERP', 'Purchase order export fails for supplier pack', 'Finance cannot export the supplier purchase-order pack from Northridge ERP for the weekly close.', 'high', 'closed', 2, 20, 4, 'Export service was restarted on PLT-APP01 and the pack completed successfully in the simulation.'],
            ['PG-2026-0170', 'ICT', 'Server and infrastructure', 'PG-SRV-0002', null, 'File share latency for admin block users', 'Admin block users report slow opens on department file shares hosted on PLT-FS01.', 'medium', 'resolved', 3, 16, null, 'A stuck backup job was cleared and share response times returned to the demonstration baseline.'],
            ['PG-2026-0171', 'PRD', 'Hardware', 'PG-PC-0090', null, 'Shift supervisor laptop battery drains quickly', 'The production supervisor laptop drops to 20 percent within an hour even when idle on the desk.', 'low', 'closed', 4, 22, 5, 'Battery health report showed end-of-life. A spare laptop was issued from ICT stock.'],
            ['PG-2026-0172', 'WHS', 'User account and access', null, null, 'New warehouse starter needs system access', 'A new warehouse clerk needs ERP read access and the finished-goods share mapped on day one.', 'medium', 'in_progress', 5, 6, 3],

            // Fresh queue — newly opened, not yet owned
            ['PG-2026-0173', 'PRD', 'Hardware', 'PG-PC-0101', null, 'Packaging laptop keyboard keys stick on shift handover', 'Several keys on the packaging supervisor laptop stick during afternoon handover, slowing dispatch note entry.', 'medium', 'new', null, 1, 1],
            ['PG-2026-0174', 'WHS', 'Printer or scanner', null, null, 'Receiving desk printer offline after overnight reboot', 'The receiving desk printer shows offline in Windows after the overnight power cycle.', 'high', 'new', null, 2, 1],
            ['PG-2026-0175', 'FIN', 'Software', 'PG-PC-0104', null, 'Finance spreadsheet formulas show #REF after copy', 'A clerk reports broken references in the weekly cashflow workbook after copying sheets between months.', 'low', 'new', null, 4, 3],
            ['PG-2026-0176', 'QA', 'Hardware', 'PG-PC-0103', null, 'QA lab monitor flickers during certificate review', 'The laboratory monitor flickers when switching between the certificate template and photo evidence folder.', 'medium', 'new', null, 3, 2],
            ['PG-2026-0177', 'MNT', 'Network connectivity', null, null, 'Maintenance workshop cannot reach vendor portal', 'Technicians in the workshop cannot open the vendor parts portal through the plant proxy.', 'medium', 'new', null, 5, 2],
            ['PG-2026-0178', 'PRD', 'Production technology', 'PG-SCN-0201', 'PackLabel', 'Handheld scanner beeps but PackLabel ignores barcode', 'Line 1 operators scan finished goods successfully by tone, but PackLabel does not register the code.', 'high', 'new', null, 1, 1],
            ['PG-2026-0179', 'ICT', 'Other ICT request', null, null, 'Request for second monitor in ICT office', 'A technician needs a second monitor for dual-pane case and monitoring work during peak hours.', 'low', 'new', null, 8, 7],
            ['PG-2026-0180', 'WHS', 'User account and access', null, null, 'Agency picker account locked after password attempts', 'An agency picker account locked overnight after repeated failed password attempts at the dispatch desk.', 'high', 'new', null, 2, 1],
            ['PG-2026-0181', 'FIN', 'Printer or scanner', null, null, 'Accounts payable needs duplex enabled on shared printer', 'Invoice packs are printing single-sided only; finance asked for duplex to cut paper use.', 'low', 'new', null, 10, 5],
            ['PG-2026-0182', 'QA', 'ERP and business application', null, 'Northridge ERP', 'QA cannot open batch release screen in ERP', 'The batch release form in Northridge ERP opens blank for QA supervisors after the morning refresh.', 'critical', 'new', null, 1, 1],
            ['PG-2026-0183', 'PRD', 'Server and infrastructure', null, null, 'Shift report share folder missing for packaging team', 'The packaging shift report folder disappeared from the mapped drive overnight.', 'high', 'new', null, 3, 1],
            ['PG-2026-0184', 'MNT', 'Hardware', null, null, 'Workshop tablet will not charge on dock', 'The maintenance workshop tablet stays at 4 percent even when seated on the charging dock.', 'medium', 'new', null, 6, 3],

            // Worked cases — assigned / in progress / pending
            ['PG-2026-0185', 'PRD', 'Network connectivity', 'PG-NET-0003', null, 'Processing hall intermittent latency on intake PCs', 'Intake PCs briefly freeze when saving FloorWeigh sessions. Packet loss was seen toward PLT-SW-PROD.', 'high', 'in_progress', 0, 28, 1],
            ['PG-2026-0186', 'WHS', 'Hardware', 'PG-PC-0104', null, 'Warehouse desktop fans run loudly under light load', 'The warehouse desk PC fans ramp constantly even when only the inventory workbook is open.', 'low', 'assigned', 1, 36, 4],
            ['PG-2026-0187', 'FIN', 'ERP and business application', null, 'Northridge ERP', 'Supplier remittance advice email fails from ERP', 'Finance remittance advice emails stay in the ERP outbox and never reach suppliers.', 'high', 'in_progress', 2, 40, 2],
            ['PG-2026-0188', 'QA', 'Software', 'PG-PC-0103', null, 'Certificate PDF export truncates footer fields', 'Exported certificate PDFs cut off the approval footer when printed for the retention file.', 'medium', 'pending', 3, 48, 3],
            ['PG-2026-0189', 'ICT', 'Server and infrastructure', 'PG-SRV-0004', null, 'Database backup window overruns into morning batch', 'PLT-DB01 backup still runs past 06:00 and delays the demonstration morning batch jobs.', 'critical', 'in_progress', 4, 30, null],
            ['PG-2026-0190', 'PRD', 'Production technology', 'PG-SCL-0301', 'FloorWeigh', 'FloorWeigh calibration prompt appears mid-shift', 'Operators get an unexpected calibration prompt mid-shift even though the scale was verified this morning.', 'high', 'assigned', 5, 22, 1],
            ['PG-2026-0191', 'MNT', 'User account and access', null, null, 'Contractor VPN profile expires during plant visit', 'A maintenance contractor VPN profile expired while on site, blocking access to documentation shares.', 'medium', 'in_progress', 0, 26, 2],
            ['PG-2026-0192', 'WHS', 'Network connectivity', 'PG-NET-0006', null, 'Packaging Wi-Fi weak near dispatch roller doors', 'Handheld scanners lose association near the dispatch roller doors during outbound loading.', 'high', 'pending', 1, 52, 2],
            ['PG-2026-0193', 'FIN', 'Hardware', null, null, 'Finance docking station does not detect USB headset', 'A finance clerk docking station no longer enumerates the USB headset used for vendor calls.', 'low', 'assigned', 2, 44, 5],
            ['PG-2026-0194', 'QA', 'Printer or scanner', 'PG-PRT-0401', null, 'QA printer tray 2 ignored after paper refill', 'Tray 2 was refilled but the driver continues to pull from tray 1 only.', 'medium', 'in_progress', 3, 34, 2],
            ['PG-2026-0195', 'PRD', 'Security', null, null, 'USB write block preventing line checklist updates', 'Packaging supervisors cannot copy checklist updates to an approved encrypted USB stick.', 'medium', 'pending', 4, 60, 4],
            ['PG-2026-0196', 'ICT', 'Software', 'PG-PC-0102', null, 'Remote support tool fails to start after update', 'The ICT remote support client exits immediately after the latest demonstration update.', 'high', 'in_progress', 5, 18, 1],
            ['PG-2026-0197', 'WHS', 'ERP and business application', null, 'Northridge ERP', 'Dispatch confirmation screen times out at shift end', 'Warehouse dispatch confirmation screens time out when many trucks are closed out together.', 'high', 'assigned', 0, 24, 1],
            ['PG-2026-0198', 'MNT', 'Other ICT request', null, null, 'Need temporary projector for toolbox talk', 'Maintenance requested a projector and HDMI lead for a contractor toolbox talk tomorrow morning.', 'low', 'pending', 1, 16, 1],
            ['PG-2026-0199', 'PRD', 'Hardware', 'PG-LBL-0142', null, 'Label printer cutter leaves ragged edges', 'Even when powered, the packaging label cutter leaves ragged edges that jam applicator rollers.', 'medium', 'in_progress', 2, 42, 2],
            ['PG-2026-0200', 'QA', 'Network connectivity', null, null, 'QA tablets drop VPN when moving between lab rooms', 'QA tablets lose VPN when carried between the wet lab and document room.', 'medium', 'assigned', 3, 38, 3],
            ['PG-2026-0201', 'FIN', 'User account and access', null, null, 'Accounts receivable needs shared mailbox add-in', 'AR staff need the shared mailbox visible in Outlook after a role move from AP.', 'low', 'in_progress', 4, 50, 4],
            ['PG-2026-0202', 'ICT', 'Server and infrastructure', 'PG-SRV-0001', null, 'Directory sync delay for new starter accounts', 'New starter accounts appear in AD but take over an hour to sync to the file-share ACL groups.', 'high', 'pending', 5, 55, null],

            // Solved — resolved / closed history across recent weeks
            ['PG-2026-0203', 'PRD', 'Hardware', 'PG-PC-0101', null, 'Packaging laptop overheating on stand', 'The packaging laptop shut down from heat while charging on the metal stand.', 'medium', 'resolved', 0, 96, null, 'Thermal paste was re-applied and the stand was swapped for a ventilated cradle. Temperatures stayed within the demo baseline.'],
            ['PG-2026-0204', 'WHS', 'Network connectivity', null, null, 'Warehouse cannot print to packaging label queue', 'Warehouse clerks could not reach the packaging label print queue across VLANs.', 'high', 'closed', 1, 120, null, 'Firewall rule for print services between warehouse and packaging was corrected in the demonstration policy set.'],
            ['PG-2026-0205', 'FIN', 'ERP and business application', null, 'Northridge ERP', 'Month-end journal import rejected CSV encoding', 'Finance journal import failed on accented supplier names due to CSV encoding.', 'medium', 'resolved', 2, 144, null, 'Import template was switched to UTF-8 and a sample journal posted successfully.'],
            ['PG-2026-0206', 'QA', 'Printer or scanner', 'PG-PRT-0401', null, 'QA printer showed toner empty with new cartridge', 'The lab printer insisted toner was empty immediately after a cartridge swap.', 'low', 'closed', 3, 168, null, 'Drum unit counters were reset and the new cartridge was recognised.'],
            ['PG-2026-0207', 'ICT', 'Server and infrastructure', 'PG-SRV-0002', null, 'File server shadow copies unavailable for one share', 'Previous versions were missing on the QA templates share after a storage rebalance.', 'high', 'resolved', 4, 192, null, 'Shadow copy schedule was restored for the volume and sample restores succeeded.'],
            ['PG-2026-0208', 'PRD', 'Production technology', 'PG-SCL-0301', 'FloorWeigh', 'FloorWeigh units displayed in pounds after restart', 'After a power dip the intake scale UI showed pounds instead of kilograms.', 'critical', 'closed', 5, 216, null, 'Locale profile was corrected and the unit preference locked in the application config.'],
            ['PG-2026-0209', 'MNT', 'User account and access', null, null, 'Maintenance planner lost calendar permissions', 'The maintenance planner could not edit the shared workshop calendar after a mailbox move.', 'medium', 'resolved', 0, 240, null, 'Calendar folder permissions were reapplied and ownership verified with a test booking.'],
            ['PG-2026-0210', 'WHS', 'Software', 'PG-PC-0104', null, 'Inventory workbook corruption after autosave', 'Autosave left the finished-goods workbook unreadable until repaired.', 'high', 'closed', 1, 264, null, 'File was recovered from yesterday’s shadow copy and autosave path was redirected to a stable folder.'],
            ['PG-2026-0211', 'FIN', 'Hardware', null, null, 'Finance laptop trackpad intermittent clicks', 'Trackpad clicks registered twice, creating duplicate ERP rows.', 'low', 'resolved', 2, 288, null, 'Precision touchpad driver was rolled back and the external mouse became the default input.'],
            ['PG-2026-0212', 'QA', 'Network connectivity', 'PG-PC-0103', null, 'QA desktop DNS pointed to retired server', 'Certificate lookups failed because DNS still referenced a decommissioned demo resolver.', 'high', 'closed', 3, 312, null, 'DHCP scope options were updated and the desktop renewed to the current resolvers.'],
            ['PG-2026-0213', 'PRD', 'Printer or scanner', 'PG-SCN-0201', null, 'Handheld scanner firmware mismatch after spare swap', 'A spare scanner refused plant barcodes until firmware matched the line profile.', 'medium', 'resolved', 4, 336, null, 'Firmware was aligned to the packaging profile and a scan test pack passed.'],
            ['PG-2026-0214', 'ICT', 'Security', null, null, 'Phishing simulation mailbox quarantine overflow', 'Demo quarantine filled after a phishing simulation, delaying legitimate mail release.', 'medium', 'closed', 5, 360, null, 'Quarantine retention was shortened and simulation traffic was tagged to a separate rule.'],
            ['PG-2026-0215', 'WHS', 'Other ICT request', null, null, 'Request for spare barcode sleeves for cold store', 'Warehouse asked for protective sleeves so handheld screens survive cold-store condensation.', 'low', 'resolved', 0, 384, null, 'Sleeves were issued from ICT stores and logged against the warehouse cost centre.'],
            ['PG-2026-0216', 'MNT', 'Hardware', null, null, 'Workshop PC BIOS clock drifted after battery change', 'After CMOS battery replacement the workshop PC clock drifted and broke certificate trusts.', 'medium', 'closed', 1, 408, null, 'Time service was forced to PLT-AD01 and the CMOS battery part was recorded in the asset note.'],
            ['PG-2026-0217', 'FIN', 'Software', null, null, 'Outlook search index rebuild for finance mailbox', 'Finance mailbox search returned incomplete results after a profile rebuild.', 'low', 'resolved', 2, 432, null, 'Search index was rebuilt overnight and sample queries matched expected mail.'],
            ['PG-2026-0218', 'QA', 'ERP and business application', null, 'Northridge ERP', 'QA hold codes missing from ERP dropdown', 'Hold-reason codes were blank in the QA disposition screen.', 'high', 'closed', 3, 456, null, 'Reference data cache was refreshed and hold codes reappeared for the QA role.'],
            ['PG-2026-0219', 'PRD', 'Network connectivity', 'PG-NET-0004', null, 'Packaging uplink utilisation alarm cleared after tuning', 'Uplink alarms fired during label bursts even though throughput stayed within design.', 'medium', 'resolved', 4, 480, null, 'Alarm threshold was retuned for bursty label traffic and a quiet period confirmed stability.'],
            ['PG-2026-0220', 'ICT', 'Server and infrastructure', 'PG-SRV-0003', null, 'Application pool recycle storm on PLT-APP01', 'Repeated application pool recycles caused intermittent ERP slowdowns.', 'critical', 'closed', 5, 504, null, 'Memory limit and recycle schedule were adjusted; monitoring stayed green for two batch windows.'],
            ['PG-2026-0221', 'WHS', 'User account and access', null, null, 'Forklift tablet kiosk account auto-logoff too aggressive', 'Kiosk sessions logged off every ten minutes, interrupting put-away scans.', 'medium', 'resolved', 0, 528, null, 'Idle timeout was extended for the warehouse kiosk GPO and verified on two tablets.'],
            ['PG-2026-0222', 'PRD', 'Production technology', 'PG-LBL-0142', 'PackLabel', 'PackLabel reprint dialog crashed on long job lists', 'Reprint dialog crashed when operators scrolled a long historical job list.', 'high', 'closed', 1, 552, null, 'Client cache limit was raised and a patched PackLabel build was deployed to packaging PCs.'],
            ['PG-2026-0223', 'FIN', 'Printer or scanner', null, null, 'Colour prints from finance were billed to ICT queue', 'Colour print jobs from finance were landing on the ICT office queue after a driver remap.', 'low', 'resolved', 2, 576, null, 'Default queue mapping was corrected via GPO and a colour test page printed to the finance device.'],
            ['PG-2026-0224', 'QA', 'Hardware', 'PG-PC-0103', null, 'QA desktop RAM upgrade for photo evidence folders', 'Opening large photo evidence folders froze the QA desktop during audits.', 'medium', 'closed', 3, 600, null, 'RAM was upgraded to 16 GB and folder open times returned to an acceptable demo baseline.'],
            ['PG-2026-0225', 'MNT', 'Network connectivity', null, null, 'Guest SSID broadcast in workshop after event', 'Guest SSID remained visible in the workshop after a vendor event ended.', 'low', 'resolved', 4, 624, null, 'Guest SSID schedule was disabled outside event windows and verified with a phone scan.'],
            ['PG-2026-0226', 'ICT', 'Other ICT request', null, null, 'Archive older ICT meeting recordings to file share', 'Teams meeting recordings filled the ICT OneDrive quota used for weekly stand-ups.', 'low', 'closed', 5, 648, null, 'Recordings older than 90 days were moved to the ICT archive share and quota pressure cleared.'],
        ];
    }

    private function resolveHours(string $priority, string $status): int
    {
        $base = match ($priority) {
            'critical' => 4,
            'high' => 8,
            'medium' => 18,
            default => 36,
        };

        return $status === 'closed' ? $base + 4 : $base;
    }

    private function seedTrail(
        SupportTicket $ticket,
        User $requester,
        ?User $assignee,
        string $status,
        Carbon $reported,
        ?Carbon $resolvedAt,
    ): void {
        TicketStatusHistory::query()->where('support_ticket_id', $ticket->id)->delete();
        TicketComment::query()->where('support_ticket_id', $ticket->id)->delete();

        TicketStatusHistory::query()->create([
            'support_ticket_id' => $ticket->id,
            'old_status' => null,
            'new_status' => 'new',
            'changed_by' => $requester->id,
            'notes' => 'Ticket opened.',
            'created_at' => $reported,
        ]);

        $actor = $assignee ?? $requester;
        $cursor = $reported->copy()->addMinutes(25);

        if ($status === 'new') {
            TicketComment::query()->create([
                'support_ticket_id' => $ticket->id,
                'user_id' => $requester->id,
                'comment' => 'Requester note: still blocking work on this shift. Please advise when someone can look.',
                'visibility' => 'requester_visible',
                'created_at' => $cursor,
                'updated_at' => $cursor,
            ]);

            return;
        }

        $this->history($ticket, 'new', 'assigned', $actor, 'Assigned for demonstration follow-up.', $cursor);
        $cursor = $cursor->copy()->addHours(1);

        if (in_array($status, ['in_progress', 'pending', 'resolved', 'closed'], true)) {
            $this->history($ticket, 'assigned', 'in_progress', $actor, 'Investigation started.', $cursor);
            $cursor = $cursor->copy()->addHours(2);

            if ($assignee) {
                TicketComment::query()->create([
                    'support_ticket_id' => $ticket->id,
                    'user_id' => $assignee->id,
                    'comment' => 'Internal note: reproduced in the demonstration environment and gathering evidence.',
                    'visibility' => 'internal',
                    'created_at' => $cursor,
                    'updated_at' => $cursor,
                ]);
                $cursor = $cursor->copy()->addHours(1);
            }

            TicketComment::query()->create([
                'support_ticket_id' => $ticket->id,
                'user_id' => $requester->id,
                'comment' => 'Thanks — we can provide access to the area whenever ICT is on site.',
                'visibility' => 'requester_visible',
                'created_at' => $cursor,
                'updated_at' => $cursor,
            ]);
            $cursor = $cursor->copy()->addHours(2);
        }

        if ($status === 'assigned') {
            if ($assignee) {
                TicketComment::query()->create([
                    'support_ticket_id' => $ticket->id,
                    'user_id' => $assignee->id,
                    'comment' => 'Internal note: queued behind a higher-priority floor call. Will pick up next.',
                    'visibility' => 'internal',
                    'created_at' => $cursor,
                    'updated_at' => $cursor,
                ]);
            }

            return;
        }

        if ($status === 'pending') {
            $this->history($ticket, 'in_progress', 'pending', $actor, 'Waiting on requester or vendor input.', $cursor);
            if ($assignee) {
                TicketComment::query()->create([
                    'support_ticket_id' => $ticket->id,
                    'user_id' => $assignee->id,
                    'comment' => 'We need a short confirmation from the area owner before the next change window.',
                    'visibility' => 'requester_visible',
                    'created_at' => $cursor->copy()->addMinutes(20),
                    'updated_at' => $cursor->copy()->addMinutes(20),
                ]);
            }

            return;
        }

        if ($status === 'in_progress') {
            if ($assignee) {
                TicketComment::query()->create([
                    'support_ticket_id' => $ticket->id,
                    'user_id' => $assignee->id,
                    'comment' => 'Update: fix is in progress. Will post again after the next check.',
                    'visibility' => 'requester_visible',
                    'created_at' => $cursor,
                    'updated_at' => $cursor,
                ]);
            }

            return;
        }

        if (in_array($status, ['resolved', 'closed'], true)) {
            $resolveAt = $resolvedAt?->copy() ?? $cursor->copy()->addHours(6);
            $this->history($ticket, 'in_progress', 'resolved', $actor, 'Marked resolved in the demonstration.', $resolveAt);

            if ($assignee) {
                TicketComment::query()->create([
                    'support_ticket_id' => $ticket->id,
                    'user_id' => $assignee->id,
                    'comment' => 'Resolution posted. Please confirm if the issue returns on the next shift.',
                    'visibility' => 'requester_visible',
                    'created_at' => $resolveAt->copy()->addMinutes(10),
                    'updated_at' => $resolveAt->copy()->addMinutes(10),
                ]);
            }

            if ($status === 'closed') {
                $closeAt = $resolveAt->copy()->addHours(3);
                $this->history($ticket, 'resolved', 'closed', $actor, 'Closed after confirmation window.', $closeAt);
                TicketComment::query()->create([
                    'support_ticket_id' => $ticket->id,
                    'user_id' => $requester->id,
                    'comment' => 'Confirmed working on our side. Thanks ICT.',
                    'visibility' => 'requester_visible',
                    'created_at' => $closeAt->copy()->subHour(),
                    'updated_at' => $closeAt->copy()->subHour(),
                ]);
            }
        }
    }

    private function history(
        SupportTicket $ticket,
        string $from,
        string $to,
        User $actor,
        string $notes,
        Carbon $when,
    ): void {
        TicketStatusHistory::query()->create([
            'support_ticket_id' => $ticket->id,
            'old_status' => $from,
            'new_status' => $to,
            'changed_by' => $actor->id,
            'notes' => $notes,
            'created_at' => $when,
        ]);
    }
}
