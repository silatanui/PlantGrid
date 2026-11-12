<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('job_title')->nullable()->after('email');
            $table->string('phone', 40)->nullable()->after('job_title');
            $table->string('mobile', 40)->nullable()->after('phone');
            $table->string('extension', 20)->nullable()->after('mobile');
            $table->string('address')->nullable()->after('extension');
            $table->string('city', 80)->nullable()->after('address');
            $table->string('postal_code', 24)->nullable()->after('city');
            $table->string('country', 80)->nullable()->after('postal_code');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'job_title',
                'phone',
                'mobile',
                'extension',
                'address',
                'city',
                'postal_code',
                'country',
            ]);
        });
    }
};
