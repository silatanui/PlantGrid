<?php

use App\Support\PlantMap;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (PlantMap::zones() as $zoneKey => $zone) {
            DB::table('locations')
                ->where('code', $zone['code'])
                ->update([
                    'map_zone' => $zoneKey,
                    'map_x' => $zone['x'],
                    'map_y' => $zone['y'],
                ]);
        }
    }

    public function down(): void
    {
        DB::table('locations')->update([
            'map_zone' => null,
            'map_x' => null,
            'map_y' => null,
        ]);
    }
};
