<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isInstalled()) {
            return $next($request);
        }

        // Bootstrap .env if missing (fresh clone with no .env yet)
        if (! file_exists(base_path('.env')) && file_exists(base_path('.env.example'))) {
            copy(base_path('.env.example'), base_path('.env'));
        }

        // Generate APP_KEY inline if absent so sessions can be encrypted
        if (! config('app.key') && file_exists(base_path('.env'))) {
            $this->generateAppKey();
        }

        // Force file sessions — DB may not be configured yet
        config(['session.driver' => 'file']);

        // Allow installer routes and static assets through
        if ($request->is('install', 'install/*') || $request->is('build/*') || $request->is('assets/*')) {
            return $next($request);
        }

        return redirect('/install');
    }

    private function isInstalled(): bool
    {
        // APP_INSTALLED in .env is authoritative when present.
        // env() returns null only when the key is completely absent from .env
        // (e.g. a legacy install that pre-dates this key), in which case we
        // fall back to the flag file.
        $envValue = env('APP_INSTALLED');
        if ($envValue !== null) {
            return (bool) $envValue;
        }

        return file_exists(storage_path('app/.installed'));
    }

    private function generateAppKey(): void
    {
        $key     = 'base64:' . base64_encode(random_bytes(32));
        $envPath = base_path('.env');
        $content = file_get_contents($envPath) ?: '';

        if (preg_match('/^APP_KEY=.*$/m', $content)) {
            $content = preg_replace('/^APP_KEY=.*$/m', "APP_KEY={$key}", $content);
        } else {
            $content .= "\nAPP_KEY={$key}\n";
        }

        file_put_contents($envPath, $content);

        // Apply to the current process so this request's sessions work
        config(['app.key' => $key]);
        putenv("APP_KEY={$key}");
        $_ENV['APP_KEY']    = $key;
        $_SERVER['APP_KEY'] = $key;
    }
}
