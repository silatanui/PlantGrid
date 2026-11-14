<?php

namespace Tests\Feature;

use App\Models\DirectMessage;
use App\Models\SupportTicket;
use App\Models\SystemSetting;
use App\Models\TicketComment;
use App\Models\User;
use App\Services\LivePulse\LivePulseService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class LivePulseTest extends TestCase
{
    use RefreshDatabase;

    public function test_pulse_once_creates_tagged_activity_when_enabled(): void
    {
        $this->seed();

        config([
            'plantgrid.live_pulse.enabled' => true,
            'plantgrid.live_pulse.use_openai' => false,
            'plantgrid.live_pulse.ends_at' => '2026-12-31 23:59:59',
            'plantgrid.live_pulse.timezone' => 'Africa/Nairobi',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-11-13 10:00:00', 'Africa/Nairobi'));

        $beforeTickets = SupportTicket::query()->count();
        $beforeComments = TicketComment::query()->count();
        $beforeMessages = DirectMessage::query()->count();

        $pulse = app(LivePulseService::class);
        $pulse->setEnabled(true);
        $result = $pulse->run(forceOne: true);

        $this->assertTrue($result['ran']);
        $this->assertNotEmpty($result['events']);

        $marker = $pulse->marker();
        $createdSomething =
            SupportTicket::query()->count() > $beforeTickets
            || TicketComment::query()->where('comment', 'like', '%'.$marker.'%')->exists()
            || DirectMessage::query()->where('subject', 'like', '%'.$marker.'%')->exists()
            || SupportTicket::query()->where('description', 'like', '%'.$marker.'%')->exists();

        $historyTagged = \Illuminate\Support\Facades\DB::table('ticket_status_histories')
            ->where('notes', 'like', '%'.$marker.'%')
            ->exists();

        $this->assertTrue(
            $createdSomething
            || $historyTagged
            || SupportTicket::query()->where('sla_due_at', '<', now())->exists()
        );

        Carbon::setTestNow();
    }

    public function test_pulse_is_noop_after_end_date(): void
    {
        $this->seed();

        config([
            'plantgrid.live_pulse.enabled' => true,
            'plantgrid.live_pulse.use_openai' => false,
            'plantgrid.live_pulse.ends_at' => '2026-12-31 23:59:59',
            'plantgrid.live_pulse.timezone' => 'Africa/Nairobi',
        ]);

        Carbon::setTestNow(Carbon::parse('2027-01-02 10:00:00', 'Africa/Nairobi'));

        $beforeTickets = SupportTicket::query()->count();
        $beforeMessages = DirectMessage::query()->count();

        $result = app(LivePulseService::class)->run(forceOne: true);

        $this->assertFalse($result['ran']);
        $this->assertSame('ended', $result['reason']);
        $this->assertSame($beforeTickets, SupportTicket::query()->count());
        $this->assertSame($beforeMessages, DirectMessage::query()->count());

        Carbon::setTestNow();
    }

    public function test_pulse_respects_disabled_flag(): void
    {
        $this->seed();

        config([
            'plantgrid.live_pulse.enabled' => false,
            'plantgrid.live_pulse.use_openai' => false,
        ]);
        SystemSetting::putValue(LivePulseService::SETTING_ENABLED, '0');

        Carbon::setTestNow(Carbon::parse('2026-11-13 10:00:00', 'Africa/Nairobi'));

        $result = app(LivePulseService::class)->run(forceOne: true);

        $this->assertFalse($result['ran']);
        $this->assertSame('disabled', $result['reason']);

        Carbon::setTestNow();
    }

    public function test_admin_can_toggle_live_pulse(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'amina.okello@northridge.example')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.live-pulse.update'), ['live_pulse_enabled' => '1'])
            ->assertRedirect();

        $this->assertTrue(app(LivePulseService::class)->isEnabled());

        $this->actingAs($admin)
            ->post(route('admin.live-pulse.update'), [])
            ->assertRedirect();

        $this->assertFalse(app(LivePulseService::class)->isEnabled());
    }

    public function test_reset_command_removes_tagged_rows(): void
    {
        $this->seed();

        config([
            'plantgrid.live_pulse.enabled' => true,
            'plantgrid.live_pulse.use_openai' => false,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-11-13 10:00:00', 'Africa/Nairobi'));

        $pulse = app(LivePulseService::class);
        $pulse->setEnabled(true);
        for ($i = 0; $i < 5; $i++) {
            $pulse->run(forceOne: true);
        }

        $marker = $pulse->marker();
        $this->assertTrue(
            SupportTicket::query()->where('description', 'like', '%'.$marker.'%')->exists()
            || TicketComment::query()->where('comment', 'like', '%'.$marker.'%')->exists()
            || DirectMessage::query()->where('subject', 'like', '%'.$marker.'%')->exists()
        );

        Artisan::call('plantgrid:live-pulse-reset', ['--force' => true]);

        $this->assertFalse(SupportTicket::query()->where('description', 'like', '%'.$marker.'%')->exists());
        $this->assertFalse(TicketComment::query()->where('comment', 'like', '%'.$marker.'%')->exists());
        $this->assertFalse(DirectMessage::query()->where('subject', 'like', '%'.$marker.'%')->exists());

        Carbon::setTestNow();
    }

    public function test_quiet_window_skips_scheduled_events(): void
    {
        $this->seed();

        config([
            'plantgrid.live_pulse.enabled' => true,
            'plantgrid.live_pulse.use_openai' => false,
            'plantgrid.live_pulse.ends_at' => '2026-12-31 23:59:59',
            'plantgrid.live_pulse.timezone' => 'Africa/Nairobi',
        ]);

        $now = Carbon::parse('2026-11-13 10:00:00', 'Africa/Nairobi');
        Carbon::setTestNow($now);

        $pulse = app(LivePulseService::class);
        $pulse->setEnabled(true);
        SystemSetting::putValue(
            LivePulseService::SETTING_QUIET_UNTIL,
            $now->copy()->addMinutes(5)->toIso8601String()
        );

        $beforeTickets = SupportTicket::query()->count();
        $beforeComments = TicketComment::query()->count();

        $result = $pulse->run(forceOne: false);

        $this->assertTrue($result['ran']);
        $this->assertSame('quiet', $result['reason'] ?? null);
        $this->assertSame([], $result['events']);
        $this->assertSame($beforeTickets, SupportTicket::query()->count());
        $this->assertSame($beforeComments, TicketComment::query()->count());

        Carbon::setTestNow();
    }

    public function test_new_pulse_cases_prefer_linked_assets(): void
    {
        $this->seed();

        config([
            'plantgrid.live_pulse.enabled' => true,
            'plantgrid.live_pulse.use_openai' => false,
            'plantgrid.live_pulse.ends_at' => '2026-12-31 23:59:59',
            'plantgrid.live_pulse.timezone' => 'Africa/Nairobi',
            'plantgrid.live_pulse.weights' => [
                'new_case' => 100,
                'starve' => 0,
                'internal_note' => 0,
                'visible_note' => 0,
                'assign' => 0,
                'progress' => 0,
                'resolve' => 0,
                'close' => 0,
                'message' => 0,
                'monitoring' => 0,
            ],
        ]);

        Carbon::setTestNow(Carbon::parse('2026-11-13 10:00:00', 'Africa/Nairobi'));

        $pulse = app(LivePulseService::class);
        $pulse->setEnabled(true);

        $created = null;
        for ($i = 0; $i < 8; $i++) {
            $result = $pulse->run(forceOne: true);
            foreach ($result['events'] as $event) {
                if (str_starts_with($event, 'new_case ')) {
                    $number = trim(explode('@', substr($event, 9))[0]);
                    $created = SupportTicket::query()->where('ticket_number', $number)->first();
                    break 2;
                }
            }
        }

        $this->assertNotNull($created);
        $this->assertNotNull($created->asset_id);
        $this->assertStringContainsString('Linked device:', (string) $created->description);

        Carbon::setTestNow();
    }

    public function test_progress_posts_an_internal_note_on_stage_change(): void
    {
        $this->seed();

        config([
            'plantgrid.live_pulse.enabled' => true,
            'plantgrid.live_pulse.use_openai' => false,
            'plantgrid.live_pulse.ends_at' => '2026-12-31 23:59:59',
            'plantgrid.live_pulse.timezone' => 'Africa/Nairobi',
            'plantgrid.live_pulse.weights' => [
                'new_case' => 0,
                'starve' => 0,
                'internal_note' => 0,
                'visible_note' => 0,
                'assign' => 0,
                'progress' => 100,
                'resolve' => 0,
                'close' => 0,
                'message' => 0,
                'monitoring' => 0,
            ],
        ]);

        Carbon::setTestNow(Carbon::parse('2026-11-13 10:00:00', 'Africa/Nairobi'));

        $pulse = app(LivePulseService::class);
        $pulse->setEnabled(true);
        $marker = $pulse->marker();

        $before = TicketComment::query()->where('visibility', 'internal')->where('comment', 'like', '%'.$marker.'%')->count();

        $progressed = false;
        for ($i = 0; $i < 10; $i++) {
            $result = $pulse->run(forceOne: true);
            foreach ($result['events'] as $event) {
                if (str_starts_with($event, 'progress ') || str_contains($event, '_note ')) {
                    $progressed = true;
                    break 2;
                }
            }
        }

        $this->assertTrue($progressed);
        $this->assertGreaterThan(
            $before,
            TicketComment::query()->where('visibility', 'internal')->where('comment', 'like', '%'.$marker.'%')->count()
        );

        $sample = TicketComment::query()
            ->where('visibility', 'internal')
            ->where('comment', 'like', '%'.$marker.'%')
            ->latest('id')
            ->value('comment');

        $this->assertNotNull($sample);
        $this->assertStringContainsString('Internal', (string) $sample);
        $this->assertTrue(
            str_contains((string) $sample, '•') || str_contains((string) $sample, "\n"),
            'Internal notes should be multi-line checklists, not a single short line.'
        );

        Carbon::setTestNow();
    }
}
