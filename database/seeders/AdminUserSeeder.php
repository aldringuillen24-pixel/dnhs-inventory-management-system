<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminRole = Role::where('role_name', 'Administrator')->first();

        if (!$adminRole) {
            $this->command->error('Administrator role not found. Please run RoleSeeder first.');
            return;
        }

        User::firstOrCreate(
            ['username' => 'admin'],
            [
                'role_id' => $adminRole->role_id,
                'last_name' => 'Admin',
                'first_name' => 'System',
                'username' => 'admin',
                'email' => 'admin@dnhs.edu.ph',
                'password' => Hash::make('admin123'),
                'status' => 'active',
            ]
        );
    }
}