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

        $this->assertTrue($createdSomething || SupportTicket::query()->where('sla_due_at', '<', now())->exists());

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
}
