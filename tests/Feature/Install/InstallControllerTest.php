<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Installer\DatabaseConnectionTester;
use App\Services\Installer\EnvWriter;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

// Guard: all install tests must run without .installed present
beforeEach(function (): void {
    $flag = storage_path('app/.installed');
    if (file_exists($flag)) {
        unlink($flag);
    }

    // Force file sessions so tests don't depend on DB session table being empty
    config(['session.driver' => 'file']);

    // Override APP_INSTALLED so env flag never blocks installer routes in tests
    config(['app.installed' => false]);

    // Seed roles so step 4 can assign the Admin role
    $this->seed(RolesAndPermissionsSeeder::class);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
});

afterEach(function (): void {
    $flag = storage_path('app/.installed');
    if (file_exists($flag)) {
        unlink($flag);
    }
});

// ── Middleware guard ──────────────────────────────────────────────────────────

it('redirects root to /install when not installed', function (): void {
    $this->get('/')->assertRedirect('/install');
});

it('allows /install through when not installed', function (): void {
    $this->get('/install')->assertRedirectToRoute('install.step', 1);
});

it('redirects /install/step/1 to login with info toast when already installed', function (): void {
    file_put_contents(storage_path('app/.installed'), '{}');

    $this->get(route('install.step', 1))
        ->assertRedirect(route('login'))
        ->assertSessionHas('info', 'The application is already installed.');
});

it('redirects /install/complete to login with warning when not installed', function (): void {
    $this->get(route('install.complete'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('warning', 'Installation has not been completed yet.');
});

it('shows /install/complete when installed', function (): void {
    file_put_contents(storage_path('app/.installed'), '{}');

    $this->get(route('install.complete'))->assertOk()->assertViewIs('install.complete');
});

// ── Step 1 — Requirements ─────────────────────────────────────────────────────

it('shows step 1 requirements page', function (): void {
    $this->get(route('install.step', 1))
        ->assertOk()
        ->assertViewIs('install.steps.1-requirements')
        ->assertSee('System Requirements');
});

it('cannot view step 2 before completing step 1', function (): void {
    $this->get(route('install.step', 2))
        ->assertRedirectToRoute('install.step', 1);
});

it('completes step 1 and redirects to step 2', function (): void {
    $this->post(route('install.process', 1))
        ->assertRedirectToRoute('install.step', 2);
});

it('stores step 1 in session completed_steps after POST', function (): void {
    $this->post(route('install.process', 1));

    $state = session('installer');
    expect($state['completed_steps'])->toContain(1);
});

// ── Step 2 — Database ─────────────────────────────────────────────────────────

it('shows step 2 database page when step 1 is complete', function (): void {
    $session = ['installer' => ['current_step' => 2, 'completed_steps' => [1], 'data' => []]];

    $this->withSession($session)
        ->get(route('install.step', 2))
        ->assertOk()
        ->assertViewIs('install.steps.2-database');
});

it('validates required database fields', function (): void {
    $session = ['installer' => ['current_step' => 2, 'completed_steps' => [1], 'data' => []]];

    $this->withSession($session)
        ->post(route('install.process', 2), [])
        ->assertSessionHasErrors(['db_host', 'db_port', 'db_database', 'db_username']);
});

it('shows connection error when PDO fails', function (): void {
    $session = ['installer' => ['current_step' => 2, 'completed_steps' => [1], 'data' => []]];

    $mock = Mockery::mock(DatabaseConnectionTester::class);
    $mock->shouldReceive('connect')->andThrow(new \PDOException('Access denied'));
    app()->instance(DatabaseConnectionTester::class, $mock);

    $this->withSession($session)
        ->post(route('install.process', 2), [
            'db_host'     => '127.0.0.1',
            'db_port'     => '3306',
            'db_database' => 'test_db',
            'db_username' => 'root',
            'db_password' => 'wrong',
        ])
        ->assertSessionHasErrors('db_connection');
});

it('completes step 2 on successful connection to empty database', function (): void {
    $session = ['installer' => ['current_step' => 2, 'completed_steps' => [1], 'data' => []]];

    Process::fake([
        '*config:clear*' => Process::result('', '', 0),
        '*migrate*'      => Process::result('Migration table created.', '', 0),
        '*db:seed*'      => Process::result('Seeding complete.', '', 0),
        '*rules:sync*'   => Process::result('Rules synced.', '', 0),
    ]);

    $pdoMock = Mockery::mock(\PDO::class);

    $mock = Mockery::mock(DatabaseConnectionTester::class);
    $mock->shouldReceive('connect')->andReturn($pdoMock);
    $mock->shouldReceive('tables')->andReturn([]); // empty database
    app()->instance(DatabaseConnectionTester::class, $mock);

    // Also mock EnvWriter to avoid touching real .env
    $envMock = Mockery::mock(EnvWriter::class);
    $envMock->shouldReceive('setMany')->once();
    app()->instance(EnvWriter::class, $envMock);

    $this->withSession($session)
        ->post(route('install.process', 2), [
            'db_host'     => '127.0.0.1',
            'db_port'     => '3306',
            'db_database' => 'federation',
            'db_username' => 'federation',
            'db_password' => 'secret',
        ])
        ->assertRedirectToRoute('install.step', 3);
});

it('prompts for action when existing tables are detected', function (): void {
    $session = ['installer' => ['current_step' => 2, 'completed_steps' => [1], 'data' => []]];

    $pdoMock = Mockery::mock(\PDO::class);

    $mock = Mockery::mock(DatabaseConnectionTester::class);
    $mock->shouldReceive('connect')->andReturn($pdoMock);
    $mock->shouldReceive('tables')->andReturn(['users', 'migrations']); // existing tables
    app()->instance(DatabaseConnectionTester::class, $mock);

    $envMock = Mockery::mock(EnvWriter::class);
    $envMock->shouldReceive('setMany')->never(); // must NOT write before user chooses
    app()->instance(EnvWriter::class, $envMock);

    $this->withSession($session)
        ->post(route('install.process', 2), [
            'db_host'     => '127.0.0.1',
            'db_port'     => '3306',
            'db_database' => 'federation',
            'db_username' => 'federation',
            'db_password' => 'secret',
        ])
        ->assertRedirect()
        ->assertSessionHas('has_existing_tables', true);
});

// ── Step 3 — Mail ─────────────────────────────────────────────────────────────

it('shows step 3 mail page', function (): void {
    $session = ['installer' => ['current_step' => 3, 'completed_steps' => [1, 2], 'data' => []]];

    $this->withSession($session)
        ->get(route('install.step', 3))
        ->assertOk()
        ->assertViewIs('install.steps.3-mail');
});

it('skips mail step and redirects to step 4', function (): void {
    $session = ['installer' => ['current_step' => 3, 'completed_steps' => [1, 2], 'data' => []]];

    $this->withSession($session)
        ->post(route('install.process', 3), ['skip' => '1'])
        ->assertRedirectToRoute('install.step', 4);
});

it('marks mail as skipped in session', function (): void {
    $session = ['installer' => ['current_step' => 3, 'completed_steps' => [1, 2], 'data' => []]];

    $this->withSession($session)
        ->post(route('install.process', 3), ['skip' => '1']);

    expect(session('installer.data.mail_skipped'))->toBeTrue();
});

it('validates mail fields when not skipping', function (): void {
    $session = ['installer' => ['current_step' => 3, 'completed_steps' => [1, 2], 'data' => []]];

    $this->withSession($session)
        ->post(route('install.process', 3), [
            'mail_host'       => '',
            'mail_encryption' => 'tls',
        ])
        ->assertSessionHasErrors(['mail_host', 'mail_from_address', 'mail_from_name']);
});

it('saves mail config and advances to step 4', function (): void {
    $session = ['installer' => ['current_step' => 3, 'completed_steps' => [1, 2], 'data' => []]];

    $envMock = Mockery::mock(EnvWriter::class);
    $envMock->shouldReceive('setMany')->once();
    app()->instance(EnvWriter::class, $envMock);

    $this->withSession($session)
        ->post(route('install.process', 3), [
            'mail_host'         => 'smtp.example.com',
            'mail_port'         => '587',
            'mail_encryption'   => 'tls',
            'mail_from_address' => 'admin@example.com',
            'mail_from_name'    => 'Federation Registry',
        ])
        ->assertRedirectToRoute('install.step', 4);
});

// ── Step 4 — Admin ────────────────────────────────────────────────────────────

it('shows step 4 admin page', function (): void {
    $session = ['installer' => ['current_step' => 4, 'completed_steps' => [1, 2, 3], 'data' => []]];

    $this->withSession($session)
        ->get(route('install.step', 4))
        ->assertOk()
        ->assertViewIs('install.steps.4-admin');
});

it('validates admin account fields', function (): void {
    $session = ['installer' => ['current_step' => 4, 'completed_steps' => [1, 2, 3], 'data' => []]];

    $this->withSession($session)
        ->post(route('install.process', 4), [
            'name'     => '',
            'email'    => 'not-an-email',
            'password' => 'short',
        ])
        ->assertSessionHasErrors(['name', 'email', 'password']);
});

it('creates admin user and redirects to step 5', function (): void {
    $session = ['installer' => ['current_step' => 4, 'completed_steps' => [1, 2, 3], 'data' => []]];

    $this->withSession($session)
        ->post(route('install.process', 4), [
            'name'                  => 'Test Admin',
            'email'                 => 'admin@example.com',
            'password'              => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ])
        ->assertRedirectToRoute('install.step', 5);

    $user = User::where('email', 'admin@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->hasRole('Admin'))->toBeTrue();
    expect($user->status)->toBe('active');
});

it('does not store password in session', function (): void {
    $session = ['installer' => ['current_step' => 4, 'completed_steps' => [1, 2, 3], 'data' => []]];

    $this->withSession($session)
        ->post(route('install.process', 4), [
            'name'                  => 'Test Admin',
            'email'                 => 'admin@example.com',
            'password'              => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ]);

    $state = session('installer');
    expect(json_encode($state))->not->toContain('SecurePassword123!');
});

it('rejects duplicate email for admin account', function (): void {
    User::factory()->create(['email' => 'existing@example.com']);

    $session = ['installer' => ['current_step' => 4, 'completed_steps' => [1, 2, 3], 'data' => []]];

    $this->withSession($session)
        ->post(route('install.process', 4), [
            'name'                  => 'Admin',
            'email'                 => 'existing@example.com',
            'password'              => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ])
        ->assertSessionHasErrors('email');
});

// ── Step 5 — Finish ───────────────────────────────────────────────────────────

it('shows step 5 summary page', function (): void {
    $session = [
        'installer' => [
            'current_step'    => 5,
            'completed_steps' => [1, 2, 3, 4],
            'data'            => [
                'db'          => ['host' => '127.0.0.1', 'database' => 'fed', 'username' => 'fed'],
                'mail_skipped' => false,
                'mail'        => ['host' => 'smtp.example.com', 'port' => 587, 'from_address' => 'a@b.com', 'from_name' => 'Test'],
                'admin'       => ['name' => 'Admin', 'email' => 'admin@example.com'],
            ],
        ],
    ];

    $this->withSession($session)
        ->get(route('install.step', 5))
        ->assertOk()
        ->assertViewIs('install.steps.5-summary');
});

it('writes .installed flag on finish and redirects to complete', function (): void {
    $session = [
        'installer' => [
            'current_step'    => 5,
            'completed_steps' => [1, 2, 3, 4],
            'data'            => ['mail_skipped' => false],
        ],
    ];

    Process::fake([
        '*config:cache*' => Process::result('', '', 0),
        '*route:cache*'  => Process::result('', '', 0),
        '*view:cache*'   => Process::result('', '', 0),
        '*event:cache*'  => Process::result('', '', 0),
    ]);

    $envMock = Mockery::mock(EnvWriter::class);
    $envMock->shouldReceive('get')->with('APP_ENV')->andReturn('local');
    $envMock->shouldReceive('set')->with('APP_ENV', 'production');
    $envMock->shouldReceive('set')->with('APP_DEBUG', 'false');
    app()->instance(EnvWriter::class, $envMock);

    $this->withSession($session)
        ->post(route('install.process', 5))
        ->assertRedirectToRoute('install.complete');

    expect(file_exists(storage_path('app/.installed')))->toBeTrue();

    $content = json_decode(file_get_contents(storage_path('app/.installed')), true);
    expect($content)->toHaveKey('installed_at');
    expect($content)->toHaveKey('version');
});

it('clears installer session data after finish', function (): void {
    $session = [
        'installer' => [
            'current_step'    => 5,
            'completed_steps' => [1, 2, 3, 4],
            'data'            => ['mail_skipped' => false],
        ],
    ];

    Process::fake();

    $envMock = Mockery::mock(EnvWriter::class);
    $envMock->shouldReceive('get')->andReturn('local');
    $envMock->shouldReceive('set')->twice();
    app()->instance(EnvWriter::class, $envMock);

    $this->withSession($session)
        ->post(route('install.process', 5));

    expect(session('installer'))->toBeNull();
});
