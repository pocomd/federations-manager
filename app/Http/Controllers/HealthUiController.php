<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Entity\CertificateService;
use App\Services\HealthChecks\CertificateParserCheck;
use App\Services\HealthChecks\DatabaseCheck;
use App\Services\HealthChecks\QueueCheck;
use App\Services\HealthChecks\RedisCheck;
use App\Services\HealthChecks\SchedulerHeartbeatCheck;
use App\Services\HealthChecks\SigningHealthCheck;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class HealthUiController extends Controller
{
    public const CHECKS = ['database', 'redis', 'signing', 'scheduler', 'cert_parser', 'queue'];

    public function index(): View
    {
        abort_unless(config('app.health_ui_enabled'), 404);
        Gate::authorize('federation.create');

        return view('admin.health.index', [
            'checks' => self::CHECKS,
            'meta'   => self::meta(),
        ]);
    }

    public function check(string $name, CertificateService $certificates): JsonResponse
    {
        abort_unless(config('app.health_ui_enabled'), 404);
        Gate::authorize('federation.create');

        $instance = match($name) {
            'database'    => new DatabaseCheck(),
            'redis'       => new RedisCheck(),
            'signing'     => new SigningHealthCheck(),
            'scheduler'   => new SchedulerHeartbeatCheck(),
            'cert_parser' => new CertificateParserCheck($certificates),
            'queue'       => new QueueCheck(),
            default       => abort(404),
        };

        return response()->json($instance->run()->toArray());
    }

    /**
     * Each action is either:
     *   string                         — plain text (may contain inline <code>)
     *   ['text' => ..., 'cmds' => []]  — descriptive text followed by one command per line
     *
     * @return array<string, array{description: string, actions: array<int, string|array{text:string,cmds:string[]}>}>
     */
    private static function meta(): array
    {
        return [
            'database' => [
                'description' => 'Verifies the application can connect to MySQL and execute a basic query.',
                'actions' => [
                    'Check <code>DB_HOST</code>, <code>DB_PORT</code>, <code>DB_DATABASE</code>, <code>DB_USERNAME</code>, and <code>DB_PASSWORD</code> in <code>.env</code>',
                    ['text' => 'Ensure MySQL is running:', 'cmds' => [
                        'systemctl status mysql',
                    ]],
                    ['text' => 'Test connectivity:', 'cmds' => [
                        'mysql -u $DB_USERNAME -p -h $DB_HOST $DB_DATABASE',
                    ]],
                    ['text' => 'Check Laravel logs:', 'cmds' => [
                        'tail -f storage/logs/laravel.log',
                    ]],
                ],
            ],
            'redis' => [
                'description' => 'Tests Redis connectivity by writing and reading a temporary cache key.',
                'actions' => [
                    'Check <code>REDIS_HOST</code> and <code>REDIS_PORT</code> in <code>.env</code>',
                    ['text' => 'Ensure Redis is running:', 'cmds' => [
                        'systemctl status redis',
                    ]],
                    ['text' => 'Test connectivity:', 'cmds' => [
                        'redis-cli -h $REDIS_HOST ping',
                    ]],
                    'If using a password, verify <code>REDIS_PASSWORD</code> in <code>.env</code>',
                ],
            ],
            'signing' => [
                'description' => 'Runs a health check on every active signing driver. The file driver performs a live xmlsectool signing round-trip; the SoftHSM2 driver verifies binaries and the PKCS#11 library. A failure means signing will not work. A warning means a driver is misconfigured but may not affect all federations.',
                'actions' => [
                    'Set <code>XMLSECTOOL_PATH</code> in <code>.env</code> — required for the file driver',
                    'Upload a signing key pair per federation via <strong>Federation → Signing Keys tab</strong>',
                    ['text' => 'Verify the xmlsectool binary is executable:', 'cmds' => [
                        'grep XMLSECTOOL_PATH /var/www/jagger-next/.env',
                        'test -x "$(grep ^XMLSECTOOL_PATH /var/www/jagger-next/.env | cut -d= -f2)" && echo "OK: executable" || echo "FAIL: not found or not executable"',
                    ]],
                    ['text' => 'For SoftHSM2 driver — verify required packages are installed:', 'cmds' => [
                        'which softhsm2-util && softhsm2-util --version',
                        'which pkcs11-tool && pkcs11-tool --version',
                        'test -f "$(grep ^PKCS11_LIBRARY /var/www/jagger-next/.env | cut -d= -f2)" && echo "library: present" || echo "library: NOT found"',
                    ]],
                    ['text' => 'If the error is "JAVA_HOME environment variable is not set":', 'cmds' => [
                        'dirname $(dirname $(readlink -f $(which java)))',
                        'echo \'JAVA_HOME=/usr/lib/jvm/java-17-openjdk-amd64\' | sudo tee -a /etc/environment',
                        'sudo systemctl restart php8.4-fpm   # or php8.3-fpm',
                    ]],
                    '<strong>Security note (SoftHSM2):</strong> Anyone with root access and the <code>JAGGER_HSM_PIN</code> value can sign metadata directly via xmlsectool, bypassing this application. Monitor the audit log for metadata changes without a corresponding signing entry.',
                ],
            ],
            'scheduler' => [
                'description' => 'Checks the scheduler heartbeat written every minute by SchedulerHeartbeatJob. Confirms the Laravel scheduler cron is running and the queue worker is processing jobs.',
                'actions' => [
                    ['text' => 'Check cron is configured:', 'cmds' => [
                        'crontab -l | grep artisan',
                    ]],
                    ['text' => 'Add the scheduler cron if missing (run as www-data or nginx):', 'cmds' => [
                        '* * * * * cd /var/www/jagger-next && php artisan schedule:run >> /dev/null 2>&1',
                    ]],
                    ['text' => 'List all scheduled tasks and their next run times:', 'cmds' => [
                        'php artisan schedule:list',
                    ]],
                    ['text' => 'Check system cron logs:', 'cmds' => [
                        'grep CRON /var/log/syslog | tail -20',
                    ]],
                ],
            ],
            'cert_parser' => [
                'description' => 'Parses a test X.509 certificate to verify the certificate parsing library is functional.',
                'actions' => [
                    ['text' => 'Ensure the PHP OpenSSL extension is enabled:', 'cmds' => [
                        'php -m | grep openssl',
                    ]],
                    ['text' => 'Verify OpenSSL is available on the system:', 'cmds' => [
                        'openssl version',
                    ]],
                    ['text' => 'Check application logs for parser errors:', 'cmds' => [
                        'tail -f storage/logs/laravel.log',
                    ]],
                ],
            ],
            'queue' => [
                'description' => 'Dispatches a test job to the <code>default</code> and <code>high</code> queues and waits up to 5 seconds for a worker to process each. A failure on <code>high</code> means federation metadata is never auto-generated.',
                'actions' => [
                    'If only <code>high</code> fails: verify the worker is started with <code>--queue=high,default,low</code> — without this flag it only processes the <code>default</code> queue',
                    ['text' => 'Check the queue flag the running worker was started with:', 'cmds' => [
                        'ps aux | grep "queue:work" | grep -v grep',
                    ]],
                    ['text' => 'Check whether a queue worker is running:', 'cmds' => [
                        'systemctl status jagger-queue',
                    ]],
                    ['text' => 'Diagnose repeated worker restarts (exit reason, fatal errors):', 'cmds' => [
                        'journalctl -u jagger-queue -n 50 --no-pager',
                        'tail -n 100 storage/logs/worker.log',
                    ]],
                    ['text' => 'Start the worker if it is not running:', 'cmds' => [
                        'sudo systemctl start jagger-queue',
                    ]],
                    ['text' => 'Signal workers to restart gracefully after a deploy:', 'cmds' => [
                        'php artisan queue:restart',
                    ]],
                    ['text' => 'Check for failed jobs:', 'cmds' => [
                        'php artisan queue:failed',
                    ]],
                ],
            ],
        ];
    }
}
