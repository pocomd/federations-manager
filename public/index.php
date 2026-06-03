<?php
declare(strict_types=1);

/**
 * Pre-installer entry point.
 *
 * Serves a browser-based requirements checker and guided installer after
 * `git clone`. When all checks pass and the user clicks "Begin Installation"
 * it streams composer / npm / artisan output via SSE, then replaces this file
 * with the real Laravel entry point (bootstrap/app_index.php) and redirects
 * to /install.
 *
 * Zero external dependencies — pure PHP only.
 */

// Allow tests to define ROOT before requiring this file so the functions
// operate on a controlled temp directory instead of the real project tree.
// INSTALLER_TESTING also suppresses the top-level routing/render code.
if (!defined('ROOT')) {
    define('ROOT', dirname(__DIR__));
}

if (!defined('INSTALLER_TESTING')) {

    if (
        file_exists(ROOT . '/storage/app/.installed') ||
        file_exists(ROOT . '/storage/app/.pre_install_complete')
    ) {
        $real = ROOT . '/bootstrap/app_index.php';
        file_exists($real) ? require $real : header('Location: /');
        exit;
    }

    if (($_GET['action'] ?? '') === 'stream') {
        handleInstallStream();
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'savedb') {
        handleSaveDb();
        exit;
    }

    $checks  = runAllChecks();
    $allPass = array_reduce($checks, fn($c, $i) => $c && $i['pass'], true);

    renderPage($checks, $allPass);
    exit;

}

// =============================================================================
// CHECKS
// Each check returns:
//   id    — unique identifier
//   title — displayed label
//   value — one-line status shown on the right
//   pass  — bool
//   fix   — string|null  instructions shown when pass === false
// =============================================================================

function runAllChecks(): array
{
    return array_filter([


        checkPhpVersion(),
        checkExtensions(),

        // exec() / proc_open() are required to shell out to composer / npm /
        // artisan. Some shared hosts disable these — nothing we can do from
        // inside PHP if they are blocked.
        checkExecAvailable(),

        //
        // All paths that any install step writes to must be owned (or at least
        // group-writable) by the web-server user. The safest approach after
        // `git clone` is:
        //   sudo chown -R www-data:www-data /var/www/federations-manager   # Ubuntu
        //   sudo chown -R nginx:nginx        /var/www/federations-manager   # CentOS
        //
        // Individual path checks are listed below so the operator can see
        // exactly which directories are the problem.

        // Project root must be writable so we can copy .env.example → .env
        // (and so composer / npm can create vendor/ and node_modules/ when
        // they don't exist yet).
        checkWritable('Project root', ROOT),

        // vendor/ — composer install writes / removes packages here.
        // Only checked if the directory already exists (e.g. a previous
        // partial install); if absent, writability of ROOT is enough.
        checkWritableIfExists('vendor/', ROOT . '/vendor'),

        // node_modules/ — npm ci writes here.
        // Same rule as vendor/: only checked when the directory is present.
        checkWritableIfExists('node_modules/', ROOT . '/node_modules'),

        // public/build/ — `npm run build` (Vite) writes compiled assets here.
        // Only checked when the directory already exists.
        checkWritableIfExists('public/build/', ROOT . '/public/build'),

        // public/ — two steps write here:
        //   1. `php artisan storage:link` creates public/storage symlink
        //   2. Final step copies bootstrap/app_index.php → public/index.php
        checkWritable('public/', ROOT . '/public'),

        // storage/ — Laravel writes cache, logs, sessions, uploaded signing
        // keys, and the .installed flag here.
        checkWritable('storage/', ROOT . '/storage'),

        // bootstrap/cache/ — `php artisan config:cache` and friends write
        // compiled bootstrap files here. Also needed during queue boot.
        checkWritable('bootstrap/cache/', ROOT . '/bootstrap/cache'),

        // .env — dedicated check: writable + not world-readable (contains secrets).
        checkEnvPermissions(),


        // The real Laravel entry point must be present in bootstrap/.
        // If this is missing the git clone was incomplete.
        checkAppIndex(),


        // composer — PHP dependency manager. Must be in PATH for www-data.
        checkBinary(
            'Composer',
            ['composer', '/usr/local/bin/composer', '/usr/bin/composer'],
            "curl -sS https://getcomposer.org/installer | php\n" .
            "sudo mv composer.phar /usr/local/bin/composer\n" .
            "composer --version"
        ),

        // node — JavaScript runtime required by npm and Vite.
        checkBinary(
            'Node.js',
            ['node', '/usr/bin/node', '/usr/local/bin/node'],
            "# Ubuntu:\ncurl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -\n" .
            "sudo apt install -y nodejs\n\n" .
            "# CentOS:\ncurl -fsSL https://rpm.nodesource.com/setup_22.x | sudo bash -\n" .
            "sudo dnf install -y nodejs"
        ),

        // npm — Node.js package manager. Bundled with Node.js; missing npm
        // usually means a broken Node.js installation.
        checkBinary(
            'npm',
            ['npm', '/usr/bin/npm', '/usr/local/bin/npm'],
            "npm is bundled with Node.js — reinstall Node.js to restore npm.\n\n" .
            "# Ubuntu:\nsudo apt remove nodejs && sudo apt install -y nodejs\n\n" .
            "# CentOS:\nsudo dnf reinstall nodejs"
        ),

    ]);
}


function checkPhpVersion(): array
{
    $required = '8.3.0';
    $pass     = version_compare(PHP_VERSION, $required, '>=');

    return [
        'id'     => 'php_version',
        'title'  => 'PHP Version',
        'value'  => PHP_VERSION . ($pass ? '' : "  (need ≥ {$required})"),
        'pass'   => $pass,
        'warn'   => false,
        'advice' => null,
        'fix'    => $pass ? null :
            "# Ubuntu 22.04 / 24.04\n" .
            "sudo add-apt-repository ppa:ondrej/php -y && sudo apt update\n" .
            "sudo apt install -y php8.4 php8.4-fpm php8.4-cli php8.4-mysql \\\n" .
            "  php8.4-redis php8.4-xml php8.4-curl php8.4-mbstring \\\n" .
            "  php8.4-zip php8.4-bcmath php8.4-dom\n\n" .
            "# CentOS / RHEL 9\n" .
            "sudo dnf install -y https://rpms.remirepo.net/enterprise/remi-release-9.rpm\n" .
            "sudo dnf module enable php:remi-8.4 -y\n" .
            "sudo dnf install -y php php-fpm php-cli php-mysqlnd php-redis \\\n" .
            "  php-xml php-curl php-mbstring php-zip php-bcmath php-dom",
    ];
}

function checkExtensions(): array
{
    $required = [
        'pdo_mysql', 'openssl', 'mbstring', 'xml', 'dom',
        'simplexml', 'curl', 'zip', 'bcmath', 'tokenizer',
        'ctype', 'fileinfo', 'json',
    ];
    $missing = array_values(array_filter($required, fn($e) => !extension_loaded($e)));

    // phpredis is the recommended Redis client (configured in .env).
    // The app also accepts predis (pure PHP) but phpredis is faster.
    if (!extension_loaded('redis')) {
        $missing[] = 'redis (phpredis)';
    }

    $pass = empty($missing);
    $list = implode(', ', $missing);

    return [
        'id'     => 'extensions',
        'title'  => 'PHP Extensions',
        'value'  => $pass ? 'All required extensions loaded' : "Missing: {$list}",
        'pass'   => $pass,
        'warn'   => false,
        'advice' => null,
        'fix'    => $pass ? null :
            "Missing: {$list}\n\n" .
            "# Ubuntu — install each missing extension:\n" .
            "sudo apt install -y " .
            implode(' ', array_map(fn($e) => 'php8.4-' . explode(' ', $e)[0], $missing)) . "\n" .
            "sudo systemctl restart php8.4-fpm\n\n" .
            "# CentOS:\n" .
            "sudo dnf install -y " .
            implode(' ', array_map(fn($e) => 'php-' . explode(' ', $e)[0], $missing)) . "\n" .
            "sudo systemctl restart php-fpm",
    ];
}

function checkExecAvailable(): array
{
    $disabled = array_map('trim', explode(',', ini_get('disable_functions') ?: ''));
    $needed   = ['exec', 'proc_open', 'proc_close'];
    $blocked  = array_values(array_intersect($needed, $disabled));
    $pass     = empty($blocked);

    return [
        'id'     => 'exec',
        'title'  => 'Shell Execution',
        'value'  => $pass ? 'exec() and proc_open() available' : 'Blocked: ' . implode(', ', $blocked),
        'pass'   => $pass,
        'warn'   => false,
        'advice' => null,
        'fix'    => $pass ? null :
            "The following functions are listed in disable_functions in php.ini:\n" .
            implode(', ', $blocked) . "\n\n" .
            "Find the active php.ini:\n" .
            "  php --ini\n\n" .
            "Edit the file and remove these names from the disable_functions line, then:\n" .
            "  sudo systemctl restart php8.4-fpm   # Ubuntu\n" .
            "  sudo systemctl restart php-fpm       # CentOS",
    ];
}

/**
 * Check that a path exists and is writable. Also warns when permissions are
 * world-writable (anyone on the server can modify the files).
 */
function checkWritable(string $label, string $path): array
{
    $id     = 'writable_' . preg_replace('/\W+/', '_', $label);
    $exists = file_exists($path);

    if (!$exists || !is_writable($path)) {
        $proc  = getCurrentUser();
        $mode  = $exists ? getPathMode($path) : null;
        $owner = $exists ? getPathOwner($path) : null;
        $meta  = buildMeta($mode, $owner, $proc);

        return [
            'id'     => $id,
            'title'  => "{$label} writable",
            'value'  => !$exists ? 'Directory missing' : 'Not writable' . ($meta ? " ({$meta})" : ''),
            'pass'   => false,
            'warn'   => false,
            'fix'    => writableFix($path),
            'advice' => null,
        ];
    }

    return writableOkResult($id, "{$label} writable", $path, isDir: is_dir($path));
}

/**
 * Same but skips when the path does not exist — used for vendor/, node_modules/,
 * public/build/ which may not be present on a fresh clone.
 */
function checkWritableIfExists(string $label, string $path): ?array
{
    if (!file_exists($path)) {
        return null;
    }

    $id = 'writable_' . preg_replace('/\W+/', '_', $label);

    if (!is_writable($path)) {
        $proc  = getCurrentUser();
        $mode  = getPathMode($path);
        $owner = getPathOwner($path);
        $meta  = buildMeta($mode, $owner, $proc);

        return [
            'id'     => $id,
            'title'  => "{$label} writable",
            'value'  => 'Not writable' . ($meta ? " ({$meta})" : ''),
            'pass'   => false,
            'warn'   => false,
            'fix'    => writableFix($path),
            'advice' => null,
        ];
    }

    return writableOkResult($id, "{$label} writable", $path, isDir: is_dir($path));
}

/**
 * Dedicated check for .env: writable + not world-readable (it contains secrets).
 * Skipped entirely when .env does not exist yet.
 */
function checkEnvPermissions(): ?array
{
    $path = ROOT . '/.env';
    if (!file_exists($path)) {
        return null;
    }

    $id    = 'env_permissions';
    $mode  = getPathMode($path);
    $owner = getPathOwner($path);
    $proc  = getCurrentUser();
    $meta  = buildMeta($mode, $owner, $proc);

    if (!is_writable($path)) {
        return [
            'id'     => $id,
            'title'  => '.env',
            'value'  => 'Not writable' . ($meta ? " ({$meta})" : ''),
            'pass'   => false,
            'warn'   => false,
            'fix'    => "sudo chown www-data:www-data " . ROOT . "/.env\nsudo chmod 640 " . ROOT . "/.env",
            'advice' => null,
        ];
    }

    $perms         = (int) @fileperms($path);
    $worldWritable = ($perms & 0002) !== 0;
    $worldReadable = ($perms & 0004) !== 0;

    $issues = [];
    if ($worldWritable) $issues[] = 'world-writable';
    if ($worldReadable) $issues[] = 'world-readable';

    if ($issues) {
        return [
            'id'     => $id,
            'title'  => '.env',
            'value'  => 'Writable' . ($meta ? " ({$meta})" : '') . ' — ' . implode(', ', $issues),
            'pass'   => true,
            'warn'   => true,
            'fix'    => null,
            'advice' => ".env contains secrets (APP_KEY, DB password, …) and should only\n" .
                        "be readable by the web-server user.\n\n" .
                        "sudo chown www-data:www-data " . ROOT . "/.env\n" .
                        "sudo chmod 640 " . ROOT . "/.env   # owner rw, group r, world none",
        ];
    }

    return [
        'id'     => $id,
        'title'  => '.env',
        'value'  => 'Writable' . ($meta ? " ({$meta})" : ''),
        'pass'   => true,
        'warn'   => false,
        'fix'    => null,
        'advice' => null,
    ];
}


function getPathOwner(string $path): ?string
{
    if (function_exists('posix_getpwuid') && function_exists('fileowner')) {
        $uid  = @fileowner($path);
        if ($uid === false) return null;
        $info = @posix_getpwuid($uid);
        return $info !== false ? $info['name'] : (string) $uid;
    }
    $disabled = array_map('trim', explode(',', ini_get('disable_functions') ?: ''));
    if (!in_array('shell_exec', $disabled, true)) {
        $out = @shell_exec('stat -c %U ' . escapeshellarg($path) . ' 2>/dev/null');
        if ($out && ($name = trim($out)) !== '') return $name;
    }
    return null;
}

function getPathMode(string $path): ?string
{
    if (!file_exists($path)) return null;
    $perms = fileperms($path);
    return $perms !== false ? sprintf('%04o', $perms & 0777) : null;
}

function getCurrentUser(): ?string
{
    if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
        $info = @posix_getpwuid(posix_geteuid());
        return $info !== false ? $info['name'] : (string) posix_geteuid();
    }
    $disabled = array_map('trim', explode(',', ini_get('disable_functions') ?: ''));
    if (!in_array('shell_exec', $disabled, true)) {
        $out = @shell_exec('whoami 2>/dev/null');
        if ($out && ($name = trim($out)) !== '') return $name;
    }
    return null;
}

/** Build the parenthetical meta string shown in check values. */
function buildMeta(?string $mode, ?string $owner, ?string $proc): string
{
    $parts = [];
    if ($mode)  $parts[] = "mode: {$mode}";
    if ($owner) $parts[] = "owner: {$owner}";
    if ($proc)  $parts[] = "running as: {$proc}";
    return implode(', ', $parts);
}

/** Shared result builder for a path that is writable — checks world-writable. */
function writableOkResult(string $id, string $title, string $path, bool $isDir): array
{
    $mode  = getPathMode($path);
    $owner = getPathOwner($path);
    $meta  = buildMeta($mode, $owner, null); // omit process user in ok state

    $perms         = (int) @fileperms($path);
    $worldWritable = ($perms & 0002) !== 0;

    $value = 'Writable' . ($meta ? " ({$meta})" : '');

    if ($worldWritable) {
        $recommended = $isDir ? '775' : '664';
        return [
            'id'     => $id,
            'title'  => $title,
            'value'  => $value . ' — world-writable',
            'pass'   => true,
            'warn'   => true,
            'fix'    => null,
            'advice' => "This path is world-writable (mode {$mode}): any process on this\n" .
                        "server can modify its contents.\n\n" .
                        "sudo chown www-data:www-data {$path}\n" .
                        "sudo chmod {$recommended} {$path}",
        ];
    }

    return [
        'id'     => $id,
        'title'  => $title,
        'value'  => $value,
        'pass'   => true,
        'warn'   => false,
        'fix'    => null,
        'advice' => null,
    ];
}

/** Standard fix text for a non-writable path. */
function writableFix(string $path): string
{
    $root = ROOT;
    return
        "# Ubuntu (web user = www-data) — run once to fix the whole project:\n" .
        "sudo chown -R www-data:www-data {$root}\n" .
        "sudo find {$root} -type d -exec chmod 755 {} \\;\n" .
        "sudo find {$root} -type f -exec chmod 644 {} \\;\n" .
        "sudo chmod 775 {$root}/storage\n" .
        "sudo chmod 775 {$root}/bootstrap/cache\n\n" .
        "# CentOS (web user = nginx):\n" .
        "sudo chown -R nginx:nginx {$root}\n" .
        "sudo find {$root} -type d -exec chmod 755 {} \\;\n" .
        "sudo find {$root} -type f -exec chmod 644 {} \\;\n" .
        "sudo chmod 775 {$root}/storage\n" .
        "sudo chmod 775 {$root}/bootstrap/cache";
}

function checkAppIndex(): array
{
    $path = ROOT . '/bootstrap/app_index.php';
    $pass = file_exists($path);

    return [
        'id'     => 'app_index',
        'title'  => 'bootstrap/app_index.php',
        'value'  => $pass ? 'Found' : 'Not found — re-clone the repository',
        'pass'   => $pass,
        'warn'   => false,
        'advice' => null,
        'fix'    => $pass ? null :
            "The file bootstrap/app_index.php is missing.\n" .
            "This is the real Laravel entry point and must be present in the repository.\n\n" .
            "Re-clone the repository:\n" .
            "  git clone <repository-url> /var/www/federations-manager",
    ];
}

function checkBinary(string $label, array $candidates, string $instructions): array
{
    $found = findBinary($candidates);

    return [
        'id'     => 'binary_' . preg_replace('/\W+/', '_', strtolower($label)),
        'title'  => $label,
        'value'  => $found ?? 'Not found in PATH',
        'pass'   => $found !== null,
        'warn'   => false,
        'advice' => null,
        'fix'    => $found ? null : $instructions,
    ];
}

function findBinary(array $candidates): ?string
{
    // Check known absolute paths first (no shell needed).
    foreach ($candidates as $bin) {
        if (str_contains($bin, '/') && is_executable($bin)) {
            return $bin;
        }
    }

    // Fall back to `which` if shell_exec is available.
    $disabled = array_map('trim', explode(',', ini_get('disable_functions') ?: ''));
    if (!in_array('shell_exec', $disabled, true)) {
        foreach ($candidates as $bin) {
            $found = @shell_exec('which ' . escapeshellarg($bin) . ' 2>/dev/null');
            if ($found && ($path = trim($found)) !== '') {
                return $path;
            }
        }
    }

    return null;
}

// =============================================================================
// INSTALL STREAM  (SSE — text/event-stream)
// =============================================================================

function handleInstallStream(): void
{
    while (ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no'); // disable Nginx response buffering

    set_time_limit(600); // 10 minutes — npm build can be slow

    // Build an environment that works for www-data:
    //   - Rich PATH so composer / node / npm / php are findable
    //   - Writable HOME so composer and npm can write their caches
    //   - COMPOSER_MEMORY_LIMIT=-1 avoids OOM kills on large dependency trees
    //   - NO_COLOR / CI suppress ANSI escape codes in logged output
    $env = array_merge($_ENV, [
        'PATH'                     => '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
        'HOME'                     => sys_get_temp_dir(),
        'COMPOSER_HOME'            => sys_get_temp_dir() . '/composer',
        'COMPOSER_CACHE_DIR'       => sys_get_temp_dir() . '/composer-cache',
        'COMPOSER_NO_INTERACTION'  => '1',
        'COMPOSER_ALLOW_SUPERUSER' => '1',
        'COMPOSER_MEMORY_LIMIT'    => '-1',
        'npm_config_cache'         => sys_get_temp_dir() . '/npm-cache',
        'npm_config_update_notifier' => 'false',
        'CI'                       => 'true',
        'NO_COLOR'                 => '1',
        'FORCE_COLOR'              => '0',
    ]);

    // Must happen before artisan commands — Laravel won't boot without it.
    sse('step', 'Copying .env.example → .env');
    if (!file_exists(ROOT . '/.env')) {
        if (!copy(ROOT . '/.env.example', ROOT . '/.env')) {
            sse('error', 'Could not create .env — check that ' . ROOT . ' is writable by the web server user');
            sse('done', 'FAILED');
            return;
        }
        sse('ok', '.env created');
    } else {
        sse('info', '.env already exists — skipping copy');
    }

    // --no-dev  : skips dev-only packages (PHPUnit, Pest, etc.)
    // --optimize-autoloader : generates a faster classmap for production
    //
    // Common failure: vendor/ owned by a different user from a previous
    // install. Fix: sudo chown -R www-data:www-data /path/to/vendor
    sse('step', 'Installing PHP dependencies (composer install --no-dev)');
    $exit = runStreaming('composer install --no-dev --optimize-autoloader', ROOT, $env);
    if ($exit !== 0) {
        sse('error', 'composer install failed — see output above');
        sse('error', 'Common cause: vendor/ is owned by another user. Run: sudo chown -R www-data:www-data ' . ROOT . '/vendor');
        sse('done', 'FAILED');
        return;
    }
    sse('ok', 'PHP dependencies installed');

    // `npm ci` does a clean install from package-lock.json (faster and more
    // reproducible than `npm install`). Requires node_modules/ to be writable.
    //
    // Common failure: node_modules/ owned by a different user.
    // Fix: sudo chown -R www-data:www-data /path/to/node_modules
    sse('step', 'Installing Node.js dependencies (npm ci)');
    $exit = runStreaming('npm ci', ROOT, $env);
    if ($exit !== 0) {
        sse('error', 'npm ci failed — see output above');
        sse('error', 'Common cause: node_modules/ is owned by another user. Run: sudo chown -R www-data:www-data ' . ROOT . '/node_modules');
        sse('done', 'FAILED');
        return;
    }
    sse('ok', 'Node.js dependencies installed');

    // Vite compiles and bundles all frontend assets into public/build/.
    // This is the slowest step — can take 1–3 minutes on a slow server.
    //
    // Common failure: public/build/ owned by another user, or out of memory.
    // Increase Node.js heap if needed: NODE_OPTIONS=--max-old-space-size=2048
    sse('step', 'Building frontend assets (npm run build)');
    $exit = runStreaming('npm run build', ROOT, $env);
    if ($exit !== 0) {
        sse('error', 'npm run build failed — see output above');
        sse('error', 'Common cause: public/build/ is owned by another user or Node.js ran out of memory');
        sse('done', 'FAILED');
        return;
    }
    sse('ok', 'Frontend assets built');

    // Writes APP_KEY into .env. Required for encryption, sessions and cookies.
    // --force overwrites any existing key (safe on a fresh install).
    sse('step', 'Generating application key (php artisan key:generate)');
    $exit = runStreaming('php artisan key:generate --force', ROOT, $env);
    if ($exit !== 0) {
        sse('error', 'key:generate failed — check that .env is writable by the web server user');
        sse('done', 'FAILED');
        return;
    }
    sse('ok', 'Application key written to .env');

    // Creates public/storage → storage/app/public symlink so uploaded files
    // are accessible over HTTP.
    sse('step', 'Creating storage symlink (php artisan storage:link)');
    $exit = runStreaming('php artisan storage:link --force', ROOT, $env);
    if ($exit !== 0) {
        sse('error', 'storage:link failed — check that public/ is writable by the web server user');
        // Non-fatal: continue so the rest of setup can proceed.
    } else {
        sse('ok', 'public/storage symlink created');
    }

    // chmod is only effective when the process owns the directory.
    // If www-data owns these dirs (after chown earlier) this will succeed.
    sse('step', 'Setting directory permissions');
    @chmod(ROOT . '/storage',         0775);
    @chmod(ROOT . '/bootstrap/cache', 0775);
    sse('ok', 'storage/ and bootstrap/cache/ set to 775');

    sse('done', 'SUCCESS');
}

function runStreaming(string $cmd, string $cwd, array $env): int
{
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $proc = proc_open($cmd, $descriptors, $pipes, $cwd, $env);

    if (!is_resource($proc)) {
        sse('error', "Failed to start process: {$cmd}");
        return 1;
    }

    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    while (true) {
        $read    = [$pipes[1], $pipes[2]];
        $write   = [];
        $except  = [];

        if (stream_select($read, $write, $except, 0, 200000) === false) {
            break;
        }

        foreach ($read as $pipe) {
            while (($line = fgets($pipe)) !== false) {
                $line = rtrim($line);
                if ($line !== '') {
                    sse('output', $line);
                }
            }
        }

        if (feof($pipes[1]) && feof($pipes[2])) {
            break;
        }
    }

    fclose($pipes[1]);
    fclose($pipes[2]);
    return proc_close($proc);
}

function sse(string $type, string $message): void
{
    echo 'data: ' . json_encode(['type' => $type, 'message' => $message]) . "\n\n";
    flush();
}

function handleSaveDb(): void
{
    header('Content-Type: application/json');

    $host     = trim($_POST['db_host']     ?? '');
    $port     = (int) ($_POST['db_port']   ?? 3306);
    $database = trim($_POST['db_database'] ?? '');
    $username = trim($_POST['db_username'] ?? '');
    $password = $_POST['db_password']      ?? '';

    if (!$host || !$database || !$username) {
        echo json_encode(['status' => 'error', 'message' => 'Host, database and username are required.']);
        return;
    }

    if ($port < 1 || $port > 65535) {
        $port = 3306;
    }

    try {
        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
        new PDO($dsn, $username, $password, [
            PDO::ATTR_TIMEOUT => 5,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Connection failed: ' . $e->getMessage()]);
        return;
    }

    $envPath = ROOT . '/.env';
    if (!file_exists($envPath)) {
        echo json_encode(['status' => 'error', 'message' => '.env not found — run the dependency installer first.']);
        return;
    }

    $content = file_get_contents($envPath);
    $pairs   = [
        'DB_CONNECTION' => 'mysql',
        'DB_HOST'       => $host,
        'DB_PORT'       => (string) $port,
        'DB_DATABASE'   => $database,
        'DB_USERNAME'   => $username,
        'DB_PASSWORD'   => $password,
    ];

    foreach ($pairs as $key => $value) {
        $safe = (preg_match('/[\s#"\'\\\\]/', $value) || $value === '')
            ? '"' . addcslashes($value, '"\\') . '"'
            : $value;
        if (preg_match('/^' . $key . '=/m', $content)) {
            $content = preg_replace('/^' . $key . '=.*/m', $key . '=' . $safe, $content);
        } else {
            $content .= "\n" . $key . '=' . $safe;
        }
    }

    if (file_put_contents($envPath, $content) === false) {
        echo json_encode(['status' => 'error', 'message' => 'Could not write to .env — check file permissions.']);
        return;
    }

    // Clear any cached config so Laravel reads the fresh .env on first boot.
    $p = proc_open([PHP_BINARY, ROOT . '/artisan', 'config:clear', '--no-ansi'],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pp, ROOT);
    if (is_resource($p)) { fclose($pp[1]); fclose($pp[2]); proc_close($p); }

    $src = ROOT . '/bootstrap/app_index.php';
    $dst = ROOT . '/public/index.php';

    if (!file_exists($src)) {
        echo json_encode(['status' => 'error', 'message' => 'bootstrap/app_index.php not found — cannot activate the application.']);
        return;
    }

    if (!copy($src, $dst)) {
        echo json_encode(['status' => 'error', 'message' => 'Could not replace public/index.php — check that public/ is writable.']);
        return;
    }

    if (function_exists('opcache_invalidate')) {
        opcache_invalidate($dst, true);
    }

    file_put_contents(ROOT . '/storage/app/.pre_install_complete', date('c'));

    echo json_encode(['status' => 'ok']);
}

// =============================================================================
// HTML
// =============================================================================

function renderPage(array $checks, bool $allPass): void
{
    $failCount = count(array_filter($checks, fn($c) => !$c['pass']));
    $warnCount = count(array_filter($checks, fn($c) =>  $c['pass'] && $c['warn']));
    $procUser  = getCurrentUser();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Federation Registry — Installer</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: #f0f2f5;
    color: #212529;
    min-height: 100vh;
    display: flex;
    align-items: flex-start;
    justify-content: center;
    padding: 2rem 1rem;
  }

  .card {
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 2px 16px rgba(0,0,0,.1);
    width: 100%;
    max-width: 740px;
    overflow: hidden;
  }

  .card-header {
    background: #1a1f2e;
    color: #fff;
    padding: 1.5rem 2rem;
    display: flex;
    align-items: center;
    gap: 1rem;
  }
  .card-header svg { flex-shrink: 0; }
  .card-header h1  { font-size: 1.25rem; font-weight: 600; }
  .card-header p   { font-size: .85rem; color: #9aa3b5; margin-top: .2rem; }

  .card-body { padding: 1.75rem 2rem; }

  h2 {
    font-size: 1rem;
    font-weight: 600;
    color: #495057;
    margin-bottom: 1rem;
    padding-bottom: .5rem;
    border-bottom: 1px solid #e9ecef;
  }

  .check-list { list-style: none; display: flex; flex-direction: column; gap: .35rem; }

  .check-item { border: 1px solid #e9ecef; border-radius: 6px; overflow: hidden; }
  .check-item.fail .check-row { background: #fff8f8; }
  .check-item.pass .check-row { background: #f8fff8; }
  .check-item.warn .check-row { background: #fffdf0; }

  .check-row {
    display: flex;
    align-items: center;
    gap: .75rem;
    padding: .55rem .85rem;
  }

  .icon        { font-size: 1rem; flex-shrink: 0; width: 1.3rem; text-align: center; }
  .check-title { font-weight: 500; flex: 1; font-size: .88rem; }
  .check-value { font-size: .8rem; color: #6c757d; text-align: right; max-width: 55%; }
  .check-item.fail .check-value { color: #dc3545; }
  .check-item.pass .check-value { color: #198754; }
  .check-item.warn .check-value { color: #856404; }

  .fix-label {
    font-size: .7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: #9aa3b5;
    background: #1e2130;
    padding: .3rem 1rem .15rem;
    border-top: 1px solid #2d3348;
  }

  .fix-block {
    background: #1e2130;
    color: #e8eaf0;
    font-family: 'Courier New', Courier, monospace;
    font-size: .78rem;
    line-height: 1.65;
    padding: .75rem 1rem;
    white-space: pre-wrap;
    word-break: break-all;
  }

  .advice-label {
    font-size: .7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: #a07a00;
    background: #2b2200;
    padding: .3rem 1rem .15rem;
    border-top: 1px solid #3d3000;
  }

  .advice-block {
    background: #2b2200;
    color: #ffd866;
    font-family: 'Courier New', Courier, monospace;
    font-size: .78rem;
    line-height: 1.65;
    padding: .75rem 1rem;
    white-space: pre-wrap;
    word-break: break-all;
  }

  .summary {
    display: flex;
    align-items: center;
    gap: .6rem;
    padding: .65rem 1rem;
    border-radius: 6px;
    font-size: .88rem;
    font-weight: 500;
    margin-bottom: 1.25rem;
  }
  .summary.ok   { background: #d1e7dd; color: #0a3622; }
  .summary.warn { background: #fff3cd; color: #664d03; }
  .summary.err  { background: #f8d7da; color: #58151c; }

  .actions {
    display: flex;
    gap: .75rem;
    margin-top: 1.5rem;
    flex-wrap: wrap;
    align-items: center;
  }

  .btn {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    padding: .55rem 1.25rem;
    border-radius: 6px;
    font-size: .9rem;
    font-weight: 500;
    cursor: pointer;
    border: none;
    transition: opacity .15s, background .15s;
  }
  .btn:disabled            { opacity: .4; cursor: not-allowed; }
  .btn-secondary           { background: #e9ecef; color: #495057; text-decoration: none; }
  .btn-secondary:hover:not(:disabled) { background: #dee2e6; }
  .btn-primary             { background: #0d6efd; color: #fff; }
  .btn-primary:hover:not(:disabled)   { background: #0b5ed7; }

  .hint {
    font-size: .78rem;
    color: #6c757d;
    margin-left: .25rem;
  }

  /* Install log */
  #install-section { display: none; margin-top: 1.75rem; }
  #install-section h2 { margin-bottom: .75rem; }

  .progress-bar-wrap {
    height: 5px;
    background: #e9ecef;
    border-radius: 3px;
    margin-bottom: .75rem;
    overflow: hidden;
  }
  .progress-bar {
    height: 100%;
    background: #0d6efd;
    width: 0%;
    transition: width .4s;
    border-radius: 3px;
  }

  #log {
    background: #1e2130;
    color: #cdd5e0;
    font-family: 'Courier New', Courier, monospace;
    font-size: .78rem;
    line-height: 1.6;
    padding: 1rem;
    border-radius: 6px;
    height: 340px;
    overflow-y: auto;
    white-space: pre-wrap;
    word-break: break-all;
  }
  #log .log-step   { color: #7dd3fc; font-weight: bold; }
  #log .log-ok     { color: #86efac; }
  #log .log-error  { color: #fca5a5; font-weight: bold; }
  #log .log-info   { color: #94a3b8; }
  #log .log-output { color: #cbd5e1; }

  #result-msg {
    display: none;
    margin-top: 1rem;
    padding: .75rem 1rem;
    border-radius: 6px;
    font-weight: 500;
    font-size: .9rem;
  }
  #result-msg.ok  { background: #d1e7dd; color: #0a3622; }
  #result-msg.err { background: #f8d7da; color: #58151c; }

  /* DB credentials form */
  #db-section { display: none; margin-top: 1.75rem; }
  .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; margin-bottom: 1rem; }
  @media (max-width: 540px) { .form-grid { grid-template-columns: 1fr; } }
  .form-group { display: flex; flex-direction: column; gap: .3rem; }
  .form-group.full { grid-column: 1 / -1; }
  .form-label { font-size: .82rem; font-weight: 500; color: #495057; }
  .form-input {
    padding: .45rem .7rem;
    border: 1px solid #ced4da;
    border-radius: 5px;
    font-size: .88rem;
    font-family: inherit;
    color: #212529;
  }
  .form-input:focus { outline: none; border-color: #86b7fe; box-shadow: 0 0 0 3px rgba(13,110,253,.15); }
  #db-result {
    display: none;
    margin-top: .75rem;
    padding: .65rem 1rem;
    border-radius: 6px;
    font-weight: 500;
    font-size: .88rem;
  }
</style>
</head>
<body>
<div class="card">

  <div class="card-header">
    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#7dd3fc" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
      <path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/>
    </svg>
    <div>
      <h1>Federation Registry</h1>
      <p>Pre-installer &mdash; verifies your environment before setup begins</p>
    </div>
  </div>

  <div class="card-body">

    <?php if (!$allPass): ?>
    <div class="summary err">
      ❌ &nbsp;<?= $failCount ?> check<?= $failCount !== 1 ? 's' : '' ?> failed.
      Follow the fix instructions below, then click <strong>Re-check</strong>.
    </div>
    <?php elseif ($warnCount > 0): ?>
    <div class="summary warn">
      ⚠️ &nbsp;All checks passed but <?= $warnCount ?> permission <?= $warnCount !== 1 ? 'warnings' : 'warning' ?> found.
      Installation can proceed — fix before going live.
    </div>
    <?php else: ?>
    <div class="summary ok">
      ✅ &nbsp;All checks passed &mdash; ready to install.
    </div>
    <?php endif; ?>
    <?php if ($procUser): ?>
    <p class="hint" style="margin-bottom:.75rem">Running as: <code><?= htmlspecialchars($procUser) ?></code></p>
    <?php endif; ?>

    <h2>System Requirements</h2>
    <ul class="check-list">
    <?php foreach ($checks as $check): ?>
      <?php
        $itemClass = !$check['pass'] ? 'fail' : ($check['warn'] ? 'warn' : 'pass');
        $icon      = !$check['pass'] ? '❌'   : ($check['warn'] ? '⚠️'  : '✅');
      ?>
      <li class="check-item <?= $itemClass ?>">
        <div class="check-row">
          <span class="icon"><?= $icon ?></span>
          <span class="check-title"><?= htmlspecialchars($check['title']) ?></span>
          <span class="check-value"><?= htmlspecialchars($check['value']) ?></span>
        </div>
        <?php if (!$check['pass'] && $check['fix']): ?>
        <div class="fix-label">How to fix</div>
        <div class="fix-block"><?= htmlspecialchars($check['fix']) ?></div>
        <?php endif; ?>
        <?php if ($check['warn'] && $check['advice']): ?>
        <div class="advice-label">Security recommendation</div>
        <div class="advice-block"><?= htmlspecialchars($check['advice']) ?></div>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
    </ul>

    <div class="actions">
      <a href="/" class="btn btn-secondary">🔄 Re-check</a>
      <button id="btn-install" class="btn btn-primary" <?= $allPass ? '' : 'disabled' ?>>
        🚀 Begin Installation
      </button>
      <?php if (!$allPass): ?>
      <span class="hint">Fix the issues above first, then Re-check.</span>
      <?php endif; ?>
    </div>

    <div id="install-section">
      <h2>Installation Progress</h2>
      <div class="progress-bar-wrap"><div class="progress-bar" id="progress"></div></div>
      <div id="log"></div>
      <div id="result-msg"></div>
    </div>

    <div id="db-section">
      <h2>Database Configuration</h2>
      <p style="font-size:.85rem; color:#6c757d; margin-bottom:1rem;">
        Enter your MySQL / MariaDB credentials. The connection will be tested before writing to <code>.env</code> and activating the application.
      </p>
      <form id="db-form">
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label" for="db_host">Host</label>
            <input class="form-input" type="text" id="db_host" name="db_host" value="127.0.0.1" required>
          </div>
          <div class="form-group">
            <label class="form-label" for="db_port">Port</label>
            <input class="form-input" type="number" id="db_port" name="db_port" value="3306" min="1" max="65535" required>
          </div>
          <div class="form-group">
            <label class="form-label" for="db_database">Database name</label>
            <input class="form-input" type="text" id="db_database" name="db_database" placeholder="federation_manager" required>
          </div>
          <div class="form-group">
            <label class="form-label" for="db_username">Username</label>
            <input class="form-input" type="text" id="db_username" name="db_username" required>
          </div>
          <div class="form-group full">
            <label class="form-label" for="db_password">Password <span style="font-weight:400;color:#9aa3b5">(leave blank if none)</span></label>
            <input class="form-input" type="password" id="db_password" name="db_password">
          </div>
        </div>
        <button type="submit" id="btn-savedb" class="btn btn-primary">⚡ Connect &amp; Activate</button>
      </form>
      <div id="db-result"></div>
    </div>

  </div>
</div>

<script>
(function () {
  const btn     = document.getElementById('btn-install');
  const section = document.getElementById('install-section');
  const log     = document.getElementById('log');
  const bar     = document.getElementById('progress');
  const result  = document.getElementById('result-msg');

  const TOTAL_STEPS = 7;
  let stepsDone = 0;
  let finished   = false;

  function appendLog(cls, text) {
    const span = document.createElement('span');
    span.className = 'log-' + cls;
    span.textContent = text + '\n';
    log.appendChild(span);
    log.scrollTop = log.scrollHeight;
  }

  btn.addEventListener('click', function () {
    btn.disabled = true;
    document.querySelector('.btn-secondary').style.display = 'none';
    const hint = document.querySelector('.hint');
    if (hint) hint.style.display = 'none';
    section.style.display = 'block';
    section.scrollIntoView({ behavior: 'smooth', block: 'start' });

    const es = new EventSource('?action=stream');

    es.onmessage = function (e) {
      const { type, message } = JSON.parse(e.data);

      if (type === 'step') {
        appendLog('step', '▶  ' + message);
        stepsDone++;
        bar.style.width = Math.round((stepsDone / TOTAL_STEPS) * 90) + '%';

      } else if (type === 'ok') {
        appendLog('ok', '   ✓ ' + message);

      } else if (type === 'info') {
        appendLog('info', '   ℹ ' + message);

      } else if (type === 'error') {
        appendLog('error', '   ✗ ' + message);

      } else if (type === 'output') {
        appendLog('output', '     ' + message);

      } else if (type === 'done') {
        es.close();
        bar.style.width = '100%';
        finished = true;

        if (message === 'SUCCESS') {
          result.className     = 'ok';
          result.textContent   = '✅  Dependencies ready — configure your database below.';
          result.style.display = 'block';
          const dbSec = document.getElementById('db-section');
          dbSec.style.display = 'block';
          dbSec.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } else {
          result.className     = 'err';
          result.textContent   = '❌  Installation failed. Review the errors above, fix them, reload the page and try again.';
          result.style.display = 'block';
          btn.disabled   = false;
          btn.textContent = '🔁 Retry';
          document.querySelector('.btn-secondary').style.display = '';
        }
      }
    };

    es.onerror = function () {
      // Ignore the close-event that fires when the server ends a successful
      // stream — EventSource treats a normal server disconnect as an error.
      if (finished) return;

      es.close();
      appendLog('error', 'Connection lost — the server may have timed out or restarted.');
      result.className     = 'err';
      result.textContent   = '❌  Stream interrupted. Reload the page to check the current state.';
      result.style.display = 'block';
      btn.disabled   = false;
      btn.textContent = '🔁 Retry';
    };
  });

  document.getElementById('db-form').addEventListener('submit', async function (e) {
    e.preventDefault();
    const submitBtn = document.getElementById('btn-savedb');
    const dbResult  = document.getElementById('db-result');

    submitBtn.disabled    = true;
    submitBtn.textContent = '⏳ Connecting…';
    dbResult.style.display = 'none';

    try {
      const resp = await fetch('?action=savedb', { method: 'POST', body: new FormData(this) });
      const data = await resp.json();

      if (data.status === 'ok') {
        dbResult.style.cssText = 'background:#d1e7dd;color:#0a3622;display:block';
        dbResult.textContent   = '✅  Database configured — verifying application boot…';

        try {
          const probe = await fetch('/install', { redirect: 'follow' });
          if (probe.status >= 500) {
            throw new Error('HTTP ' + probe.status);
          }
          dbResult.textContent = '✅  Application ready — redirecting to setup wizard…';
          setTimeout(() => { window.location.href = '/install'; }, 800);
        } catch (probeErr) {
          dbResult.style.cssText = 'background:#f8d7da;color:#58151c;display:block';
          dbResult.textContent   = '❌  Application failed to start (' + probeErr.message + '). '
            + 'Credentials were saved. Check storage/logs/laravel.log and ensure storage/ and bootstrap/cache/ are writable.';
          submitBtn.disabled    = false;
          submitBtn.textContent = '🔁 Retry';
        }
      } else {
        dbResult.style.cssText = 'background:#f8d7da;color:#58151c;display:block';
        dbResult.textContent   = '❌  ' + data.message;
        submitBtn.disabled    = false;
        submitBtn.textContent = '🔁 Retry';
      }
    } catch (err) {
      dbResult.style.cssText = 'background:#f8d7da;color:#58151c;display:block';
      dbResult.textContent   = '❌  Request failed: ' + err.message;
      submitBtn.disabled    = false;
      submitBtn.textContent = '🔁 Retry';
    }
  });
}());
</script>
</body>
</html>
<?php
}
