<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'role_id']);
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('status')->default('active')->after('password');
            $table->dateTime('last_login_at')->nullable()->after('email_verified_at');
        });

        Schema::create('asset_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->text('support_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_tag')->unique();
            $table->string('name');
            $table->foreignId('asset_category_id')->constrained();
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable()->unique();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->date('purchase_date')->nullable();
            $table->date('warranty_expiry')->nullable();
            $table->string('condition')->default('good');
            $table->string('status')->default('active');
            $table->json('specifications')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'department_id']);
        });

        Schema::create('asset_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('assigned_at');
            $table->dateTime('returned_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('asset_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->string('previous_status')->nullable();
            $table->string('new_status');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('changed_at');
            $table->text('notes')->nullable();
        });

        Schema::create('asset_maintenance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('maintenance_type');
            $table->text('description');
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->string('status')->default('scheduled');
            $table->decimal('cost', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('network_subnets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('network_address');
            $table->unsignedTinyInteger('cidr');
            $table->string('gateway')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('vlans', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('vlan_number')->unique();
            $table->string('name');
            $table->foreignId('subnet_id')->nullable()->constrained('network_subnets')->nullOnDelete();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('network_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->string('hostname')->unique();
            $table->string('device_type');
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->string('management_ip')->nullable();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('ip_addresses', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address')->unique();
            $table->foreignId('subnet_id')->constrained('network_subnets')->cascadeOnDelete();
            $table->foreignId('vlan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->foreignId('assigned_network_device_id')->nullable()->constrained('network_devices')->nullOnDelete();
            $table->string('address_type')->default('static');
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['subnet_id', 'status']);
        });

        Schema::create('network_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_device_id')->constrained('network_devices')->cascadeOnDelete();
            $table->foreignId('destination_device_id')->constrained('network_devices')->cascadeOnDelete();
            $table->string('connection_type');
            $table->string('port_or_interface')->nullable();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->string('hostname')->unique();
            $table->string('operating_system');
            $table->string('os_version')->nullable();
            $table->string('server_role');
            $table->string('environment')->default('simulated_production');
            $table->unsignedInteger('cpu_cores')->nullable();
            $table->unsignedInteger('ram_gb')->nullable();
            $table->unsignedInteger('storage_gb')->nullable();
            $table->foreignId('ip_address_id')->nullable()->constrained('ip_addresses')->nullOnDelete();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('server_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('service_name');
            $table->text('description')->nullable();
            $table->unsignedInteger('port')->nullable();
            $table->string('protocol')->nullable();
            $table->string('status')->default('running');
            $table->timestamps();
        });

        Schema::create('system_applications', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('application_type');
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('version')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('ticket_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number')->unique();
            $table->foreignId('requester_id')->constrained('users');
            $table->foreignId('department_id')->constrained();
            $table->foreignId('ticket_category_id')->constrained();
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('system_application_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject');
            $table->text('description');
            $table->string('priority');
            $table->string('status')->default('new');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reported_at');
            $table->dateTime('due_at')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
            $table->index(['status', 'priority']);
            $table->index(['department_id', 'status']);
            $table->index('assigned_to');
        });

        Schema::create('ticket_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('comment');
            $table->string('visibility')->default('requester_visible');
            $table->timestamps();
        });

        Schema::create('ticket_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
            $table->string('old_status')->nullable();
            $table->string('new_status');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->dateTime('created_at')->useCurrent();
        });

        Schema::create('ticket_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('original_name');
            $table->string('stored_name');
            $table->string('storage_path');
            $table->string('mime_type')->nullable();
            $table->unsignedInteger('file_size');
            $table->timestamps();
        });

        Schema::create('production_systems', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('system_type');
            $table->foreignId('department_id')->constrained();
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('business_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('production_equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('production_system_id')->nullable()->constrained()->nullOnDelete();
            $table->string('equipment_type');
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('production_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_system_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('production_equipment_id')->nullable()->constrained('production_equipment')->nullOnDelete();
            $table->foreignId('support_ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->string('severity');
            $table->text('description');
            $table->text('operational_impact')->nullable();
            $table->unsignedInteger('downtime_minutes')->nullable();
            $table->string('status')->default('open');
            $table->dateTime('reported_at');
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('maintenance_records', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->text('description')->nullable();
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('server_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('network_device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('production_equipment_id')->nullable()->constrained('production_equipment')->nullOnDelete();
            $table->foreignId('assigned_technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('maintenance_type');
            $table->string('priority')->default('medium');
            $table->string('status')->default('scheduled');
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['status', 'scheduled_at']);
        });

        Schema::create('security_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reported_by')->constrained('users');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('incident_type');
            $table->string('severity');
            $table->text('description');
            $table->string('status')->default('open');
            $table->dateTime('reported_at');
            $table->dateTime('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('security_controls', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('control_category');
            $table->text('description')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('in_place');
            $table->string('review_frequency')->nullable();
            $table->dateTime('last_reviewed_at')->nullable();
            $table->dateTime('next_review_at')->nullable();
            $table->timestamps();
        });

        Schema::create('compliance_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('security_control_id')->constrained()->cascadeOnDelete();
            $table->foreignId('checked_by')->constrained('users');
            $table->date('review_date');
            $table->string('result');
            $table->string('evidence_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('backup_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('server_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('system_application_id')->nullable()->constrained()->nullOnDelete();
            $table->string('frequency');
            $table->string('backup_type');
            $table->unsignedInteger('retention_days');
            $table->string('status')->default('active');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('backup_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('backup_plan_id')->constrained()->cascadeOnDelete();
            $table->dateTime('started_at');
            $table->dateTime('completed_at')->nullable();
            $table->string('result');
            $table->decimal('size_mb', 12, 2)->nullable();
            $table->text('failure_reason')->nullable();
            $table->string('data_source')->default('simulated');
            $table->timestamps();
        });

        Schema::create('recovery_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('backup_run_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tested_by')->constrained('users');
            $table->date('test_date');
            $table->string('result');
            $table->text('notes')->nullable();
            $table->string('evidence_path')->nullable();
            $table->timestamps();
        });

        Schema::create('monitoring_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('metric_key')->unique();
            $table->string('unit');
            $table->text('description')->nullable();
            $table->decimal('warning_threshold', 10, 2)->nullable();
            $table->decimal('critical_threshold', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('monitoring_samples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitoring_metric_id')->constrained()->cascadeOnDelete();
            $table->string('target_type');
            $table->unsignedBigInteger('target_id');
            $table->decimal('value', 10, 2);
            $table->string('source')->default('simulated');
            $table->string('scenario')->nullable();
            $table->dateTime('collected_at');
            $table->timestamps();
            $table->index(['target_type', 'target_id', 'collected_at']);
        });

        Schema::create('monitoring_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitoring_metric_id')->nullable()->constrained()->nullOnDelete();
            $table->string('target_type');
            $table->unsignedBigInteger('target_id');
            $table->string('severity');
            $table->string('message');
            $table->decimal('observed_value', 10, 2)->nullable();
            $table->decimal('threshold_value', 10, 2)->nullable();
            $table->string('status')->default('open');
            $table->string('source')->default('simulated');
            $table->dateTime('triggered_at');
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('acknowledged_at')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['target_type', 'target_id', 'status']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->dateTime('created_at')->useCurrent()->index();
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key')->unique();
            $table->text('setting_value')->nullable();
            $table->string('description')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('monitoring_alerts');
        Schema::dropIfExists('monitoring_samples');
        Schema::dropIfExists('monitoring_metrics');
        Schema::dropIfExists('recovery_tests');
        Schema::dropIfExists('backup_runs');
        Schema::dropIfExists('backup_plans');
        Schema::dropIfExists('compliance_checks');
        Schema::dropIfExists('security_controls');
        Schema::dropIfExists('security_incidents');
        Schema::dropIfExists('maintenance_records');
        Schema::dropIfExists('production_incidents');
        Schema::dropIfExists('production_equipment');
        Schema::dropIfExists('production_systems');
        Schema::dropIfExists('ticket_attachments');
        Schema::dropIfExists('ticket_status_histories');
        Schema::dropIfExists('ticket_comments');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('ticket_categories');
        Schema::dropIfExists('system_applications');
        Schema::dropIfExists('server_services');
        Schema::dropIfExists('servers');
        Schema::dropIfExists('network_links');
        Schema::dropIfExists('ip_addresses');
        Schema::dropIfExists('network_devices');
        Schema::dropIfExists('vlans');
        Schema::dropIfExists('network_subnets');
        Schema::dropIfExists('asset_maintenance_records');
        Schema::dropIfExists('asset_status_histories');
        Schema::dropIfExists('asset_assignments');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('vendors');
        Schema::dropIfExists('asset_categories');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn(['status', 'last_login_at']);
        });

        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('departments');
    }
};
