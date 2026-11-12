<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class TeamMembersSeeder extends Seeder
{
    public function run(): void
    {
        $password = 'PlantGrid-Demo';
        $departments = Department::query()->get()->keyBy('code');
        $roles = Role::query()->get()->keyBy('name');

        $people = [
            // ICT
            ['Brian Otieno', 'brian.otieno@northridge.example', 'ICT', 'ICT Technician'],
            ['Mercy Chebet', 'mercy.chebet@northridge.example', 'ICT', 'ICT Technician'],
            ['Kevin Njoroge', 'kevin.njoroge@northridge.example', 'ICT', 'ICT Technician'],
            ['Lilian Auma', 'lilian.auma@northridge.example', 'ICT', 'ICT Technician'],
            ['Samwel Kiprono', 'samwel.kiprono@northridge.example', 'ICT', 'Auditor'],
            // Production
            ['Jane Muthoni', 'jane.muthoni@northridge.example', 'PRD', 'General Employee'],
            ['David Ochieng', 'david.ochieng@northridge.example', 'PRD', 'General Employee'],
            ['Ann Nyambura', 'ann.nyambura@northridge.example', 'PRD', 'General Employee'],
            ['Joseph Kariuki', 'joseph.kariuki@northridge.example', 'PRD', 'General Employee'],
            ['Esther Chepkoech', 'esther.chepkoech@northridge.example', 'PRD', 'General Employee'],
            ['Michael Wekesa', 'michael.wekesa@northridge.example', 'PRD', 'General Employee'],
            ['Catherine Atieno', 'catherine.atieno@northridge.example', 'PRD', 'Department Manager'],
            // Quality Assurance
            ['Naomi Wairimu', 'naomi.wairimu@northridge.example', 'QA', 'General Employee'],
            ['Paul Kipchumba', 'paul.kipchumba@northridge.example', 'QA', 'General Employee'],
            ['Irene Akinyi', 'irene.akinyi@northridge.example', 'QA', 'General Employee'],
            ['George Mutiso', 'george.mutiso@northridge.example', 'QA', 'Department Manager'],
            // Warehouse
            ['Ruth Njeri', 'ruth.njeri@northridge.example', 'WHS', 'General Employee'],
            ['Collins Barasa', 'collins.barasa@northridge.example', 'WHS', 'General Employee'],
            ['Doris Moraa', 'doris.moraa@northridge.example', 'WHS', 'General Employee'],
            ['Evans Kiprotich', 'evans.kiprotich@northridge.example', 'WHS', 'Department Manager'],
            // Maintenance
            ['Alex Maina', 'alex.maina@northridge.example', 'MNT', 'General Employee'],
            ['Beatrice Chelagat', 'beatrice.chelagat@northridge.example', 'MNT', 'General Employee'],
            ['Caleb Odhiambo', 'caleb.odhiambo@northridge.example', 'MNT', 'General Employee'],
            ['Diana Jepchirchir', 'diana.jepchirchir@northridge.example', 'MNT', 'Department Manager'],
            // Finance / Admin
            ['Francis Kamau', 'francis.kamau@northridge.example', 'FIN', 'General Employee'],
            ['Gladys Wanjiru', 'gladys.wanjiru@northridge.example', 'FIN', 'General Employee'],
            ['Henry Omondi', 'henry.omondi@northridge.example', 'FIN', 'General Employee'],
            ['Ivy Cherop', 'ivy.cherop@northridge.example', 'FIN', 'Department Manager'],
            // Cross-plant support
            ['James Kimani', 'james.kimani@northridge.example', 'ICT', 'General Employee'],
            ['Lucy Achieng', 'lucy.achieng@northridge.example', 'PRD', 'General Employee'],
        ];

        foreach ($people as [$name, $email, $departmentCode, $roleName]) {
            $department = $departments->get($departmentCode);
            $role = $roles->get($roleName);

            if ($department === null || $role === null) {
                continue;
            }

            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => $password,
                    'department_id' => $department->id,
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]
            );

            $user->roles()->sync([$role->id]);
        }
    }
}
