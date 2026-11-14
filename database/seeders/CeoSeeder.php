<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\DemoContact;
use Illuminate\Database\Seeder;

class CeoSeeder extends Seeder
{
    /**
     * Idempotent: safe to run on an existing Northridge demo database.
     */
    public function run(): void
    {
        $permissionNames = [
            'dashboard.view', 'assets.view', 'network.view', 'servers.view',
            'tickets.view.all', 'maintenance.view', 'production.view',
            'security.view', 'backups.view', 'monitoring.view',
            'reports.view', 'reports.export', 'audit.view',
        ];

        $permissionIds = Permission::query()
            ->whereIn('name', $permissionNames)
            ->pluck('id', 'name');

        $role = Role::query()->firstOrCreate(
            ['name' => 'CEO'],
            ['description' => 'Chief Executive Officer access for the fictional Northridge demonstration.']
        );

        if ($permissionIds->isNotEmpty()) {
            $role->permissions()->sync($permissionIds->values()->all());
        }

        $department = Department::query()->where('code', 'FIN')->first()
            ?? Department::query()->orderBy('id')->first();

        if (! $department) {
            return;
        }

        $email = 'sila.tanui@northridge.example';
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Sila Kipngetich Tanui',
                'password' => 'PlantGrid-Demo',
                'department_id' => $department->id,
                'status' => 'active',
                'email_verified_at' => now(),
                ...DemoContact::for($email, 'FIN', 'CEO'),
            ]
        );

        $user->roles()->sync([$role->id]);
    }
}
