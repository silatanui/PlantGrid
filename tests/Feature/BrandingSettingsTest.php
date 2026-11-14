<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\BrandingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandingSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_and_reset_branding(): void
    {
        Storage::fake('public');
        $this->seed();

        $admin = User::query()->where('email', 'amina.okello@northridge.example')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.branding'))
            ->assertOk()
            ->assertSee('Branding & appearance');

        $this->actingAs($admin)
            ->post(route('admin.branding.update'), [
                'brand_name' => 'AcmeOps',
                'plant_name' => 'Acme Plant',
                'plant_chip_meta' => 'Trial plant',
                'brand_font' => 'outfit',
                'brand_logo' => UploadedFile::fake()->image('logo.png', 1200, 260),
            ])
            ->assertRedirect(route('admin.branding'));

        $branding = app(BrandingService::class)->forViews();
        $this->assertSame('AcmeOps', $branding['brandName']);
        $this->assertSame('Acme Plant', $branding['plantName']);
        $this->assertSame('Trial plant', $branding['plantChipMeta']);
        $this->assertSame('outfit', $branding['brandFont']);
        $this->assertTrue($branding['hasCustomLogo']);

        $this->actingAs($admin)
            ->post(route('admin.branding.update'), [
                'reset_branding' => '1',
            ])
            ->assertRedirect();

        $reset = app(BrandingService::class)->forViews();
        $this->assertSame('PlantGrid', $reset['brandName']);
        $this->assertSame('Northridge Processing', $reset['plantName']);
        $this->assertFalse($reset['hasCustomLogo']);
    }
}
