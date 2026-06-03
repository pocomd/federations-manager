<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

// Point ROOT at a dedicated temp directory so every file-based function
// operates on controlled fixtures rather than the real project tree.
// Must be defined before require_once so public/index.php skips its own define.
if (!defined('ROOT')) {
    define('ROOT', sys_get_temp_dir() . '/jagger-preinstall-test');
}

// Suppress the top-level routing / render code inside public/index.php.
if (!defined('INSTALLER_TESTING')) {
    define('INSTALLER_TESTING', true);
}

require_once __DIR__ . '/../../public/index.php';

class PreInstallerPermissionsTest extends TestCase
{
    private string $tmpDir;

    // ── Lifecycle ────────────────────────────────────────────────────────────

    public static function setUpBeforeClass(): void
    {
        @mkdir(ROOT, 0755, true);
    }

    public static function tearDownAfterClass(): void
    {
        self::deleteTree(ROOT);
    }

    protected function setUp(): void
    {
        $this->tmpDir = ROOT . '/d_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        self::deleteTree($this->tmpDir);
        if (file_exists(ROOT . '/.env')) {
            unlink(ROOT . '/.env');
        }
    }

    private static function deleteTree(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }
        if (is_file($path) || is_link($path)) {
            unlink($path);
            return;
        }
        foreach (scandir($path) as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            self::deleteTree($path . '/' . $entry);
        }
        rmdir($path);
    }

    // ── getPathMode ──────────────────────────────────────────────────────────

    public function test_getPathMode_returns_correct_mode_for_file(): void
    {
        $file = $this->tmpDir . '/f.txt';
        file_put_contents($file, '');
        chmod($file, 0640);

        $this->assertSame('0640', getPathMode($file));
    }

    public function test_getPathMode_returns_correct_mode_for_directory(): void
    {
        chmod($this->tmpDir, 0755);

        $this->assertSame('0755', getPathMode($this->tmpDir));
    }

    public function test_getPathMode_returns_null_for_missing_path(): void
    {
        $this->assertNull(getPathMode(ROOT . '/does-not-exist'));
    }

    // ── getCurrentUser ───────────────────────────────────────────────────────

    public function test_getCurrentUser_returns_non_empty_string(): void
    {
        $user = getCurrentUser();

        $this->assertNotNull($user);
        $this->assertNotEmpty($user);
        $this->assertIsString($user);
    }

    // ── buildMeta ────────────────────────────────────────────────────────────

    public function test_buildMeta_empty_when_all_null(): void
    {
        $this->assertSame('', buildMeta(null, null, null));
    }

    public function test_buildMeta_mode_only(): void
    {
        $this->assertSame('mode: 0755', buildMeta('0755', null, null));
    }

    public function test_buildMeta_mode_and_owner(): void
    {
        $this->assertSame('mode: 0755, owner: www-data', buildMeta('0755', 'www-data', null));
    }

    public function test_buildMeta_all_three_fields(): void
    {
        $this->assertSame(
            'mode: 0755, owner: www-data, running as: nginx',
            buildMeta('0755', 'www-data', 'nginx'),
        );
    }

    // ── writableOkResult ─────────────────────────────────────────────────────

    public function test_writableOkResult_clean_directory_passes_without_warn(): void
    {
        chmod($this->tmpDir, 0755);
        $result = writableOkResult('id', 'label', $this->tmpDir, isDir: true);

        $this->assertTrue($result['pass']);
        $this->assertFalse($result['warn']);
        $this->assertNull($result['fix']);
        $this->assertNull($result['advice']);
        $this->assertStringContainsString('Writable', $result['value']);
    }

    public function test_writableOkResult_world_writable_directory_warns_with_775(): void
    {
        chmod($this->tmpDir, 0777);
        $result = writableOkResult('id', 'label', $this->tmpDir, isDir: true);

        $this->assertTrue($result['pass']);
        $this->assertTrue($result['warn']);
        $this->assertNull($result['fix']);
        $this->assertStringContainsString('world-writable', $result['value']);
        $this->assertStringContainsString('775', $result['advice']);
    }

    public function test_writableOkResult_world_writable_file_recommends_664(): void
    {
        $file = $this->tmpDir . '/f.txt';
        file_put_contents($file, '');
        chmod($file, 0777);
        $result = writableOkResult('id', 'label', $file, isDir: false);

        $this->assertTrue($result['warn']);
        $this->assertStringContainsString('664', $result['advice']);
    }

    public function test_writableOkResult_value_contains_mode(): void
    {
        chmod($this->tmpDir, 0755);
        $result = writableOkResult('id', 'label', $this->tmpDir, isDir: true);

        $this->assertStringContainsString('0755', $result['value']);
    }

    // ── checkWritable ────────────────────────────────────────────────────────

    public function test_checkWritable_fails_when_directory_missing(): void
    {
        $result = checkWritable('absent/', ROOT . '/no-such-dir');

        $this->assertFalse($result['pass']);
        $this->assertFalse($result['warn']);
        $this->assertStringContainsString('missing', strtolower($result['value']));
        $this->assertNotNull($result['fix']);
    }

    public function test_checkWritable_passes_clean_directory(): void
    {
        chmod($this->tmpDir, 0755);
        $result = checkWritable('d/', $this->tmpDir);

        $this->assertTrue($result['pass']);
        $this->assertFalse($result['warn']);
        $this->assertNull($result['fix']);
    }

    public function test_checkWritable_warns_on_world_writable(): void
    {
        chmod($this->tmpDir, 0777);
        $result = checkWritable('d/', $this->tmpDir);

        $this->assertTrue($result['pass']);
        $this->assertTrue($result['warn']);
        $this->assertNotNull($result['advice']);
        $this->assertNull($result['fix']);
    }

    public function test_checkWritable_fails_non_writable_and_shows_meta(): void
    {
        if (posix_geteuid() === 0) {
            $this->markTestSkipped('Running as root — chmod 0444 does not prevent writes.');
        }

        $dir = $this->tmpDir . '/ro';
        mkdir($dir, 0555);

        $result = checkWritable('ro/', $dir);

        $this->assertFalse($result['pass']);
        $this->assertStringContainsString('mode:', $result['value']);
        $this->assertNotNull($result['fix']);

        chmod($dir, 0755);
    }

    // ── checkWritableIfExists ────────────────────────────────────────────────

    public function test_checkWritableIfExists_returns_null_for_absent_path(): void
    {
        $this->assertNull(checkWritableIfExists('x/', ROOT . '/no-such-dir'));
    }

    public function test_checkWritableIfExists_passes_clean_directory(): void
    {
        chmod($this->tmpDir, 0755);
        $result = checkWritableIfExists('d/', $this->tmpDir);

        $this->assertNotNull($result);
        $this->assertTrue($result['pass']);
        $this->assertFalse($result['warn']);
    }

    public function test_checkWritableIfExists_warns_world_writable(): void
    {
        chmod($this->tmpDir, 0777);
        $result = checkWritableIfExists('d/', $this->tmpDir);

        $this->assertNotNull($result);
        $this->assertTrue($result['pass']);
        $this->assertTrue($result['warn']);
    }

    // ── checkEnvPermissions ──────────────────────────────────────────────────

    public function test_checkEnvPermissions_returns_null_when_env_absent(): void
    {
        $this->assertFileDoesNotExist(ROOT . '/.env');
        $this->assertNull(checkEnvPermissions());
    }

    public function test_checkEnvPermissions_passes_on_secure_permissions(): void
    {
        file_put_contents(ROOT . '/.env', 'APP_KEY=test');
        chmod(ROOT . '/.env', 0640);

        $result = checkEnvPermissions();

        $this->assertNotNull($result);
        $this->assertTrue($result['pass']);
        $this->assertFalse($result['warn']);
        $this->assertNull($result['advice']);
    }

    public function test_checkEnvPermissions_warns_world_readable(): void
    {
        file_put_contents(ROOT . '/.env', 'APP_KEY=test');
        chmod(ROOT . '/.env', 0644);

        $result = checkEnvPermissions();

        $this->assertNotNull($result);
        $this->assertTrue($result['pass']);
        $this->assertTrue($result['warn']);
        $this->assertStringContainsString('world-readable', $result['value']);
        $this->assertStringContainsString('secrets', $result['advice']);
    }

    public function test_checkEnvPermissions_warns_world_writable(): void
    {
        file_put_contents(ROOT . '/.env', 'APP_KEY=test');
        chmod(ROOT . '/.env', 0626);

        $result = checkEnvPermissions();

        $this->assertNotNull($result);
        $this->assertTrue($result['warn']);
        $this->assertStringContainsString('world-writable', $result['value']);
    }

    public function test_checkEnvPermissions_warns_both_flags(): void
    {
        file_put_contents(ROOT . '/.env', 'APP_KEY=test');
        chmod(ROOT . '/.env', 0666);

        $result = checkEnvPermissions();

        $this->assertNotNull($result);
        $this->assertTrue($result['warn']);
        $this->assertStringContainsString('world-readable', $result['value']);
        $this->assertStringContainsString('world-writable', $result['value']);
    }

    public function test_checkEnvPermissions_fails_when_not_writable(): void
    {
        if (posix_geteuid() === 0) {
            $this->markTestSkipped('Running as root — chmod 0444 does not prevent writes.');
        }

        file_put_contents(ROOT . '/.env', 'APP_KEY=test');
        chmod(ROOT . '/.env', 0444);

        $result = checkEnvPermissions();

        $this->assertNotNull($result);
        $this->assertFalse($result['pass']);
        $this->assertNotNull($result['fix']);

        chmod(ROOT . '/.env', 0640);
    }
}
