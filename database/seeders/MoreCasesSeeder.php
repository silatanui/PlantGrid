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

        $cases = [
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
        ];

        foreach ($cases as $index => $case) {
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
            $assignee = $assigneeIndex === null ? null : $assignees[$assigneeIndex % max(1, $assignees->count())] ?? null;
            $department = $departments->get($departmentCode) ?? $requester->department;
            $category = $categories->get($categoryName) ?? $categories->first();
            $asset = $assetTag ? $assets->get($assetTag) : null;
            $application = $applicationName ? $applications->get($applicationName) : null;
            $reported = now()->subHours((int) $reportedHoursAgo);
            $due = $dueDays === null ? null : now()->addDays((int) $dueDays);

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
                    'resolved_at' => in_array($status, ['resolved', 'closed'], true) ? $reported->copy()->addHours(6) : null,
                    'closed_at' => $status === 'closed' ? $reported->copy()->addHours(10) : null,
                    'resolution_notes' => $resolution,
                ]
            );

            if (! $ticket->wasRecentlyCreated) {
                continue;
            }

            TicketStatusHistory::query()->create([
                'support_ticket_id' => $ticket->id,
                'old_status' => null,
                'new_status' => 'new',
                'changed_by' => $requester->id,
                'notes' => 'Ticket opened.',
                'created_at' => $reported,
            ]);

            if ($status !== 'new') {
                TicketStatusHistory::query()->create([
                    'support_ticket_id' => $ticket->id,
                    'old_status' => 'new',
                    'new_status' => $status,
                    'changed_by' => $assignee?->id ?? $requester->id,
                    'notes' => 'Demonstration status.',
                    'created_at' => $reported->copy()->addHour(),
                ]);
            }

            if (in_array($status, ['in_progress', 'pending', 'assigned'], true) && $assignee) {
                TicketComment::query()->create([
                    'support_ticket_id' => $ticket->id,
                    'user_id' => $assignee->id,
                    'comment' => 'Internal note: investigation started in the demonstration environment.',
                    'visibility' => 'internal',
                    'created_at' => $reported->copy()->addHours(2),
                    'updated_at' => $reported->copy()->addHours(2),
                ]);
            }
        }
    }
}
