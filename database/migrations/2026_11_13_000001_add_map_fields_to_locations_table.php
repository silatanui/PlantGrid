<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->string('map_zone', 32)->nullable()->after('status');
            $table->decimal('map_x', 6, 2)->nullable()->after('map_zone');
            $table->decimal('map_y', 6, 2)->nullable()->after('map_x');
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn(['map_zone', 'map_x', 'map_y']);
        });
    }
};
