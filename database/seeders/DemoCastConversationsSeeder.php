<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\Location;
use App\Models\SupportTicket;
use App\Models\TicketComment;
use App\Models\TicketStatusHistory;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Database\Seeder;

class DemoCastConversationsSeeder extends Seeder
{
    public function run(): void
    {
        $faith = User::query()->where('email', 'faith.cheruiyot@northridge.example')->first();
        $peter = User::query()->where('email', 'peter.kamau@northridge.example')->first();
        $grace = User::query()->where('email', 'grace.wanjiku@northridge.example')->first();
        $helen = User::query()->where('email', 'helen.achieng@northridge.example')->first();

        if (! $faith || ! $peter || ! $grace || ! $helen) {
            return;
        }

        $locations = Location::query()->get()->keyBy('code');
        $assets = Asset::query()->get()->keyBy('asset_tag');
        $notify = app(NotificationService::class);

        $mention = function (User $person): string {
            return '@['.$person->name.'](people:'.$person->id.')';
        };

        $locationLink = function (string $code) use ($locations): string {
            $location = $locations->get($code);
            if (! $location) {
                return $code;
            }

            return '@['.$location->name.'](location:'.$location->id.')';
        };

        $scripts = $this->scripts($mention, $locationLink, $faith, $peter, $grace, $helen);

        foreach ($scripts as $script) {
            $ticket = SupportTicket::query()->where('ticket_number', $script['ticket'])->first();
            if (! $ticket) {
                continue;
            }

            if (! empty($script['asset']) && isset($assets[$script['asset']])) {
                $ticket->asset_id = $assets[$script['asset']]->id;
            }

            if (! empty($script['requester_id'])) {
                $ticket->requester_id = $script['requester_id'];
            }

            if (array_key_exists('assigned_to', $script)) {
                $ticket->assigned_to = $script['assigned_to'];
            }

            if (! empty($script['status'])) {
                $ticket->status = $script['status'];
            }

            if (! empty($script['priority'])) {
                $ticket->priority = $script['priority'];
            }

            $ticket->save();

            $notify->syncPartners($ticket, $script['partners'] ?? [], 'demo_cast');

            if (TicketComment::query()
                ->where('support_ticket_id', $ticket->id)
                ->where('comment', 'like', $script['marker'].'%')
                ->exists()) {
                continue;
            }

            // Remove thin generic stubs so the cast conversation is what recruiters read.
            TicketComment::query()
                ->where('support_ticket_id', $ticket->id)
                ->where(function ($query) {
                    $query->where('comment', 'like', 'Internal note: investigation started%')
                        ->orWhere('comment', 'like', 'Update: fix is in progress%')
                        ->orWhere('comment', 'like', 'Requester note: still blocking%')
                        ->orWhere('comment', 'like', 'We need a short confirmation%')
                        ->orWhere('comment', 'like', 'Internal note: queued behind%')
                        ->orWhere('comment', 'like', 'Resolution posted.%')
                        ->orWhere('comment', 'like', 'Confirmed working on our side%')
                        ->orWhere('comment', 'like', 'Thanks - we can provide access%');
                })
                ->delete();

            $base = ($ticket->reported_at ?? now())->copy();

            foreach ($script['notes'] as $offset => $note) {
                $when = $base->copy()->addMinutes((int) ($note['minutes'] ?? (30 + ($offset * 45))));
                TicketComment::query()->create([
                    'support_ticket_id' => $ticket->id,
                    'user_id' => $note['author']->id,
                    'comment' => $script['marker']."\n".$note['body'],
                    'visibility' => $note['visibility'],
                    'created_at' => $when,
                    'updated_at' => $when,
                ]);
            }

            if (! empty($script['history'])) {
                foreach ($script['history'] as $step) {
                    $when = $base->copy()->addMinutes((int) $step['minutes']);
                    $exists = TicketStatusHistory::query()
                        ->where('support_ticket_id', $ticket->id)
                        ->where('new_status', $step['to'])
                        ->where('notes', $step['notes'])
                        ->exists();
                    if ($exists) {
                        continue;
                    }
                    TicketStatusHistory::query()->create([
                        'support_ticket_id' => $ticket->id,
                        'old_status' => $step['from'],
                        'new_status' => $step['to'],
                        'changed_by' => $step['by']->id,
                        'notes' => $step['notes'],
                        'created_at' => $when,
                    ]);
                }
            }
        }
    }

    /**
     * @param  callable(User): string  $mention
     * @param  callable(string): string  $loc
     * @return list<array<string, mixed>>
     */
    private function scripts(callable $mention, callable $loc, User $faith, User $peter, User $grace, User $helen): array
    {
        return [
            [
                'ticket' => 'PG-2026-0148',
                'marker' => '[Demo cast · packaging label printer]',
                'asset' => 'PG-LBL-0142',
                'requester_id' => $grace->id,
                'assigned_to' => $faith->id,
                'status' => 'in_progress',
                'priority' => 'high',
                'partners' => [$grace->id, $faith->id, $peter->id, $helen->id],
                'notes' => [
                    [
                        'author' => $grace,
                        'visibility' => 'requester_visible',
                        'minutes' => 20,
                        'body' => "{$mention($faith)} - label printer **PG-LBL-0142** went dark mid-run in {$loc('PKG-HL')}.\n\n**What we see**\n- Power LED stays off after reseating the wall plug\n- Neighbouring PackLabel terminal still online\n- Finished-goods cases stacking without barcodes for the 14:00 dispatch\n\n{$mention($peter)} asked ICT to treat this as line-critical for Packaging Hall.",
                    ],
                    [
                        'author' => $peter,
                        'visibility' => 'requester_visible',
                        'minutes' => 55,
                        'body' => "Confirming impact from the floor: without **PG-LBL-0142** we cannot release pallets from {$loc('PKG-HL')}.\n\nPlease keep {$mention($grace)} and me on every update. If you need a short stop on line 2 for swap-out, say so and I will coordinate with the shift lead.",
                    ],
                    [
                        'author' => $faith,
                        'visibility' => 'internal',
                        'minutes' => 85,
                        'body' => "Internal - on site at {$loc('PKG-HL')}.\n\n**Checks so far**\n- Wall outlet measures OK; suspect PSU on **PG-LBL-0142**\n- Switch port on **PLT-SW-PACK** still up; this is not a network drop\n- Spare Zebra ZT411 PSU requested from ICT stores\n\n{$mention($helen)} - logging evidence photos and serial for the audit trail once the spare is fitted.\n\nNext: fit spare PSU, reprint test labels via PackLabel, then hand back to {$mention($grace)}.",
                    ],
                    [
                        'author' => $helen,
                        'visibility' => 'internal',
                        'minutes' => 110,
                        'body' => "Audit note - reviewed assignment chain for PG-2026-0148.\n\nPlease keep asset **PG-LBL-0142**, location {$loc('PKG-HL')}, and the PSU serial on the resolution note. I will sample this case in the next ICT evidence review.",
                    ],
                    [
                        'author' => $faith,
                        'visibility' => 'requester_visible',
                        'minutes' => 150,
                        'body' => "{$mention($grace)} / {$mention($peter)} - spare PSU fitted on **PG-LBL-0142** at {$loc('PKG-HL')}.\n\nTest pack of 20 labels printed cleanly through PackLabel. Please confirm the next production run looks normal and I will move this toward resolve.",
                    ],
                ],
                'history' => [
                    ['from' => 'new', 'to' => 'assigned', 'by' => $faith, 'minutes' => 40, 'notes' => 'Faith took ownership for packaging hall response.'],
                    ['from' => 'assigned', 'to' => 'in_progress', 'by' => $faith, 'minutes' => 80, 'notes' => 'On-site investigation at Packaging Hall.'],
                ],
            ],
            [
                'ticket' => 'PG-2026-0147',
                'marker' => '[Demo cast · warehouse remote access]',
                'requester_id' => $peter->id,
                'assigned_to' => $faith->id,
                'status' => 'in_progress',
                'priority' => 'medium',
                'partners' => [$peter->id, $faith->id, $grace->id],
                'notes' => [
                    [
                        'author' => $peter,
                        'visibility' => 'requester_visible',
                        'minutes' => 15,
                        'body' => "{$mention($faith)} - remote access token rejected while I was off-site covering warehouse queries.\n\nWork continues at {$loc('WHS-OF')}, but I need VPN for after-hours stock checks. {$mention($grace)} can confirm the on-site workaround is in place for today.",
                    ],
                    [
                        'author' => $grace,
                        'visibility' => 'requester_visible',
                        'minutes' => 40,
                        'body' => "Confirmed - we are processing receiving on the warehouse desk PC **PG-PC-0104** at {$loc('WHS-OF')} until VPN is restored for {$mention($peter)}.",
                    ],
                    [
                        'author' => $faith,
                        'visibility' => 'internal',
                        'minutes' => 70,
                        'body' => "Internal - token serial matches the directory record on **PLT-AD01**.\n\nDevice clock on Peter's handset was ~7 minutes ahead; nudging him to sync time and retry. If it still fails I will re-issue the token and walk through enrolment.\n\nAlso checking whether {$loc('WHS-OF')} Wi-Fi path is involved when he is on site.",
                    ],
                    [
                        'author' => $faith,
                        'visibility' => 'requester_visible',
                        'minutes' => 120,
                        'body' => "{$mention($peter)} - please retry VPN after confirming the phone clock matches plant time. If it fails again, reply here and I will push a fresh token within the hour.",
                    ],
                ],
            ],
            [
                'ticket' => 'PG-2026-0153',
                'marker' => '[Demo cast · packaging PC power dip]',
                'asset' => 'PG-PC-0101',
                'requester_id' => $grace->id,
                'assigned_to' => $faith->id,
                'status' => 'assigned',
                'priority' => 'high',
                'partners' => [$grace->id, $faith->id, $peter->id],
                'notes' => [
                    [
                        'author' => $grace,
                        'visibility' => 'requester_visible',
                        'minutes' => 10,
                        'body' => "{$mention($faith)} - packaging supervisor laptop **PG-PC-0101** stays on a black screen after this morning's power dip in {$loc('PKG-HL')}.\n\nCannot print dispatch notes. {$mention($peter)} is with me on the floor.",
                    ],
                    [
                        'author' => $peter,
                        'visibility' => 'requester_visible',
                        'minutes' => 35,
                        'body' => "Same symptoms on cold boot. We moved temporary note printing to the warehouse desk, but that is not sustainable past lunch.\n\nPlease prioritise {$loc('PKG-HL')} - this blocks packaging paperwork even if the labeler is healthy.",
                    ],
                    [
                        'author' => $faith,
                        'visibility' => 'internal',
                        'minutes' => 60,
                        'body' => "Internal - queued behind the label printer call, then straight to **PG-PC-0101** at {$loc('PKG-HL')}.\n\nPlan: check PSU/board after dip, try known-good charger from ICT stock, capture boot photo for {$mention($helen)} if hardware swap is needed.",
                    ],
                ],
            ],
            [
                'ticket' => 'PG-2026-0163',
                'marker' => '[Demo cast · FloorWeigh freeze]',
                'asset' => 'PG-SCL-0301',
                'requester_id' => $grace->id,
                'assigned_to' => $faith->id,
                'status' => 'in_progress',
                'priority' => 'critical',
                'partners' => [$grace->id, $faith->id, $peter->id, $helen->id],
                'notes' => [
                    [
                        'author' => $grace,
                        'visibility' => 'requester_visible',
                        'minutes' => 8,
                        'body' => "**Critical for intake** - FloorWeigh on **PG-SCL-0301** freezes after ~20 minutes in {$loc('PRC-HL')}.\n\n{$mention($faith)} {$mention($peter)} - operators are recording weights on paper. Need ICT on the scale PC now.",
                    ],
                    [
                        'author' => $peter,
                        'visibility' => 'requester_visible',
                        'minutes' => 25,
                        'body' => "Agree this is critical. Pause any non-urgent ICT work and get to {$loc('PRC-HL')}.\n\nIf we need a temporary second scale terminal, say what asset to relocate.",
                    ],
                    [
                        'author' => $faith,
                        'visibility' => 'internal',
                        'minutes' => 50,
                        'body' => "Internal - at {$loc('PRC-HL')} with **PG-SCL-0301**.\n\n**Findings**\n- App memory climbs until UI stops updating\n- Link to **PLT-SW-PROD** is stable (no flap)\n- Restarting FloorWeigh clears it for another ~20 minutes\n\nWorking a config change + capture for vendor. {$mention($helen)} - retaining memory dump path on the ticket for the control review.",
                    ],
                    [
                        'author' => $helen,
                        'visibility' => 'internal',
                        'minutes' => 75,
                        'body' => "Audit follow-up - please ensure the temporary paper process is noted, then closed out when FloorWeigh is stable again. Link the resolution back to asset **PG-SCL-0301** and location {$loc('PRC-HL')}.",
                    ],
                    [
                        'author' => $faith,
                        'visibility' => 'requester_visible',
                        'minutes' => 95,
                        'body' => "{$mention($grace)} - applied a temporary FloorWeigh setting change on **PG-SCL-0301**. Please run three consecutive intake batches and reply if the freeze returns.\n\n{$mention($peter)} - paper log can stop once Grace confirms.",
                    ],
                ],
            ],
            [
                'ticket' => 'PG-2026-0143',
                'marker' => '[Demo cast · QA printer jam]',
                'asset' => 'PG-PRT-0401',
                'requester_id' => $peter->id,
                'assigned_to' => $faith->id,
                'status' => 'assigned',
                'priority' => 'medium',
                'partners' => [$peter->id, $faith->id, $helen->id],
                'notes' => [
                    [
                        'author' => $peter,
                        'visibility' => 'requester_visible',
                        'minutes' => 12,
                        'body' => "{$mention($faith)} - QA cannot print the morning checklist; **PG-PRT-0401** jammed in {$loc('QA-LAB')}.\n\nNot as urgent as packaging, but certificates will slip if this sits past mid-morning.",
                    ],
                    [
                        'author' => $faith,
                        'visibility' => 'internal',
                        'minutes' => 45,
                        'body' => "Internal - assigned. Will clear jam at {$loc('QA-LAB')} after the packaging hall calls settle. Checking tray sensors and last toner change on **PG-PRT-0401**.",
                    ],
                    [
                        'author' => $helen,
                        'visibility' => 'internal',
                        'minutes' => 70,
                        'body' => "For evidence completeness: when cleared, note whether any certificate printouts were reprinted and attach the location link {$loc('QA-LAB')} in the public update.",
                    ],
                ],
            ],
            [
                'ticket' => 'PG-2026-0167',
                'marker' => '[Demo cast · packaging switch flap]',
                'asset' => 'PG-NET-0004',
                'requester_id' => $grace->id,
                'assigned_to' => $faith->id,
                'status' => 'assigned',
                'priority' => 'critical',
                'partners' => [$grace->id, $faith->id, $peter->id],
                'notes' => [
                    [
                        'author' => $grace,
                        'visibility' => 'requester_visible',
                        'minutes' => 5,
                        'body' => "{$mention($faith)} - brief network drops on packaging terminals via **PLT-SW-PACK** / asset **PG-NET-0004** in {$loc('PKG-HL')}.\n\nPackLabel jobs stutter when it happens. {$mention($peter)} is watching line 2.",
                    ],
                    [
                        'author' => $peter,
                        'visibility' => 'requester_visible',
                        'minutes' => 28,
                        'body' => "Saw two short freezes in 10 minutes. Please correlate with uplink counters before we blame the labeler again.",
                    ],
                    [
                        'author' => $faith,
                        'visibility' => 'internal',
                        'minutes' => 55,
                        'body' => "Internal - pulling interface counters on **PLT-SW-PACK** (**PG-NET-0004**) at {$loc('PKG-HL')}.\n\nAlso comparing against **PLT-SW-PROD** to see if the flap is hall-local. Will update {$mention($grace)} before changing any port config.",
                    ],
                ],
            ],
            [
                'ticket' => 'PG-2026-0172',
                'marker' => '[Demo cast · warehouse starter access]',
                'requester_id' => $peter->id,
                'assigned_to' => $faith->id,
                'status' => 'in_progress',
                'priority' => 'medium',
                'partners' => [$peter->id, $faith->id, $grace->id, $helen->id],
                'notes' => [
                    [
                        'author' => $peter,
                        'visibility' => 'requester_visible',
                        'minutes' => 20,
                        'body' => "{$mention($faith)} - new warehouse clerk starts tomorrow. Needs ERP read access and the finished-goods share mapped on the desk at {$loc('WHS-OF')}.\n\n{$mention($grace)} will buddy them on day one.",
                    ],
                    [
                        'author' => $faith,
                        'visibility' => 'internal',
                        'minutes' => 50,
                        'body' => "Internal - creating AD account against warehouse group, mapping share used by **PG-PC-0104** at {$loc('WHS-OF')}.\n\n{$mention($helen)} - access request captured here for joiner evidence; will attach group membership screenshot on close.",
                    ],
                    [
                        'author' => $helen,
                        'visibility' => 'internal',
                        'minutes' => 80,
                        'body' => "Noted for joiner control sample. Please confirm least-privilege (read-only ERP) and that access is limited to {$loc('WHS-OF')} warehouse shares only.",
                    ],
                    [
                        'author' => $faith,
                        'visibility' => 'requester_visible',
                        'minutes' => 110,
                        'body' => "{$mention($peter)} / {$mention($grace)} - account ready. Temporary password in the sealed envelope at ICT desk. On first login at {$loc('WHS-OF')} the share should map automatically; ping me if ERP tiles are missing.",
                    ],
                ],
            ],
            [
                'ticket' => 'PG-2026-0157',
                'marker' => '[Demo cast · app server memory]',
                'asset' => 'PG-SRV-0003',
                'requester_id' => $helen->id,
                'assigned_to' => $faith->id,
                'status' => 'in_progress',
                'priority' => 'critical',
                'partners' => [$helen->id, $faith->id, $peter->id],
                'notes' => [
                    [
                        'author' => $helen,
                        'visibility' => 'requester_visible',
                        'minutes' => 15,
                        'body' => "{$mention($faith)} - monitoring shows sustained memory pressure on **PLT-APP01** / **PG-SRV-0003** in {$loc('SRV-RM')} during the morning batch.\n\nRaising this so operations and ICT have a shared trail. {$mention($peter)} may feel ERP sluggishness on the floor.",
                    ],
                    [
                        'author' => $peter,
                        'visibility' => 'requester_visible',
                        'minutes' => 40,
                        'body' => "ERP screens in packaging were slow around 08:40. Not stopping the line, but please keep {$loc('PKG-HL')} informed if you recycle services.",
                    ],
                    [
                        'author' => $faith,
                        'visibility' => 'internal',
                        'minutes' => 65,
                        'body' => "Internal - on **PLT-APP01** in {$loc('SRV-RM')}.\n\nIdentifying top memory consumers; preparing a controlled recycle after the current batch. Will notify {$mention($helen)} and {$mention($peter)} before the window.",
                    ],
                    [
                        'author' => $faith,
                        'visibility' => 'requester_visible',
                        'minutes' => 100,
                        'body' => "{$mention($helen)} - recycle completed on **PLT-APP01**. Memory back under the warning line. Watching the next batch; {$mention($peter)} please shout if packaging ERP feels slow again.",
                    ],
                ],
            ],
        ];
    }
}
