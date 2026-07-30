<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'role_name' => 'Administrator',
                'description' => 'Manages accounts',
            ],
            [
                'role_name' => 'School Head',
                'description' => 'Approves or denies requests',
            ],
            [
                'role_name' => 'Property Custodian',
                'description' => 'Manages inventory',
            ],
            [
                'role_name' => 'Inspector',
                'description' => 'Tracks items',
            ],
            [
                'role_name' => 'End User',
                'description' => 'Teacher who submits item requests',
            ],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(
                ['role_name' => $role['role_name']],
                $role
            );
        }
    }
}