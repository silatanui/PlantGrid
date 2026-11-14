<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class BrandingService
{
    public const FONTS = [
        'ibm-plex' => 'IBM Plex Sans',
        'outfit' => 'Outfit',
        'system' => 'System UI',
    ];

    public function forViews(): array
    {
        $logoPath = SystemSetting::getValue('brand_logo_path');
        $logoUrl = null;
        if ($logoPath && Storage::disk('public')->exists($logoPath)) {
            $logoUrl = asset('storage/'.$logoPath);
        }

        $font = SystemSetting::getValue('brand_font', 'ibm-plex') ?: 'ibm-plex';
        if (! array_key_exists($font, self::FONTS)) {
            $font = 'ibm-plex';
        }

        return [
            'brandName' => SystemSetting::getValue('brand_name', 'PlantGrid') ?: 'PlantGrid',
            'plantName' => SystemSetting::getValue('plant_name', 'Northridge Processing') ?: 'Northridge Processing',
            'plantChipMeta' => SystemSetting::getValue('plant_chip_meta', 'Demo plant') ?: 'Demo plant',
            'brandFont' => $font,
            'brandLogoUrl' => $logoUrl,
            'brandLogoPath' => $logoPath,
            'hasCustomLogo' => $logoUrl !== null,
        ];
    }

    public function adminState(): array
    {
        return array_merge($this->forViews(), [
            'fonts' => self::FONTS,
            'logoHint' => 'Preferred: transparent PNG or SVG. Wide wordmark 1200×260 px (min 600×130). Keep artwork in the centre; max about 2 MB.',
        ]);
    }

    public function update(array $data, ?UploadedFile $logo, ?int $userId, bool $reset = false): array
    {
        if ($reset) {
            $this->deleteLogoFile(SystemSetting::getValue('brand_logo_path'));
            SystemSetting::putValue('brand_name', 'PlantGrid', $userId, 'Product / brand display name');
            SystemSetting::putValue('plant_name', 'Northridge Processing', $userId, 'Fictional plant display name');
            SystemSetting::putValue('plant_chip_meta', 'Demo plant', $userId, 'Plant chip subtitle');
            SystemSetting::putValue('brand_font', 'ibm-plex', $userId, 'App UI font');
            SystemSetting::putValue('brand_logo_path', null, $userId, 'Custom brand logo path');

            return $this->forViews();
        }

        SystemSetting::putValue(
            'brand_name',
            trim((string) ($data['brand_name'] ?? 'PlantGrid')) ?: 'PlantGrid',
            $userId,
            'Product / brand display name'
        );
        SystemSetting::putValue(
            'plant_name',
            trim((string) ($data['plant_name'] ?? 'Northridge Processing')) ?: 'Northridge Processing',
            $userId,
            'Fictional plant display name'
        );
        SystemSetting::putValue(
            'plant_chip_meta',
            trim((string) ($data['plant_chip_meta'] ?? 'Demo plant')) ?: 'Demo plant',
            $userId,
            'Plant chip subtitle'
        );

        $font = (string) ($data['brand_font'] ?? 'ibm-plex');
        if (! array_key_exists($font, self::FONTS)) {
            $font = 'ibm-plex';
        }
        SystemSetting::putValue('brand_font', $font, $userId, 'App UI font');

        if (! empty($data['remove_logo'])) {
            $this->deleteLogoFile(SystemSetting::getValue('brand_logo_path'));
            SystemSetting::putValue('brand_logo_path', null, $userId, 'Custom brand logo path');
        }

        if ($logo) {
            $this->deleteLogoFile(SystemSetting::getValue('brand_logo_path'));
            $path = $logo->store('branding', 'public');
            SystemSetting::putValue('brand_logo_path', $path, $userId, 'Custom brand logo path');
        }

        return $this->forViews();
    }

    private function deleteLogoFile(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
