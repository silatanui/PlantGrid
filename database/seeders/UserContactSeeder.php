<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\DemoContact;
use Illuminate\Database\Seeder;

class UserContactSeeder extends Seeder
{
    public function run(): void
    {
        User::query()
            ->with(['department:id,code', 'roles:id,name'])
            ->orderBy('id')
            ->each(function (User $user) {
                $contact = DemoContact::for(
                    $user->email,
                    $user->department?->code,
                    $user->roles->first()?->name
                );

                $user->forceFill($contact)->save();
            });
    }
}
