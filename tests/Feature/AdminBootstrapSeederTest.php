<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/*
 * The Render Free plan has no shell, so docker-entrypoint.sh runs RoleSeeder and
 * AdminUserSeeder on every boot. These tests lock in the guarantees that makes
 * safe: idempotent, no hardcoded password, no duplicate administrators, no
 * onboarding lockout, and a clean skip when the variables are absent.
 */

test('the admin seeder does nothing when the variables are not set', function () {
    putenv('ADMIN_USERNAME');
    putenv('ADMIN_PASSWORD');
    unset($_ENV['ADMIN_USERNAME'], $_ENV['ADMIN_PASSWORD']);

    $this->seed(RoleSeeder::class);
    $this->seed(AdminUserSeeder::class);

    // No account may be created without an explicit password in the environment.
    expect(User::count())->toBe(0);
});

test('the admin seeder creates the administrator from environment variables', function () {
    putenv('ADMIN_USERNAME=render-admin');
    putenv('ADMIN_PASSWORD=strong-secret-123');
    $_ENV['ADMIN_USERNAME'] = 'render-admin';
    $_ENV['ADMIN_PASSWORD'] = 'strong-secret-123';

    $this->seed(RoleSeeder::class);
    $this->seed(AdminUserSeeder::class);

    $admin = User::where('username', 'render-admin')->firstOrFail();

    expect($admin->role?->role_name)->toBe('Administrator')
        ->and($admin->status)->toBe('active')
        // temporary_password must stay null, otherwise the login flow forces
        // the account through onboarding instead of the admin dashboard.
        ->and($admin->temporary_password)->toBeNull()
        // Login is by username, so a null email is expected and valid.
        ->and($admin->email)->toBeNull()
        ->and(Hash::check('strong-secret-123', $admin->password))->toBeTrue();

    putenv('ADMIN_PASSWORD');
    unset($_ENV['ADMIN_PASSWORD']);
});

test('running the admin seeder repeatedly never creates a duplicate account', function () {
    putenv('ADMIN_USERNAME=render-admin');
    putenv('ADMIN_PASSWORD=strong-secret-123');
    $_ENV['ADMIN_USERNAME'] = 'render-admin';
    $_ENV['ADMIN_PASSWORD'] = 'strong-secret-123';

    $this->seed(RoleSeeder::class);
    $this->seed(AdminUserSeeder::class);
    $this->seed(AdminUserSeeder::class);
    $this->seed(AdminUserSeeder::class);

    expect(User::where('username', 'render-admin')->count())->toBe(1)
        ->and(User::count())->toBe(1);

    putenv('ADMIN_PASSWORD');
    unset($_ENV['ADMIN_PASSWORD']);
});

test('an existing administrator password is never overwritten on redeploy', function () {
    $role = Role::create(['role_name' => 'Administrator']);
    User::create([
        'first_name' => '',
        'last_name' => '',
        'username' => 'render-admin',
        'email' => null,
        'password' => 'password-changed-by-human',
        'temporary_password' => null,
        'role_id' => $role->role_id,
        'status' => 'active',
    ]);

    putenv('ADMIN_USERNAME=render-admin');
    putenv('ADMIN_PASSWORD=strong-secret-123');
    $_ENV['ADMIN_USERNAME'] = 'render-admin';
    $_ENV['ADMIN_PASSWORD'] = 'strong-secret-123';

    $this->seed(AdminUserSeeder::class);

    $admin = User::where('username', 'render-admin')->firstOrFail();

    expect(User::count())->toBe(1)
        ->and(Hash::check('password-changed-by-human', $admin->password))->toBeTrue()
        ->and(Hash::check('strong-secret-123', $admin->password))->toBeFalse();

    putenv('ADMIN_PASSWORD');
    unset($_ENV['ADMIN_PASSWORD']);
});

test('a too short admin password is rejected instead of creating a weak account', function () {
    putenv('ADMIN_USERNAME=render-admin');
    putenv('ADMIN_PASSWORD=abc');
    $_ENV['ADMIN_USERNAME'] = 'render-admin';
    $_ENV['ADMIN_PASSWORD'] = 'abc';

    $this->seed(RoleSeeder::class);
    $this->seed(AdminUserSeeder::class);

    expect(User::count())->toBe(0);

    putenv('ADMIN_PASSWORD');
    unset($_ENV['ADMIN_PASSWORD']);
});

test('the admin seeder skips when the Administrator role is missing', function () {
    putenv('ADMIN_USERNAME=render-admin');
    putenv('ADMIN_PASSWORD=strong-secret-123');
    $_ENV['ADMIN_USERNAME'] = 'render-admin';
    $_ENV['ADMIN_PASSWORD'] = 'strong-secret-123';

    // RoleSeeder intentionally not run: the account must not be created
    // without a valid role_id.
    $this->seed(AdminUserSeeder::class);

    expect(User::count())->toBe(0);

    putenv('ADMIN_PASSWORD');
    unset($_ENV['ADMIN_PASSWORD']);
});