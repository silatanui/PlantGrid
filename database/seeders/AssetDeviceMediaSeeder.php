<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Support\DeviceMedia;
use Illuminate\Database\Seeder;

class AssetDeviceMediaSeeder extends Seeder
{
    public function run(): void
    {
        Asset::query()
            ->with('category:id,name')
            ->orderBy('id')
            ->each(function (Asset $asset) {
                $media = DeviceMedia::forAsset($asset);
                $asset->forceFill([
                    'specifications' => array_merge(
                        ['image_key' => $media['key'], 'summary' => $media['summary']],
                        $media['specs']
                    ),
                ])->save();
            });
    }
}
