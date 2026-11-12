<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkerPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_managers_can_view_worker_performance_board(): void
    {
        $this->seed();

        $manager = User::query()->where('email', 'daniel.mwangi@northridge.example')->firstOrFail();
        $technician = User::query()->where('email', 'faith.cheruiyot@northridge.example')->firstOrFail();

        $this->actingAs($manager)
            ->get(route('performance.index'))
            ->assertOk()
            ->assertSee('Worker performance')
            ->assertSee('Team CPM')
            ->assertSee('Cases per month')
            ->assertSee('Open vs resolved')
            ->assertSee('SLA hit rate')
            ->assertSee('30 days')
            ->assertSee($technician->name)
            ->assertSee('CPM');
    }

    public function test_person_drill_down_and_csv_export(): void
    {
        $this->seed();

        $manager = User::query()->where('email', 'daniel.mwangi@northridge.example')->firstOrFail();
        $technician = User::query()->where('email', 'faith.cheruiyot@northridge.example')->firstOrFail();

        $this->actingAs($manager)
            ->get(route('performance.index', [
                'from' => now()->subDays(90)->toDateString(),
                'to' => now()->toDateString(),
                'user_id' => $technician->id,
            ]))
            ->assertOk()
            ->assertSee('Resolved trend')
            ->assertSee('Open queue')
            ->assertSee('Leaderboard');

        $this->actingAs($manager)
            ->get(route('performance.export', [
                'from' => now()->subDays(90)->toDateString(),
                'to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_requesters_without_reports_permission_are_blocked(): void
    {
        $this->seed();

        $employee = User::query()->where('email', 'grace.wanjiku@northridge.example')->firstOrFail();

        $this->actingAs($employee)
            ->get(route('performance.index'))
            ->assertForbidden();
    }
}
