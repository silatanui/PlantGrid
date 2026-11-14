<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->string('criticality')->default('medium')->after('status');
            $table->string('alternate_id')->nullable()->after('serial_number');
            $table->decimal('purchase_cost', 12, 2)->nullable()->after('purchase_date');
            $table->string('purchase_currency', 8)->nullable()->after('purchase_cost');
            $table->string('purchase_order')->nullable()->after('purchase_currency');
            $table->string('invoice_number')->nullable()->after('purchase_order');
            $table->unsignedSmallInteger('expected_life_months')->nullable()->after('warranty_expiry');
            $table->string('hostname')->nullable()->after('expected_life_months');
            $table->string('ip_address')->nullable()->after('hostname');
            $table->string('mac_address')->nullable()->after('ip_address');
            $table->foreignId('parent_asset_id')->nullable()->after('location_id')->constrained('assets')->nullOnDelete();
            $table->string('photo_path')->nullable()->after('notes');
        });

        Schema::create('asset_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('kind')->default('document'); // photo | document | invoice | other
            $table->string('original_name');
            $table->string('stored_name');
            $table->string('storage_path');
            $table->string('mime_type')->nullable();
            $table->unsignedInteger('file_size');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_attachments');

        Schema::table('assets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_asset_id');
            $table->dropColumn([
                'criticality',
                'alternate_id',
                'purchase_cost',
                'purchase_currency',
                'purchase_order',
                'invoice_number',
                'expected_life_months',
                'hostname',
                'ip_address',
                'mac_address',
                'photo_path',
            ]);
        });
    }
};
