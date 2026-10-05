<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Creates the initial Administrator account from environment variables.
 *
 * Designed for the Render Free plan, which has no shell access, so the
 * docker entrypoint can run this on every boot:
 *
 *   - Both ADMIN_USERNAME and ADMIN_PASSWORD must be supplied. There is no
 *     default password and none is stored in source, so the account cannot be
 *     created with a known password by accident.
 *   - The account is matched on `username` (unique index from the
 *     add_role_and_profile_fields migration). If it already exists the seeder
 *     does nothing, so repeated deploys never duplicate the admin and never
 *     overwrite a password that was changed after the first boot.
 *   - Unsetting ADMIN_PASSWORD disables the mechanism: the seeder returns early
 *     and the deployment keeps working.
 *   - RoleSeeder::class must run first (it creates the roles with firstOrCreate).
 *
 * Local XAMPP/MySQL development is unaffected because nothing here runs unless
 * the variables are set; DatabaseSeeder is unchanged for local use.
 */
class AdminUserSeeder extends Seeder
{
    /**
     * Mirrors the min:6 rule the admin user-management form already enforces.
     */
    private const MIN_PASSWORD_LENGTH = 6;

    public function run(): void
    {
        $username = trim((string) env('ADMIN_USERNAME', ''));
        $password = (string) env('ADMIN_PASSWORD', '');

        if ($username === '' || $password === '') {
            $this->command?->info('AdminUserSeeder: ADMIN_USERNAME/ADMIN_PASSWORD not set, skipping.');

            return;
        }

        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            $this->command?->error(sprintf(
                'AdminUserSeeder: ADMIN_PASSWORD must be at least %d characters.',
                self::MIN_PASSWORD_LENGTH,
            ));

            return;
        }

        $adminRole = Role::where('role_name', 'Administrator')->first();

        if (! $adminRole) {
            $this->command?->error('AdminUserSeeder: Administrator role missing. Run RoleSeeder first.');

            return;
        }

        // Idempotency guard: an existing administrator is never modified.
        if (User::where('username', $username)->exists()) {
            $this->command?->info("AdminUserSeeder: user '{$username}' already exists, nothing to do.");

            return;
        }

        // Field set mirrors UserController::store(), which is the application's
        // own account-creation path. `email` is nullable and left null because
        // login is by username. `temporary_password` must stay null, otherwise
        // the login flow redirects the user into forced onboarding.
        User::create([
            'first_name' => '',
            'last_name' => '',
            'username' => $username,
            'email' => null,
            'password' => Hash::make($password),
            'temporary_password' => null,
            'role_id' => $adminRole->role_id,
            'status' => 'active',
        ]);

        $this->command?->info("AdminUserSeeder: administrator '{$username}' created.");
    }
}