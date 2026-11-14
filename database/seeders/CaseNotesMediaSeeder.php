<?php

namespace Database\Seeders;

use App\Models\SupportTicket;
use App\Models\TicketAttachment;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class CaseNotesMediaSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()
            ->where('status', 'active')
            ->get()
            ->keyBy(fn (User $user) => strtolower((string) strtok((string) $user->email, '@')));

        $byEmail = User::query()->where('status', 'active')->get()->keyBy('email');

        $author = fn (string $email) => $byEmail->get($email)
            ?? $byEmail->get('faith.cheruiyot@northridge.example')
            ?? $byEmail->first();

        $mention = function (string $handle) use ($users): string {
            $person = $users->get(strtolower($handle));
            if (! $person) {
                return '@'.$handle;
            }

            return '@['.$person->name.'](people:'.$person->id.')';
        };

        $scripts = $this->noteScripts($mention);

        foreach ($scripts as $script) {
            $ticket = SupportTicket::query()->where('ticket_number', $script['ticket'])->first();
            if (! $ticket) {
                continue;
            }

            // Idempotent: skip if this demo note pack is already present.
            if (TicketComment::query()
                ->where('support_ticket_id', $ticket->id)
                ->where('comment', 'like', $script['marker'].'%')
                ->exists()) {
                continue;
            }

            // Replace the short generic investigation stub if present.
            TicketComment::query()
                ->where('support_ticket_id', $ticket->id)
                ->where('comment', 'Internal note: investigation started in the demonstration environment.')
                ->delete();

            foreach ($script['notes'] as $offset => $note) {
                $writer = $author($note['author']);
                $created = ($ticket->reported_at ?? now())->copy()->addHours($note['hours'] ?? (2 + $offset));

                TicketComment::query()->create([
                    'support_ticket_id' => $ticket->id,
                    'user_id' => $writer->id,
                    'comment' => $script['marker']."\n".$note['body'],
                    'visibility' => $note['visibility'],
                    'created_at' => $created,
                    'updated_at' => $created,
                ]);
            }

            foreach ($script['images'] ?? [] as $image) {
                $this->attachDemoImage(
                    $ticket,
                    $author($image['author'] ?? $script['notes'][0]['author']),
                    $image['source'],
                    $image['name'],
                    ($ticket->reported_at ?? now())->copy()->addHours($image['hours'] ?? 3)
                );
            }
        }
    }

    /**
     * @param  callable(string): string  $mention
     * @return list<array<string, mixed>>
     */
    private function noteScripts(callable $mention): array
    {
        return [
            [
                'ticket' => 'PG-2026-0148',
                'marker' => '[Demo note pack · packaging label printer]',
                'notes' => [
                    [
                        'author' => 'grace.wanjiku@northridge.example',
                        'visibility' => 'requester_visible',
                        'hours' => 1,
                        'body' => "Status: Reported from Packaging Hall A\nAsset: PG-LBL-0142 (Zebra ZT411)\nIssue Summary:\nThe packaging label printer powered off mid-shift. Display stays dark; no network light. Finished-goods cases are stacking without barcodes.\nObserved:\n• Power LED dark even after reseating the wall plug.\n• Neighbouring PackLabel terminal still online.\nPlease advise - line lead needs a workaround before the 14:00 dispatch window.",
                    ],
                    [
                        'author' => 'faith.cheruiyot@northridge.example',
                        'visibility' => 'internal',
                        'hours' => 2,
                        'body' => "Status: Escalated to floor ICT · awaiting parts confirmation\nAccount: Northridge Processing - Packaging Hall A (Plant demonstration SLA)\nIssue Summary:\nIntermittent / now hard power-loss on PG-LBL-0142 after Tuesday's PackLabel queue spike. Production has not stopped, but outbound cases cannot be labelled.\nTroubleshooting Completed:\n• Verified PackLabel service and print queue on PLT-APP01 - jobs queue cleanly, device never ACKs.\n• Confirmed 230V at the packaging bench outlet; neighbouring PC boots normally.\n• Swapped the IEC lead with a known-good spare - still dark. PSU click is absent.\nNext Steps:\n• ".$mention('brian.otieno')." Please bring the hot-spare ZT411 from ICT cage bay 3 and capture a photo of the PSU label before removal.\n• Do not clear the PackLabel backlog until the spare is online - QA needs the original job IDs.\n• Client Sentiment: Packaging supervisor is firm but fair; update before the 14:00 shift change.\nAttachment: bench photo of the dark printer faceplate.",
                    ],
                    [
                        'author' => 'brian.otieno@northridge.example',
                        'visibility' => 'internal',
                        'hours' => 4,
                        'body' => "Status: Spare staged · PSU suspect\nUpdate for ".$mention('faith.cheruiyot').":\nHot-spare ZT411 powered on first try. Original unit shows scorched smell at the PSU intake (photo attached). Logging RMA against Kite Label Systems demo vendor record.\nNext:\n• Cut over PackLabel queue to the spare hostname alias.\n• Keep original offline for vendor collection - do not scrap yet.",
                    ],
                ],
                'images' => [
                    ['source' => 'images/devices/label-printer.jpg', 'name' => 'pg-lbl-0142-dark-faceplate.jpg', 'author' => 'faith.cheruiyot@northridge.example', 'hours' => 2],
                ],
            ],
            [
                'ticket' => 'PG-2026-0147',
                'marker' => '[Demo note pack · remote access token]',
                'notes' => [
                    [
                        'author' => 'peter.kamau@northridge.example',
                        'visibility' => 'requester_visible',
                        'hours' => 2,
                        'body' => "Remote token keeps saying “clock skew / invalid serial”. Laptop time looks correct to me. I need warehouse stock checks from home tonight if possible.",
                    ],
                    [
                        'author' => 'faith.cheruiyot@northridge.example',
                        'visibility' => 'internal',
                        'hours' => 3,
                        'body' => "Status: Tier-1 identity check in progress\nAccount: Warehouse Supervision - remote access demo path\nIssue Summary:\nToken rejects at challenge after password OK. On-site work continues; remote coverage is the gap.\nTroubleshooting Completed:\n• Serial in the vault matches the physical fob etched number.\n• AD account unlocked; MFA methods show one stale phone entry.\n• Laptop BIOS clock was 11 minutes ahead of NTP (photo of Windows time dialog attached).\nNext Steps:\n• ".$mention('daniel.mwangi')." Approve temporary SMS MFA for 48h while we re-enrol the token.\n• User to leave the laptop powered overnight for a forced time sync policy.\n• Sentiment: Manager is calm - prefers a temporary workaround over waiting on a couriered spare fob.",
                    ],
                ],
                'images' => [
                    ['source' => 'images/devices/laptop.jpg', 'name' => 'warehouse-laptop-clock-skew.jpg', 'author' => 'faith.cheruiyot@northridge.example', 'hours' => 3],
                ],
            ],
            [
                'ticket' => 'PG-2026-0144',
                'marker' => '[Demo note pack · weigh scale network]',
                'notes' => [
                    [
                        'author' => 'grace.wanjiku@northridge.example',
                        'visibility' => 'requester_visible',
                        'hours' => 1,
                        'body' => "FloorWeigh on PG-SCL-0301 showed “cable disconnected” and froze intake. We paused the line for safety.",
                    ],
                    [
                        'author' => 'faith.cheruiyot@northridge.example',
                        'visibility' => 'internal',
                        'hours' => 2,
                        'body' => "Status: Closed after physical layer fix (demo)\nAccount: Intake / Processing Hall - FloorWeigh path\nIssue Summary:\nScale PC lost L2 to PLT-SW-PROD; MQTT heartbeats from the scale head stopped.\nTroubleshooting Completed:\n• Switch port Gi1/0/12 showed err-disabled flaps in the demonstration log.\n• Reseated the patch lead at the floor outlet; replaced a crushed clip (photo).\n• FloorWeigh resumed and published weights within 40s.\nNext Steps:\n• ".$mention('kevin.njoroge')." Schedule a weekly visual on intake patch leads - this is the third bend failure this quarter in the demo story.\n• No firmware change required.",
                    ],
                ],
                'images' => [
                    ['source' => 'images/devices/bench-scale.jpg', 'name' => 'pg-scl-0301-intake-bench.jpg', 'author' => 'faith.cheruiyot@northridge.example', 'hours' => 2],
                    ['source' => 'images/devices/network-switch.jpg', 'name' => 'plt-sw-prod-port-gi1-0-12.jpg', 'author' => 'faith.cheruiyot@northridge.example', 'hours' => 3],
                ],
            ],
            [
                'ticket' => 'PG-2026-0145',
                'marker' => '[Demo note pack · file server disk]',
                'notes' => [
                    [
                        'author' => 'daniel.mwangi@northridge.example',
                        'visibility' => 'internal',
                        'hours' => 2,
                        'body' => "Status: Critical disk warning cleared (simulation)\nHost: PLT-FS01 / PG-SRV-0002\nIssue Summary:\nVolume D: crossed 92% during the overnight archive job. Alerts fired to ICT on-call.\nTroubleshooting Completed:\n• Identified duplicate packaging photo packs from 2023 still outside the retention hold.\n• Moved archives older than the retention note to the holding folder on colder storage.\n• Volume returned to 71% in the demonstration metric.\nNext Steps:\n• ".$mention('amina.okello')." Please confirm audit is comfortable with the holding-folder path for the portfolio walkthrough.\n• ".$mention('brian.otieno')." Add a capacity watch for D: at 80% - we should not wait for 92% again.",
                    ],
                    [
                        'author' => 'amina.okello@northridge.example',
                        'visibility' => 'internal',
                        'hours' => 5,
                        'body' => "Audit note: holding-folder path is acceptable for the demo narrative. Keep the before/after capacity screenshot in the case media for the portfolio story.",
                    ],
                ],
                'images' => [
                    ['source' => 'images/devices/rack-server.jpg', 'name' => 'plt-fs01-rack-face.jpg', 'author' => 'brian.otieno@northridge.example', 'hours' => 3],
                ],
            ],
            [
                'ticket' => 'PG-2026-0143',
                'marker' => '[Demo note pack · QA printer jam]',
                'notes' => [
                    [
                        'author' => 'faith.cheruiyot@northridge.example',
                        'visibility' => 'internal',
                        'hours' => 1,
                        'body' => "Status: Assigned · hardware jam\nAsset: PG-PRT-0401 QA laboratory printer\nIssue Summary:\nMorning checklist print jammed behind the fuser path. QA cannot sign off intake samples.\nTroubleshooting Completed:\n• Opened the rear door - accordion fold of A4 lodged at the exit roller (photo).\n• Cleared jam; printed a 3-page test set successfully.\nNext Steps:\n• Ask QA to use the 80gsm stock only - the glossy checklist stock is out of printer spec.\n• ".$mention('mercy.chebet')." If jam repeats today, swap the exit roller kit from ICT spares.",
                    ],
                ],
                'images' => [
                    ['source' => 'images/devices/laser-printer.jpg', 'name' => 'pg-prt-0401-jam-cleared.jpg', 'author' => 'faith.cheruiyot@northridge.example', 'hours' => 1],
                ],
            ],
            [
                'ticket' => 'PG-2026-0157',
                'marker' => '[Demo note pack · app server memory]',
                'notes' => [
                    [
                        'author' => 'kevin.njoroge@northridge.example',
                        'visibility' => 'internal',
                        'hours' => 2,
                        'body' => "Status: Escalated to Tier 2 platform engineering (demo)\nHost: PLT-APP01 / PG-SRV-0003\nIssue Summary:\nSustained memory >88% through the morning PackLabel + ERP batch window. No outage yet; GC pauses are stretching print jobs.\nTroubleshooting Completed:\n• Confirmed no runaway Windows Update. PackLabel worker count doubled after yesterday's queue change.\n• Captured process list and Performance Monitor screenshot (attached).\n• Restarted the PackLabel worker pool - memory dropped to 64% for 20 minutes, then climbed again.\nNext Steps:\n• ".$mention('daniel.mwangi')." Approve a controlled recycle of the PackLabel app pool at 12:30 before the afternoon wave.\n• ".$mention('lilian.auma')." Review whether v4 worker concurrency should be capped at 4 for this demo host.\n• Sentiment: Production is watching the board; communicate before any recycle.",
                    ],
                    [
                        'author' => 'daniel.mwangi@northridge.example',
                        'visibility' => 'internal',
                        'hours' => 3,
                        'body' => "Approved 12:30 recycle. ".$mention('kevin.njoroge')." Post a requester-visible note 10 minutes before so packaging knows labels may pause for ~90 seconds.",
                    ],
                    [
                        'author' => 'kevin.njoroge@northridge.example',
                        'visibility' => 'requester_visible',
                        'hours' => 4,
                        'body' => "Heads-up: we will briefly recycle the PackLabel service at 12:30 to clear a memory pressure warning on the application server. Expect about 90 seconds without new labels. Existing queued jobs will resume automatically.",
                    ],
                ],
                'images' => [
                    ['source' => 'images/devices/rack-server.jpg', 'name' => 'plt-app01-memory-pressure.jpg', 'author' => 'kevin.njoroge@northridge.example', 'hours' => 2],
                ],
            ],
            [
                'ticket' => 'PG-2026-0154',
                'marker' => '[Demo note pack · warehouse Wi-Fi scanner]',
                'notes' => [
                    [
                        'author' => 'brian.otieno@northridge.example',
                        'visibility' => 'internal',
                        'hours' => 2,
                        'body' => "Status: RF path under review\nAsset context: Handheld scanners via PG-NET-0006 (PLT-AP-PACK)\nIssue Summary:\nReceiving handhelds drop association when operators walk past the shrink-wrap tunnel.\nTroubleshooting Completed:\n• AP neighbours look clean; channel 36 utilisation spikes when the tunnel heater cycles (demo RF story).\n• One scanner stayed stable when forced to 2.4 GHz - points to 5 GHz attenuation.\nNext Steps:\n• ".$mention('mercy.chebet')." Walk the tunnel with a phone Wi-Fi analyser screenshot at 10:00.\n• Do not wholesale move all scanners to 2.4 GHz - capacity is already tight on that band.",
                    ],
                ],
                'images' => [
                    ['source' => 'images/devices/access-point.jpg', 'name' => 'plt-ap-pack-ceiling.jpg', 'author' => 'brian.otieno@northridge.example', 'hours' => 2],
                    ['source' => 'images/devices/barcode-scanner.jpg', 'name' => 'warehouse-handheld-scanner.jpg', 'author' => 'brian.otieno@northridge.example', 'hours' => 3],
                ],
            ],
            [
                'ticket' => 'PG-2026-0163',
                'marker' => '[Demo note pack · FloorWeigh freeze]',
                'notes' => [
                    [
                        'author' => 'faith.cheruiyot@northridge.example',
                        'visibility' => 'internal',
                        'hours' => 1,
                        'body' => "Status: Escalated to Tier 2 applications + weighing vendor liaison\nAccount: Intake Line - FloorWeigh / PG-SCL-0301\nIssue Summary:\nClient reported weight readings freeze after ~20 minutes. Intake has not stopped, but QA rejection alerts are firing on stale weights.\nTroubleshooting Completed:\n• Verified gateway connectivity and local MQTT broker logs - latency normal (<15ms in the simulation).\n• Confirmed 24V DC at the junction box is steady.\n• Restarted FloorWeigh client on two benches; freeze returned on both after ~22 minutes.\nNext Steps:\n• ".$mention('lilian.auma')." Confirm whether the latest FloorWeigh build introduced a buffer issue under high-frequency polling.\n• Do not authorize a full-line client rollback until Engineering reviews the diagnostic dump named floorweigh_intake_trace.zip (placeholder in demo media).\n• Sentiment: Plant lead is frustrated; update before the 14:00 shift change.",
                    ],
                    [
                        'author' => 'grace.wanjiku@northridge.example',
                        'visibility' => 'requester_visible',
                        'hours' => 2,
                        'body' => "Thank you - we can keep weighing manually for now, but please keep us posted before afternoon dispatch. Stale weights are causing false QA rejects.",
                    ],
                ],
                'images' => [
                    ['source' => 'images/devices/bench-scale.jpg', 'name' => 'floorweigh-intake-head.jpg', 'author' => 'faith.cheruiyot@northridge.example', 'hours' => 1],
                ],
            ],
            [
                'ticket' => 'PG-2026-0164',
                'marker' => '[Demo note pack · suspicious logins]',
                'notes' => [
                    [
                        'author' => 'amina.okello@northridge.example',
                        'visibility' => 'internal',
                        'hours' => 2,
                        'body' => "Status: Security monitoring follow-up\nIssue Summary:\nRepeated failed logins against the demonstration service account overnight from an address inside the lab VLAN story.\nTroubleshooting Completed:\n• Account not locked out - threshold alerts only.\n• Source correlates with the QA lab desktop used for certificate macros (related case PG-2026-0168).\nNext Steps:\n• ".$mention('helen.achieng')." For the audit trail, keep this case linked to the macro-block incident.\n• ".$mention('faith.cheruiyot')." Rotate the service account password in the demo vault after confirming no scheduled job depends on the old secret tonight.",
                    ],
                ],
                'images' => [
                    ['source' => 'images/devices/desktop.jpg', 'name' => 'qa-lab-desktop-source.jpg', 'author' => 'amina.okello@northridge.example', 'hours' => 2],
                ],
            ],
            [
                'ticket' => 'PG-2026-0167',
                'marker' => '[Demo note pack · packaging switch flap]',
                'notes' => [
                    [
                        'author' => 'brian.otieno@northridge.example',
                        'visibility' => 'internal',
                        'hours' => 2,
                        'body' => "Status: Critical link flap - packaging fabric\nDevice: PG-NET-0004 / PLT-SW-PACK\nIssue Summary:\nBrief drops on packaging terminals; PackLabel sessions disconnect for 3-5 seconds.\nTroubleshooting Completed:\n• Interface counters show CRC climb on uplink to core.\n• Reseated fibre patch in SRV-RM; CRC growth stopped in the simulation (photo of patch panel).\nNext Steps:\n• ".$mention('daniel.mwangi')." Approve replacement of the suspect LC patch lead from spares.\n• Monitor for 24h before closing.",
                    ],
                    [
                        'author' => 'daniel.mwangi@northridge.example',
                        'visibility' => 'internal',
                        'hours' => 3,
                        'body' => "Approved. Replace the lead and label both ends. ".$mention('brian.otieno')." leave the old lead in the evidence bag for the vendor RMA story.",
                    ],
                ],
                'images' => [
                    ['source' => 'images/devices/network-switch.jpg', 'name' => 'plt-sw-pack-uplink.jpg', 'author' => 'brian.otieno@northridge.example', 'hours' => 2],
                ],
            ],
            [
                'ticket' => 'PG-2026-0158',
                'marker' => '[Demo note pack · PackLabel queue]',
                'notes' => [
                    [
                        'author' => 'mercy.chebet@northridge.example',
                        'visibility' => 'internal',
                        'hours' => 2,
                        'body' => "Status: Application queue stuck on line 2\nAsset/App: PG-LBL-0142 · PackLabel\nIssue Summary:\nJobs remain Queued and never reach Spooling after the morning reprint storm.\nTroubleshooting Completed:\n• Cleared two zombie jobs stuck in “Rendering”.\n• Printer path pings; device accepts a manual test page from the driver.\nNext Steps:\n• ".$mention('kevin.njoroge')." Correlate with PLT-APP01 memory case PG-2026-0157 - may be the same worker pool.\n• Requester update once queue drains below 20.",
                    ],
                ],
                'images' => [
                    ['source' => 'images/devices/label-printer.jpg', 'name' => 'packlabel-line2-printer.jpg', 'author' => 'mercy.chebet@northridge.example', 'hours' => 2],
                ],
            ],
            [
                'ticket' => 'PG-2026-0153',
                'marker' => '[Demo note pack · packaging PC black screen]',
                'notes' => [
                    [
                        'author' => 'faith.cheruiyot@northridge.example',
                        'visibility' => 'internal',
                        'hours' => 1,
                        'body' => "Status: New · power-dip aftermath\nAsset: PG-PC-0101 packaging terminal\nIssue Summary:\nBlack screen after morning power dip; no POST beep heard by operators.\nTroubleshooting Completed:\n• External monitor test not yet possible - waiting for a spare display cable.\n• PSU fan spins; motherboard diagnostic LED shows “DRAM” code on this model family (demo).\nNext Steps:\n• ".$mention('brian.otieno')." Reseat RAM and CMOS on site with ESD kit.\n• If no POST, swap with spare desktop from ICT cage and restore the PackLabel shortcut pack.",
                    ],
                ],
                'images' => [
                    ['source' => 'images/devices/desktop.jpg', 'name' => 'pg-pc-0101-black-screen-bench.jpg', 'author' => 'faith.cheruiyot@northridge.example', 'hours' => 1],
                ],
            ],
            [
                'ticket' => 'PG-2026-0162',
                'marker' => '[Demo note pack · finance scanner]',
                'notes' => [
                    [
                        'author' => 'lilian.auma@northridge.example',
                        'visibility' => 'internal',
                        'hours' => 2,
                        'body' => "Status: Hardware feed path\nAsset: PG-SCN-0201 (used at AP desk in this demo story)\nIssue Summary:\nDouble-sheet feeds duplicate invoice images.\nTroubleshooting Completed:\n• Cleaned separation pad; reduced thickness setting one notch.\n• Tested 10 invoices - one double-feed remained on curled stock.\nNext Steps:\n• Ask Finance to flatten curled invoices overnight.\n• ".$mention('faith.cheruiyot')." Order a separation-pad kit on the Helio demo vendor account if it repeats tomorrow.",
                    ],
                ],
                'images' => [
                    ['source' => 'images/devices/barcode-scanner.jpg', 'name' => 'finance-scan-station.jpg', 'author' => 'lilian.auma@northridge.example', 'hours' => 2],
                ],
            ],
            [
                'ticket' => 'PG-2026-0156',
                'marker' => '[Demo note pack · QA faint print]',
                'notes' => [
                    [
                        'author' => 'naomi.wairimu@northridge.example',
                        'visibility' => 'requester_visible',
                        'hours' => 1,
                        'body' => "Morning checklists from PG-PRT-0401 are too light to sign. Toner was swapped last week - still faint across the whole page.",
                    ],
                    [
                        'author' => 'mercy.chebet@northridge.example',
                        'visibility' => 'internal',
                        'hours' => 2,
                        'body' => "Status: Print path / density\nAsset: PG-PRT-0401 QA laboratory printer\nIssue Summary:\nDensity low on every page; not a single-job artefact.\nTroubleshooting Completed:\n• Printed the HP config page - toner remaining shows ~40% in the demo reading.\n• Drum life is near end-of-life on this model family (photo of supplies page).\nNext Steps:\n• ".$mention('faith.cheruiyot')." Issue a drum kit from ICT stores; keep the faint sample set in the case media for the vendor claim.\n• Requester can use the admin-block copier for today only - remind QA not to leave originals there overnight.",
                    ],
                ],
                'images' => [
                    ['source' => 'images/devices/laser-printer.jpg', 'name' => 'pg-prt-0401-faint-supplies.jpg', 'author' => 'mercy.chebet@northridge.example', 'hours' => 2],
                ],
            ],
            [
                'ticket' => 'PG-2026-0155',
                'marker' => '[Demo note pack · ERP login timeout]',
                'notes' => [
                    [
                        'author' => 'kevin.njoroge@northridge.example',
                        'visibility' => 'internal',
                        'hours' => 2,
                        'body' => "Status: App tier + auth path\nApp: Northridge ERP (Demo 4.2)\nIssue Summary:\nTwo finance clerks hit login timeout from the admin block. Warehouse ERP sessions still open.\nTroubleshooting Completed:\n• PLT-APP01 HTTPS responds; AD auth from finance VLAN shows intermittent LDAP bind delays in the simulation log.\n• Cleared stale Kerberos tickets on one clerk PC - improved for that user only.\nNext Steps:\n• ".$mention('lilian.auma')." Check directory replication health on PLT-AD01 before we blame the ERP pool.\n• ".$mention('gladys.wanjiru')." Please retry from a cold browser profile and note the exact error text.",
                    ],
                ],
                'images' => [
                    ['source' => 'images/devices/tower-server.jpg', 'name' => 'plt-ad01-directory-host.jpg', 'author' => 'kevin.njoroge@northridge.example', 'hours' => 2],
                ],
            ],
            [
                'ticket' => 'PG-2026-0161',
                'marker' => '[Demo note pack · QA file share]',
                'notes' => [
                    [
                        'author' => 'paul.kipchumba@northridge.example',
                        'visibility' => 'requester_visible',
                        'hours' => 1,
                        'body' => "QA-LAB desktop cannot map \\\\PLT-FS01\\qa-templates. Certificate work is blocked until we can open the folder.",
                    ],
                    [
                        'author' => 'brian.otieno@northridge.example',
                        'visibility' => 'internal',
                        'hours' => 3,
                        'body' => "Status: Share ACL + name resolution\nHost: PLT-FS01 / PG-SRV-0002\nIssue Summary:\nMap fails with “network path not found” from PG-PC-0103 only.\nTroubleshooting Completed:\n• Ping to 10.10.10.12 succeeds; DNS suffix for the QA VLAN was missing the plant.local search list in the demo story.\n• Added the suffix via the desktop script and remapped - share opens.\nNext Steps:\n• ".$mention('helen.achieng')." For the audit pack, keep the before/after IP config screenshot attached.\n• Roll the same suffix fix into the QA desktop image checklist.",
                    ],
                ],
                'images' => [
                    ['source' => 'images/devices/desktop.jpg', 'name' => 'qa-lab-pg-pc-0103.jpg', 'author' => 'brian.otieno@northridge.example', 'hours' => 3],
                    ['source' => 'images/devices/rack-server.jpg', 'name' => 'plt-fs01-share-host.jpg', 'author' => 'brian.otieno@northridge.example', 'hours' => 4],
                ],
            ],
            [
                'ticket' => 'PG-2026-0165',
                'marker' => '[Demo note pack · vendor guest Wi-Fi]',
                'notes' => [
                    [
                        'author' => 'diana.jepchirchir@northridge.example',
                        'visibility' => 'requester_visible',
                        'hours' => 1,
                        'body' => "Calibration vendor (Savannah Scale Works contact) visits next Tuesday. They need guest wireless for tablet documentation only - no plant systems.",
                    ],
                    [
                        'author' => 'samwel.kiprono@northridge.example',
                        'visibility' => 'internal',
                        'hours' => 2,
                        'body' => "Status: Guest SSID prep (audit-aware)\nIssue Summary:\nTemporary guest access for a scheduled vendor visit.\nControls:\n• Use the existing Guest-Plant SSID with a 48-hour voucher - do not bridge to VLAN 20.\n• ".$mention('amina.okello')." Confirm voucher logging is retained for 90 days per the demo security control.\n• ".$mention('brian.otieno')." Print two vouchers and leave them with Maintenance reception the morning of the visit.\nAttachment: AP coverage photo near the intake bay for the walkthrough.",
                    ],
                ],
                'images' => [
                    ['source' => 'images/devices/access-point.jpg', 'name' => 'guest-wifi-intake-coverage.jpg', 'author' => 'samwel.kiprono@northridge.example', 'hours' => 2],
                    ['source' => 'images/devices/firewall.jpg', 'name' => 'plt-fw01-guest-policy-ref.jpg', 'author' => 'samwel.kiprono@northridge.example', 'hours' => 3],
                ],
            ],
            [
                'ticket' => 'PG-2026-0168',
                'marker' => '[Demo note pack · certificate macros]',
                'notes' => [
                    [
                        'author' => 'irene.akinyi@northridge.example',
                        'visibility' => 'requester_visible',
                        'hours' => 1,
                        'body' => "Certificate workbook macros are blocked after the Office update. We cannot generate batch certificates for today’s intake.",
                    ],
                    [
                        'author' => 'lilian.auma@northridge.example',
                        'visibility' => 'internal',
                        'hours' => 3,
                        'body' => "Status: Resolved in demo · trusted location restored\nIssue Summary:\nOffice update cleared the QA templates trusted location.\nTroubleshooting Completed:\n• Re-added \\\\PLT-FS01\\qa-templates to Trusted Locations via GPO preference (simulation).\n• Opened the certificate pack on two QA PCs - macros run without the block prompt.\nNext Steps:\n• Linked security follow-up: ".$mention('amina.okello')." see case PG-2026-0164 for the failed-login noise from the same lab desktop during testing.\n• Keep the supplies/desktop photo for the change evidence pack.",
                    ],
                ],
                'images' => [
                    ['source' => 'images/devices/desktop.jpg', 'name' => 'qa-certificate-macro-desktop.jpg', 'author' => 'lilian.auma@northridge.example', 'hours' => 3],
                ],
            ],
            [
                'ticket' => 'PG-2026-0170',
                'marker' => '[Demo note pack · file share latency]',
                'notes' => [
                    [
                        'author' => 'kevin.njoroge@northridge.example',
                        'visibility' => 'internal',
                        'hours' => 2,
                        'body' => "Status: Closed after backup contention cleared\nHost: PLT-FS01\nIssue Summary:\nAdmin block users reported multi-second opens on department shares.\nTroubleshooting Completed:\n• Found a stuck incremental backup job saturating the volume in the simulation.\n• Cancelled the hung job; share latency returned to baseline within 15 minutes.\nNext Steps:\n• ".$mention('daniel.mwangi')." Review backup window so the incremental does not overlap the morning office spike.\n• Rack face photo attached for the portfolio walkthrough.",
                    ],
                ],
                'images' => [
                    ['source' => 'images/devices/rack-server.jpg', 'name' => 'plt-fs01-latency-cleared.jpg', 'author' => 'kevin.njoroge@northridge.example', 'hours' => 2],
                ],
            ],
            [
                'ticket' => 'PG-2026-0159',
                'marker' => '[Demo note pack · maintenance mailbox]',
                'notes' => [
                    [
                        'author' => 'caleb.odhiambo@northridge.example',
                        'visibility' => 'requester_visible',
                        'hours' => 1,
                        'body' => "Maintenance shared mailbox stopped accepting mail after the password reset. Vendors are bouncing.",
                    ],
                    [
                        'author' => 'mercy.chebet@northridge.example',
                        'visibility' => 'internal',
                        'hours' => 2,
                        'body' => "Status: Shared mailbox + send-as\nIssue Summary:\nPassword reset on the shared account broke Outlook profiles that cached the old secret.\nTroubleshooting Completed:\n• Reset mailbox permissions (Full Access + Send As) for the Maintenance distribution list in the demo directory.\n• Asked ".$mention('beatrice.chelagat')." to remove and re-add the mailbox in Outlook.\nNext Steps:\n• Document the reset checklist so the next password rotation does not strand the team.\n• No hardware media - identity-only case.",
                    ],
                ],
                'images' => [],
            ],
        ];
    }

    private function attachDemoImage(
        SupportTicket $ticket,
        User $uploader,
        string $publicRelative,
        string $originalName,
        $createdAt
    ): void {
        if (TicketAttachment::query()
            ->where('support_ticket_id', $ticket->id)
            ->where('original_name', $originalName)
            ->exists()) {
            return;
        }

        $source = public_path($publicRelative);
        if (! is_file($source)) {
            return;
        }

        $storedName = basename($originalName);
        $storagePath = 'tickets/'.$ticket->id.'/'.$storedName;
        Storage::disk('local')->put($storagePath, File::get($source));

        TicketAttachment::query()->create([
            'support_ticket_id' => $ticket->id,
            'uploaded_by' => $uploader->id,
            'original_name' => $originalName,
            'stored_name' => $storedName,
            'storage_path' => $storagePath,
            'mime_type' => 'image/jpeg',
            'file_size' => filesize($source) ?: 0,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
