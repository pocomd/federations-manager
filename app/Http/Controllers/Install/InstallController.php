<?php

declare(strict_types=1);

namespace App\Http\Controllers\Install;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Installer\DatabaseConnectionTester;
use App\Services\Installer\EnvWriter;
use App\Services\Installer\PhpBinary;
use App\Services\Installer\RequirementsChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;
use Illuminate\View\View;
use PDOException;
use Spatie\Permission\Models\Role;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Mailer as SymfonyMailer;
use Symfony\Component\Mime\Email;
use Throwable;

class InstallController extends Controller
{
    private const TOTAL_STEPS = 6;

    private const INSTALLED_FLAG = 'app/.installed';

    public function __construct(
        private readonly RequirementsChecker    $requirements,
        private readonly DatabaseConnectionTester $dbTester,
        private readonly EnvWriter              $env,
    ) {}

    public function index(Request $request): RedirectResponse
    {
        if ($this->isInstalled()) {
            return $this->alreadyInstalled();
        }

        $state = $this->state($request);
        $step  = $this->earliestIncomplete($state);

        return redirect()->route('install.step', $step);
    }

    public function show(Request $request, int $step): View|RedirectResponse
    {
        if ($this->isInstalled()) {
            return $this->alreadyInstalled();
        }

        abort_if($step < 1 || $step > self::TOTAL_STEPS, 404);

        $state    = $this->state($request);
        $earliest = $this->earliestIncomplete($state);

        if ($step > $earliest) {
            return redirect()->route('install.step', $earliest);
        }

        return match ($step) {
            1 => $this->showRequirements(),
            2 => $this->showSoftHsm(),
            3 => $this->showDatabase($request),
            4 => $this->showMail($request),
            5 => view('install.steps.5-admin'),
            6 => $this->showSummary($request),
        };
    }

    public function process(Request $request, int $step): RedirectResponse|JsonResponse
    {
        if ($this->isInstalled()) {
            return $this->alreadyInstalled();
        }

        abort_if($step < 1 || $step > self::TOTAL_STEPS, 404);

        $state    = $this->state($request);
        $earliest = $this->earliestIncomplete($state);

        if ($step > $earliest) {
            return redirect()->route('install.step', $earliest);
        }

        return match ($step) {
            1 => $this->processRequirements($request),
            2 => $this->processSoftHsm($request),
            3 => $this->processDatabase($request),
            4 => $this->processMail($request),
            5 => $this->processAdmin($request),
            6 => $this->processFinish($request),
        };
    }

    public function complete(Request $request): View|RedirectResponse
    {
        if (! $this->isInstalled()) {
            $target = auth()->check() ? route('dashboard') : route('login');

            return redirect($target)->with('warning', 'Installation has not been completed yet.');
        }

        $mailSkipped = $request->session()->pull('install_mail_skipped', false);

        return view('install.complete', compact('mailSkipped'));
    }

    private function alreadyInstalled(): RedirectResponse
    {
        $target = auth()->check() ? route('dashboard') : route('login');

        return redirect($target)->with('info', 'The application is already installed.');
    }

    private function showRequirements(): View
    {
        $checks     = $this->requirements->run();
        $canProceed = $this->requirements->allRequiredPass($checks);

        return view('install.steps.1-requirements', compact('checks', 'canProceed'));
    }

    private function processRequirements(Request $request): RedirectResponse
    {
        $checks = $this->requirements->run();

        if (! $this->requirements->allRequiredPass($checks)) {
            return redirect()->route('install.step', 1)
                ->withErrors(['requirements' => 'One or more required checks failed. Please resolve them before continuing.']);
        }

        $this->markComplete($request, 1);

        return redirect()->route('install.step', 2);
    }

    private function showSoftHsm(): View
    {
        $softhsmUtil   = trim(shell_exec('which softhsm2-util 2>/dev/null') ?? '');
        $pkcs11Tool    = trim(shell_exec('which pkcs11-tool 2>/dev/null') ?? '');
        $softhsmAvailable = $softhsmUtil && is_executable($softhsmUtil)
            && $pkcs11Tool && is_executable($pkcs11Tool);

        return view('install.steps.2-softhsm', compact('softhsmAvailable', 'softhsmUtil', 'pkcs11Tool'));
    }

    private function processSoftHsm(Request $request): RedirectResponse
    {
        $enable = $request->boolean('enable_softhsm');

        $this->env->setMany([
            'FILE_SIGNING_IS_ACTIVE'    => 'true',
            'SOFTHSM_SIGNING_IS_ACTIVE' => $enable ? 'true' : 'false',
        ]);

        $this->markComplete($request, 2, ['softhsm_enabled' => $enable]);

        return redirect()->route('install.step', 3);
    }

    private function showDatabase(Request $request): View
    {
        $state   = $this->state($request);
        $session = $request->session()->get('installer.data.db', []);

        $fromEnv = [
            'host'     => $this->env->get('DB_HOST'),
            'port'     => $this->env->get('DB_PORT'),
            'database' => $this->env->get('DB_DATABASE'),
            'username' => $this->env->get('DB_USERNAME'),
        ];

        $existing = array_merge(
            array_filter($fromEnv),
            $session,
            ['app_url' => $state['data']['app_url'] ?? null],
        );

        $preConfigured = ! empty($fromEnv['host'])
            && ! empty($fromEnv['database'])
            && ! empty($fromEnv['username']);

        return view('install.steps.3-database', compact('existing', 'preConfigured'));
    }

    private function processDatabaseKeep(Request $request): RedirectResponse
    {
        $data = $request->validate(['app_url' => ['required', 'url', 'max:255']]);

        $host     = $this->env->get('DB_HOST') ?? '';
        $port     = (int) ($this->env->get('DB_PORT') ?? 3306);
        $database = $this->env->get('DB_DATABASE') ?? '';
        $username = $this->env->get('DB_USERNAME') ?? '';
        $password = $this->env->get('DB_PASSWORD') ?? '';

        try {
            $pdo = $this->dbTester->connect($host, $port, $database, $username, $password);
        } catch (PDOException $e) {
            return back()->withErrors(['db_connection' => 'Connection failed: ' . $e->getMessage()]);
        }

        $this->env->setMany(['APP_URL' => rtrim($data['app_url'], '/')]);
        Process::run([PhpBinary::find(), base_path('artisan'), 'config:clear', '--no-ansi']);

        $tables     = $this->dbTester->tables($pdo);
        $hasExisting = count($tables) > 0;
        $subAction   = $request->input('sub_action');

        if ($hasExisting && $subAction === null) {
            return back()->withInput()->with('has_existing_tables', true);
        }

        $migrateCmd = match ($subAction) {
            'fresh' => [PhpBinary::find(), base_path('artisan'), 'migrate:fresh', '--force', '--no-ansi'],
            'sync'  => [PhpBinary::find(), base_path('artisan'), 'migrate:sync-existing', '--force', '--no-ansi'],
            default => [PhpBinary::find(), base_path('artisan'), 'migrate', '--force', '--no-ansi'],
        };

        $migrate = Process::run($migrateCmd);

        if (! $migrate->successful()) {
            return back()->withErrors(['migrate' => $migrate->output() ?: $migrate->errorOutput()]);
        }

        if (! $hasExisting || $subAction === 'fresh') {
            $seed = Process::run([PhpBinary::find(), base_path('artisan'), 'db:seed', '--force', '--no-ansi']);
            if (! $seed->successful()) {
                return back()->withErrors(['seed' => $seed->output() ?: $seed->errorOutput()]);
            }
            Process::run([PhpBinary::find(), base_path('artisan'), 'rules:sync', '--no-ansi']);
        }

        $this->markComplete($request, 3, [
            'app_url' => rtrim($data['app_url'], '/'),
            'db'      => ['host' => $host, 'database' => $database, 'username' => $username],
        ]);

        return redirect()->route('install.step', 4);
    }

    private function processDatabase(Request $request): RedirectResponse
    {
        if ($request->input('action') === 'keep') {
            return $this->processDatabaseKeep($request);
        }

        $data = $request->validate([
            'app_url'     => ['required', 'url', 'max:255'],
            'db_host'     => ['required', 'string', 'max:255'],
            'db_port'     => ['required', 'integer', 'min:1', 'max:65535'],
            'db_database' => ['required', 'string', 'max:255'],
            'db_username' => ['required', 'string', 'max:255'],
            'db_password' => ['nullable', 'string', 'max:255'],
            'db_prefix'   => ['nullable', 'string', 'max:20'],
            'action'      => ['nullable', 'string', 'in:fresh,sync'],
        ]);

        // Test raw PDO connection before touching .env
        try {
            $pdo = $this->dbTester->connect(
                $data['db_host'],
                (int) $data['db_port'],
                $data['db_database'],
                $data['db_username'],
                $data['db_password'] ?? '',
            );
        } catch (PDOException $e) {
            return back()->withInput()
                ->withErrors(['db_connection' => 'Connection failed: ' . $e->getMessage()]);
        }

        // Detect existing tables
        $tables     = $this->dbTester->tables($pdo);
        $hasExisting = count($tables) > 0;
        $action      = $data['action'] ?? null;

        if ($hasExisting && $action === null) {
            return back()->withInput()->with('has_existing_tables', true);
        }

        // Write app URL and DB config to .env
        $this->env->setMany([
            'APP_URL'       => rtrim($data['app_url'], '/'),
            'DB_CONNECTION' => 'mysql',
            'DB_HOST'       => $data['db_host'],
            'DB_PORT'       => (string) $data['db_port'],
            'DB_DATABASE'   => $data['db_database'],
            'DB_USERNAME'   => $data['db_username'],
            'DB_PASSWORD'   => $data['db_password'] ?? '',
            'DB_PREFIX'     => $data['db_prefix'] ?? '',
        ]);

        // Clear any cached config so artisan subprocesses read the fresh .env
        Process::run([PhpBinary::find(), base_path('artisan'), 'config:clear', '--no-ansi']);

        // Migrations
        $migrateCmd = match ($action) {
            'fresh' => [PhpBinary::find(), base_path('artisan'), 'migrate:fresh', '--force', '--no-ansi'],
            'sync'  => [PhpBinary::find(), base_path('artisan'), 'migrate:sync-existing', '--force', '--no-ansi'],
            default => [PhpBinary::find(), base_path('artisan'), 'migrate', '--force', '--no-ansi'],
        };

        $migrate = Process::run($migrateCmd);

        if (! $migrate->successful()) {
            return back()->withInput()
                ->withErrors(['migrate' => $migrate->output() ?: $migrate->errorOutput()]);
        }

        // Seed — always on fresh DB or sync/migrate over existing skips it
        if ($action !== 'sync') {
            $seed = Process::run([PhpBinary::find(), base_path('artisan'), 'db:seed', '--force', '--no-ansi']);

            if (! $seed->successful()) {
                return back()->withInput()
                    ->withErrors(['seed' => $seed->output() ?: $seed->errorOutput()]);
            }

            Process::run([PhpBinary::find(), base_path('artisan'), 'rules:sync', '--no-ansi']);
        }

        $this->markComplete($request, 3, [
            'app_url' => rtrim($data['app_url'], '/'),
            'db'      => [
                'host'     => $data['db_host'],
                'database' => $data['db_database'],
                'username' => $data['db_username'],
            ],
        ]);

        return redirect()->route('install.step', 4);
    }

    private function showMail(Request $request): View
    {
        $existing = $request->session()->get('installer.data.mail', []);

        return view('install.steps.4-mail', compact('existing'));
    }

    private function processMail(Request $request): RedirectResponse|JsonResponse
    {
        // AJAX mail test — return JSON, do not advance step
        if ($request->input('_action') === 'test') {
            return $this->processMailTest($request);
        }

        // Skip
        if ($request->boolean('skip')) {
            $this->markComplete($request, 4, ['mail_skipped' => true]);
            return redirect()->route('install.step', 5);
        }

        $data = $request->validate([
            'mail_host'         => ['required', 'string', 'max:255'],
            'mail_port'         => ['required', 'integer', 'min:1', 'max:65535'],
            'mail_username'     => ['nullable', 'string', 'max:255'],
            'mail_password'     => ['nullable', 'string', 'max:255'],
            'mail_encryption'   => ['required', 'string', 'in:tls,ssl,none'],
            'mail_from_address' => ['required', 'email', 'max:255'],
            'mail_from_name'    => ['required', 'string', 'max:255'],
        ]);

        $encryption = $data['mail_encryption'] !== 'none' ? $data['mail_encryption'] : '';

        $this->env->setMany([
            'MAIL_MAILER'       => 'smtp',
            'MAIL_HOST'         => $data['mail_host'],
            'MAIL_PORT'         => (string) $data['mail_port'],
            'MAIL_USERNAME'     => $data['mail_username'] ?? '',
            'MAIL_PASSWORD'     => $data['mail_password'] ?? '',
            'MAIL_ENCRYPTION'   => $encryption,
            'MAIL_FROM_ADDRESS' => $data['mail_from_address'],
            'MAIL_FROM_NAME'    => $data['mail_from_name'],
        ]);

        $this->markComplete($request, 4, [
            'mail_skipped'      => false,
            'mail' => [
                'host'         => $data['mail_host'],
                'port'         => $data['mail_port'],
                'from_address' => $data['mail_from_address'],
                'from_name'    => $data['mail_from_name'],
            ],
        ]);

        return redirect()->route('install.step', 5);
    }

    private function processMailTest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mail_host'         => ['required', 'string'],
            'mail_port'         => ['required', 'integer'],
            'mail_username'     => ['nullable', 'string'],
            'mail_password'     => ['nullable', 'string'],
            'mail_encryption'   => ['required', 'string', 'in:tls,ssl,none'],
            'mail_from_address' => ['required', 'email'],
            'mail_from_name'    => ['required', 'string'],
            'test_email'        => ['required', 'email'],
        ]);

        try {
            $tls       = $data['mail_encryption'] === 'ssl';
            $transport = new EsmtpTransport($data['mail_host'], (int) $data['mail_port'], $tls);

            if (! empty($data['mail_username'])) {
                $transport->setUsername($data['mail_username']);
                $transport->setPassword($data['mail_password'] ?? '');
            }

            $mailer = new SymfonyMailer($transport);

            $email = (new Email())
                ->from($data['mail_from_address'])
                ->to($data['test_email'])
                ->subject('Federation Registry — Test Email')
                ->text(
                    "This is a test email from your Federation Registry installer.\n\n" .
                    "If you received this, your SMTP configuration is working correctly."
                );

            $mailer->send($email);

            return response()->json(['success' => true, 'message' => 'Test email sent successfully.']);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function processAdmin(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'                  => ['required', 'string', 'max:255'],
            'email'                 => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'              => ['required', 'string', 'min:12', 'confirmed'],
            'password_confirmation' => ['required'],
        ]);

        $role = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => $data['password'],
            'status'   => 'active',
        ]);

        $user->assignRole($role);

        // Never store the password in session
        $this->markComplete($request, 5, [
            'admin' => [
                'name'  => $data['name'],
                'email' => $data['email'],
            ],
        ]);

        return redirect()->route('install.step', 6);
    }

    private function showSummary(Request $request): View
    {
        $state = $this->state($request);

        return view('install.steps.6-summary', [
            'appUrl'      => $state['data']['app_url'] ?? null,
            'db'          => $state['data']['db'] ?? [],
            'mail'        => $state['data']['mail'] ?? [],
            'mailSkipped' => $state['data']['mail_skipped'] ?? false,
            'admin'       => $state['data']['admin'] ?? [],
        ]);
    }

    private function processFinish(Request $request): RedirectResponse
    {
        // Set production defaults if still on local
        if ($this->env->get('APP_ENV') === 'local') {
            $this->env->set('APP_ENV', 'production');
        }
        $this->env->setMany([
            'APP_DEBUG'     => 'false',
            'APP_INSTALLED' => 'true',
        ]);

        // Build caches (failures are non-fatal — app still works without them)
        foreach (['config:cache', 'route:cache', 'view:cache', 'event:cache'] as $cmd) {
            Process::run([PhpBinary::find(), base_path('artisan'), $cmd, '--no-ansi']);
        }

        // Write the installed flag
        if (! is_dir(storage_path('app'))) {
            mkdir(storage_path('app'), 0755, true);
        }

        file_put_contents(storage_path(self::INSTALLED_FLAG), json_encode([
            'installed_at' => now()->toISOString(),
            'version'      => app()->version(),
        ]));

        $mailSkipped = $this->state($request)['data']['mail_skipped'] ?? false;

        $request->session()->forget('installer');
        $request->session()->put('install_mail_skipped', $mailSkipped);

        return redirect()->route('install.complete');
    }

    /** @return array{current_step: int, completed_steps: int[], data: array<string, mixed>} */
    private function state(Request $request): array
    {
        return $request->session()->get('installer', $this->initialState());
    }

    private function initialState(): array
    {
        return [
            'current_step'    => 1,
            'completed_steps' => [],
            'data'            => [],
        ];
    }

    private function earliestIncomplete(array $state): int
    {
        for ($step = 1; $step <= self::TOTAL_STEPS; $step++) {
            if (! in_array($step, $state['completed_steps'], true)) {
                return $step;
            }
        }

        return self::TOTAL_STEPS;
    }

    private function isInstalled(): bool
    {
        $envValue = env('APP_INSTALLED');
        if ($envValue !== null) {
            return (bool) $envValue;
        }

        return file_exists(storage_path(self::INSTALLED_FLAG));
    }

    private function markComplete(Request $request, int $step, array $data = []): void
    {
        $state = $this->state($request);

        if (! in_array($step, $state['completed_steps'], true)) {
            $state['completed_steps'][] = $step;
        }

        $state['current_step'] = $step + 1;
        $state['data']         = array_merge($state['data'], $data);

        $request->session()->put('installer', $state);
    }
}
