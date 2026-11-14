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

class AminaAssignedNotesSeeder extends Seeder
{
    public function run(): void
    {
        $amina = User::query()->where('email', 'amina.okello@northridge.example')->first();
        $faith = User::query()->where('email', 'faith.cheruiyot@northridge.example')->first();
        $peter = User::query()->where('email', 'peter.kamau@northridge.example')->first();
        $grace = User::query()->where('email', 'grace.wanjiku@northridge.example')->first();
        $helen = User::query()->where('email', 'helen.achieng@northridge.example')->first();
        $daniel = User::query()->where('email', 'daniel.mwangi@northridge.example')->first();

        if (! $amina || ! $faith || ! $peter || ! $grace || ! $helen) {
            return;
        }

        $locations = Location::query()->get()->keyBy('code');
        $assets = Asset::query()->get()->keyBy('asset_tag');
        $notify = app(NotificationService::class);

        $mention = fn (User $person): string => '@['.$person->name.'](people:'.$person->id.')';
        $loc = function (string $code) use ($locations): string {
            $location = $locations->get($code);

            return $location
                ? '@['.$location->name.'](location:'.$location->id.')'
                : $code;
        };

        foreach ($this->scripts($mention, $loc, $amina, $faith, $peter, $grace, $helen, $daniel) as $script) {
            $ticket = SupportTicket::query()->where('ticket_number', $script['ticket'])->first();
            if (! $ticket) {
                continue;
            }

            if (! empty($script['asset']) && isset($assets[$script['asset']])) {
                $ticket->asset_id = $assets[$script['asset']]->id;
            }

            $ticket->assigned_to = $amina->id;
            if (! empty($script['status'])) {
                $ticket->status = $script['status'];
            }
            if (! empty($script['priority'])) {
                $ticket->priority = $script['priority'];
            }
            $ticket->save();

            $notify->syncPartners($ticket, $script['partners'] ?? [], 'amina_notes');

            if (TicketComment::query()
                ->where('support_ticket_id', $ticket->id)
                ->where('comment', 'like', $script['marker'].'%')
                ->exists()) {
                continue;
            }

            TicketComment::query()
                ->where('support_ticket_id', $ticket->id)
                ->where(function ($query) {
                    $query->where('comment', 'like', 'Internal note:%')
                        ->orWhere('comment', 'like', 'Requester note:%')
                        ->orWhere('comment', 'like', 'Update: fix is in progress%')
                        ->orWhere('comment', 'like', 'Thanks - we can provide access%')
                        ->orWhere('comment', 'like', 'We need a short confirmation%');
                })
                ->delete();

            $base = ($ticket->reported_at ?? now())->copy();

            foreach ($script['notes'] as $offset => $note) {
                $when = $base->copy()->addMinutes((int) ($note['minutes'] ?? (20 + ($offset * 35))));
                TicketComment::query()->create([
                    'support_ticket_id' => $ticket->id,
                    'user_id' => $note['author']->id,
                    'comment' => $script['marker']."\n".$note['body'],
                    'visibility' => $note['visibility'],
                    'created_at' => $when,
                    'updated_at' => $when,
                ]);
            }

            foreach ($script['history'] ?? [] as $step) {
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
                    'created_at' => $base->copy()->addMinutes((int) $step['minutes']),
                ]);
            }
        }
    }

    /**
     * @param  callable(User): string  $m
     * @param  callable(string): string  $loc
     * @return list<array<string, mixed>>
     */
    private function scripts(
        callable $m,
        callable $loc,
        User $amina,
        User $faith,
        User $peter,
        User $grace,
        User $helen,
        ?User $daniel,
    ): array {
        $mgr = $daniel ?? $amina;

        return [
            [
                'ticket' => 'PG-2026-0182',
                'marker' => '[Amina pack · QA batch release ERP]',
                'asset' => 'PG-PC-0103',
                'status' => 'in_progress',
                'priority' => 'critical',
                'partners' => [$amina->id, $faith->id, $peter->id, $helen->id, $grace->id, $mgr->id],
                'notes' => [
                    [
                        'author' => $amina,
                        'visibility' => 'internal',
                        'minutes' => 15,
                        'body' => "**Intake - critical ERP blank screen**\n\nTook ownership after Francis escalated from {$loc('QA-LAB')}.\n\n**Reported behaviour**\n- Northridge ERP → *Batch release* opens a **blank form** after the morning refresh\n- Same on QA lab desktop **PG-PC-0103**\n- Other ERP tiles (holds, certificates) still load\n\n**Related / compare**\n- Earlier app-tier pressure: PG-2026-0157 on **PLT-APP01** / **PG-SRV-0003** in {$loc('SRV-RM')}\n- QA certificate macros (resolved): PG-2026-0168 - *different symptom*, same lab\n\n{$m($faith)} please pull last recycle times on **PLT-APP01** and confirm whether QA role cache was flushed.\n{$m($helen)} - keeping this in the evidence trail for the morning batch control sample.",
                    ],
                    [
                        'author' => $faith,
                        'visibility' => 'internal',
                        'minutes' => 40,
                        'body' => "{$m($amina)} - checked **PLT-APP01** in {$loc('SRV-RM')}.\n\n**Findings**\n- Memory back under warning after PG-2026-0157 recycle, but *QA batch-release* view still empty\n- Application log shows `NullReference` on `BatchReleasePresenter` for role **QA Supervisor** only\n- Packaging / warehouse ERP screens look normal (spot-checked with {$m($grace)} on a packaging terminal)\n\n**Hypothesis:** stale reference-data cache for hold/batch codes - similar pattern to closed case PG-2026-0218 (*QA hold codes missing*).\n\nI can refresh the QA reference cache in a **2-minute** maintenance window. Prefer after {$m($peter)} confirms no active release is mid-sign-off.",
                    ],
                    [
                        'author' => $peter,
                        'visibility' => 'requester_visible',
                        'minutes' => 55,
                        'body' => "{$m($amina)} / {$m($faith)} - no batch release is open on my side right now.\n\nPlease proceed with the cache refresh. {$loc('QA-LAB')} will wait for your go-ahead before retrying.\n\nIf it fails again, escalate - we cannot hold finished goods past the afternoon gate.",
                    ],
                    [
                        'author' => $amina,
                        'visibility' => 'internal',
                        'minutes' => 70,
                        'body' => "**Change window authorised**\n\n{$m($faith)} - run the QA reference-data refresh on **PLT-APP01**. Capture before/after screenshots of the blank vs populated *Batch release* screen on **PG-PC-0103** at {$loc('QA-LAB')}.\n\nCross-links for the pack:\n- Infrastructure parent pressure: **PG-2026-0157**\n- Prior hold-code cache fix: **PG-2026-0218**\n- Lab workstation: **PG-PC-0103** · location {$loc('QA-LAB')}\n\n{$m($helen)} - once screenshots are on the ticket, this is ready for your control sample.",
                    ],
                    [
                        'author' => $faith,
                        'visibility' => 'internal',
                        'minutes' => 95,
                        'body' => "Cache refresh completed on **PLT-APP01**.\n\n**Result**\n- *Batch release* form now lists hold codes and open batches on **PG-PC-0103**\n- No recycle storm; memory still healthy (unlike the spike that opened PG-2026-0157)\n\n{$m($amina)} - please ask Francis / QA to confirm from {$loc('QA-LAB')}. I will stay on the case until we get a clean sign-off.",
                    ],
                    [
                        'author' => $amina,
                        'visibility' => 'requester_visible',
                        'minutes' => 110,
                        'body' => "**Update for QA**\n\nWe refreshed the ERP reference cache on the application server after correlating this with earlier morning pressure on **PLT-APP01** (see related work on PG-2026-0157).\n\nPlease retry *Batch release* on the lab desktop at {$loc('QA-LAB')} (**PG-PC-0103**) and reply here.\n\n{$m($peter)} - you are cleared to resume releases once QA confirms.",
                    ],
                    [
                        'author' => $helen,
                        'visibility' => 'internal',
                        'minutes' => 130,
                        'body' => "**Audit note**\n\nEvidence expected on close:\n1. Screenshot of blank form (pre)\n2. Screenshot of populated form (post) on **PG-PC-0103**\n3. Linkage to location {$loc('QA-LAB')} and related cases **PG-2026-0157** / **PG-2026-0218**\n\n{$m($amina)} - tidy resolution text with those three anchors and I will sample this in the next review.",
                    ],
                ],
                'history' => [
                    ['from' => 'new', 'to' => 'assigned', 'by' => $amina, 'minutes' => 10, 'notes' => 'Amina took ownership of critical QA ERP blank screen.'],
                    ['from' => 'assigned', 'to' => 'in_progress', 'by' => $amina, 'minutes' => 35, 'notes' => 'Investigation with Faith on PLT-APP01 / QA cache.'],
                ],
            ],
            [
                'ticket' => 'PG-2026-0173',
                'marker' => '[Amina pack · packaging keyboard stick]',
                'asset' => 'PG-PC-0101',
                'status' => 'in_progress',
                'priority' => 'medium',
                'partners' => [$amina->id, $faith->id, $grace->id, $peter->id],
                'notes' => [
                    [
                        'author' => $amina,
                        'visibility' => 'internal',
                        'minutes' => 12,
                        'body' => "**Assigned - packaging keyboard sticky keys**\n\nAsset **PG-PC-0101** (packaging supervisor laptop) at {$loc('PKG-HL')}.\n\n**Context**\n- Afternoon handover: several keys stick, slowing dispatch notes\n- Same laptop family as power-dip case **PG-2026-0153** (still with {$m($faith)}) - *do not confuse*; that was black-screen, this is keyboard only\n- Label printer saga on the same hall: **PG-2026-0148** / **PG-LBL-0142** - unrelated hardware, same zone\n\n{$m($grace)} already on the floor; {$m($peter)} wants paperwork moving before second shift.\n\nPlan: inspect debris/spill, try known-good USB keyboard from ICT stores, decide swap vs clean.",
                    ],
                    [
                        'author' => $grace,
                        'visibility' => 'requester_visible',
                        'minutes' => 35,
                        'body' => "{$m($amina)} - confirmed on **PG-PC-0101** at {$loc('PKG-HL')}.\n\nKeys **A / S / Enter** stick worst after lunch. No liquid visible, but the palm rest feels dusty from packaging powder.\n\nWe can use a USB keyboard temporarily if you bring one - {$m($peter)} asked us not to stop the line for a full laptop swap unless you say so.",
                    ],
                    [
                        'author' => $peter,
                        'visibility' => 'requester_visible',
                        'minutes' => 50,
                        'body' => "Agree with {$m($grace)}. Prefer a *temporary* USB keyboard today so dispatch notes stay in {$loc('PKG-HL')}.\n\nIf **PG-PC-0101** needs a full swap, schedule it against the next changeover - we already have enough open work with **PG-2026-0153** and the labeler history on **PG-2026-0148**.",
                    ],
                    [
                        'author' => $amina,
                        'visibility' => 'internal',
                        'minutes' => 75,
                        'body' => "**On-site plan**\n\n{$m($faith)} - if you finish the black-screen triage on **PG-2026-0153** first, swing by with:\n- USB keyboard from ICT stores\n- Compressed air / swab kit for **PG-PC-0101**\n\nI will document whether this is *contamination* vs *end-of-life keyboard membrane*.\n\nKeep hall linkage clear: location {$loc('PKG-HL')}, asset **PG-PC-0101**, sibling case **PG-2026-0153**.",
                    ],
                    [
                        'author' => $faith,
                        'visibility' => 'internal',
                        'minutes' => 100,
                        'body' => "{$m($amina)} - delivered USB keyboard to {$loc('PKG-HL')}.\n\n**PG-PC-0101** built-in keyboard still sticky after air blast; membrane likely gone. External keyboard restores full typing for dispatch notes.\n\nRecommend: leave USB in place, order replacement laptop keyboard or swap chassis next maintenance window. Not blocking production now.\n\n{$m($grace)} tested Enter / A / S successfully.",
                    ],
                    [
                        'author' => $amina,
                        'visibility' => 'requester_visible',
                        'minutes' => 125,
                        'body' => "**Public update**\n\nTemporary USB keyboard fitted on **PG-PC-0101** at {$loc('PKG-HL')}. Dispatch notes should type normally again.\n\n*Please confirm* on the next handover. Permanent keyboard/chassis replacement will be scheduled so we do not collide with the power-dip work on **PG-2026-0153**.\n\n{$m($peter)} / {$m($grace)} - shout if stickiness returns on the external board (unlikely).",
                    ],
                    [
                        'author' => $amina,
                        'visibility' => 'internal',
                        'minutes' => 140,
                        'body' => "**Close-out draft (internal)**\n\n- Symptom: sticky keys on packaging supervisor laptop\n- Location: {$loc('PKG-HL')}\n- Asset: **PG-PC-0101**\n- Workaround: USB keyboard issued from ICT stores\n- Related hall cases: **PG-2026-0153** (black screen), **PG-2026-0148** (labeler) - tracked separately\n- Next: procurement note for keyboard/chassis; keep case *in progress* until {$m($grace)} confirms two clean handovers",
                    ],
                ],
                'history' => [
                    ['from' => 'new', 'to' => 'assigned', 'by' => $amina, 'minutes' => 8, 'notes' => 'Amina assigned packaging keyboard sticky-keys case.'],
                    ['from' => 'assigned', 'to' => 'in_progress', 'by' => $amina, 'minutes' => 70, 'notes' => 'On-floor workaround with Faith at Packaging Hall.'],
                ],
            ],
            [
                'ticket' => 'PG-2026-0159',
                'marker' => '[Amina pack · maintenance shared mailbox]',
                'status' => 'in_progress',
                'priority' => 'medium',
                'partners' => [$amina->id, $faith->id, $helen->id, $mgr->id],
                'notes' => [
                    [
                        'author' => $amina,
                        'visibility' => 'internal',
                        'minutes' => 18,
                        'body' => "**Assigned - maintenance shared mailbox**\n\nAfter a password reset, the maintenance shared mailbox stopped accepting mail.\n\n**Scope**\n- Affects planners coordinating vendors into {$loc('PRC-HL')} / workshop areas\n- Not the same as guest Wi-Fi request **PG-2026-0165** (vendor visit) - *keep separate*\n- Directory host still **PLT-AD01** / **PG-SRV-0001** in {$loc('SRV-RM')}\n\n{$m($faith)} - check whether the shared mailbox *enabled* flag flipped during the reset, and whether Send-As rights survived.\n{$m($helen)} - this is a joiner/mover style control; please note the reset ticket number in AD if you want it on the evidence pack.",
                    ],
                    [
                        'author' => $faith,
                        'visibility' => 'internal',
                        'minutes' => 45,
                        'body' => "{$m($amina)} - on **PLT-AD01**.\n\n**Findings**\n- Shared mailbox account was left in *disabled* state after the interactive password reset (common foot-gun)\n- Send-As for the maintenance DL still present\n- Mail flow test from ICT to the shared address *bounces* while disabled\n\nI can re-enable and force an Outlook offline-address-book sync. Will also check {$loc('WHS-OF')} / {$loc('PKG-HL')} distribution groups were not accidentally nested during the reset.\n\nRelated hygiene: starter access work on **PG-2026-0172** used the same AD console - worth a quick glance that we did not touch this mailbox there.",
                    ],
                    [
                        'author' => $helen,
                        'visibility' => 'internal',
                        'minutes' => 70,
                        'body' => "**Audit ask**\n\nBefore re-enable, please record:\n1. Who requested the original password reset\n2. That the mailbox remains a *shared* object (not converted to user mailbox)\n3. Location context for the maintenance team (workshop / {$loc('PRC-HL')} coordination)\n\n{$m($amina)} - once mail flows, add a short public note so Diana can verify from her side.",
                    ],
                    [
                        'author' => $amina,
                        'visibility' => 'internal',
                        'minutes' => 90,
                        'body' => "**Go-ahead**\n\n{$m($faith)} - re-enable the shared mailbox on **PLT-AD01**, send a test message, and confirm Diana can open it in Outlook.\n\nKeep this distinct from:\n- **PG-2026-0165** guest Wi-Fi for vendor visit\n- **PG-2026-0172** warehouse starter access\n\nBoth touch identity, but *this* case is mailbox availability only.",
                    ],
                    [
                        'author' => $faith,
                        'visibility' => 'internal',
                        'minutes' => 115,
                        'body' => "Mailbox re-enabled on **PLT-AD01**. Test mail delivered.\n\nOutlook on the maintenance planner PC may need *one* restart to refresh the GAL. No changes to Send-As.\n\n{$m($amina)} - ready for public confirmation note.",
                    ],
                    [
                        'author' => $amina,
                        'visibility' => 'requester_visible',
                        'minutes' => 130,
                        'body' => "**Update**\n\nThe maintenance shared mailbox is re-enabled in directory services (**PLT-AD01** in {$loc('SRV-RM')}). A test message delivered successfully.\n\nPlease:\n1. Restart Outlook once if the folder still looks empty\n2. Reply here when new mail arrives normally\n\n*Note:* this does **not** change guest Wi-Fi for vendors (tracked on PG-2026-0165) or warehouse starter access (PG-2026-0172).",
                    ],
                    [
                        'author' => $amina,
                        'visibility' => 'internal',
                        'minutes' => 150,
                        'body' => "**Internal wrap checklist**\n\n- Root cause: shared mailbox left *disabled* after password reset\n- Fix: re-enable on **PLT-AD01** / **PG-SRV-0001**\n- Evidence: test mail + {$m($helen)} control notes\n- Interconnections documented: **PG-2026-0165**, **PG-2026-0172** (identity-adjacent, separate outcomes)\n- Await Diana confirmation → then resolve",
                    ],
                ],
                'history' => [
                    ['from' => 'new', 'to' => 'assigned', 'by' => $amina, 'minutes' => 10, 'notes' => 'Amina assigned maintenance shared mailbox case.'],
                    ['from' => 'assigned', 'to' => 'in_progress', 'by' => $amina, 'minutes' => 40, 'notes' => 'AD investigation with Faith on PLT-AD01.'],
                ],
            ],
        ];
    }
}
