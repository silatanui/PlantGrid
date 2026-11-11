<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShellPanelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_shell_shows_guidance_and_my_cases_nav(): void
    {
        $this->seed();

        $grace = User::query()->where('email', 'grace.wanjiku@northridge.example')->firstOrFail();

        $this->actingAs($grace)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Navigation')
            ->assertSee('Utility')
            ->assertSee('Report plant ICT issues here')
            ->assertSee('My cases')
            ->assertDontSee('Network map');
    }

    public function test_ict_manager_shell_shows_queue_guidance_and_ops_shortcuts(): void
    {
        $this->seed();

        $daniel = User::query()->where('email', 'daniel.mwangi@northridge.example')->firstOrFail();

        $this->actingAs($daniel)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Keep the case queue moving')
            ->assertSee('Case queue')
            ->assertSee('Network map')
            ->assertSee('Simulated alerts');
    }
}
