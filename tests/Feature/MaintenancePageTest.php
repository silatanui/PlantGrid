<?php

namespace Tests\Feature;

use App\Models\MaintenanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenancePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_maintenance_index_shows_linked_records_and_technicians(): void
    {
        $this->seed();

        $manager = User::query()->where('email', 'daniel.mwangi@northridge.example')->firstOrFail();
        $record = MaintenanceRecord::query()->with(['asset', 'technician'])->whereNotNull('asset_id')->firstOrFail();

        $response = $this->actingAs($manager)->get(route('maintenance.index'));

        $response->assertOk()
            ->assertSee('Maintenance')
            ->assertSee('Reopen', false)
            ->assertSee(route('assets.show', $record->asset), false);

        if ($record->technician) {
            $response->assertSee(route('people.show', $record->technician), false);
        }
    }

    public function test_completed_maintenance_can_be_reopened(): void
    {
        $this->seed();

        $manager = User::query()->where('email', 'daniel.mwangi@northridge.example')->firstOrFail();
        $record = MaintenanceRecord::query()->firstOrFail();
        $record->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->actingAs($manager)
            ->post(route('maintenance.update', $record), ['status' => 'in_progress'])
            ->assertRedirect();

        $record->refresh();

        $this->assertSame('in_progress', $record->status);
        $this->assertNull($record->completed_at);
    }
}
