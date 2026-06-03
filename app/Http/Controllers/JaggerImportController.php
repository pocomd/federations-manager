<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\JaggerImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Throwable;

class JaggerImportController extends Controller
{
    private function abortIfDisabled(): void
    {
        abort_unless(config('federation.import_enabled'), 404);
    }

    public function index(): View
    {
        $this->abortIfDisabled();
        Gate::authorize('federation.create');

        return view('admin.import.jagger');
    }

    /**
     * Test connectivity to a Jagger database and return a preview of importable records (JSON).
     *
     * The entire controller requires federation.import_enabled config to be true (aborts 404 otherwise).
     */
    public function test(Request $request): JsonResponse
    {
        $this->abortIfDisabled();
        Gate::authorize('federation.create');

        $creds = $request->validate([
            'host'     => 'required|string|max:253',
            'port'     => 'required|integer|min:1|max:65535',
            'database' => 'required|string|max:64',
            'username' => 'required|string|max:80',
            'password' => 'nullable|string|max:255',
        ]);

        try {
            $svc = new JaggerImportService($creds);

            if (!$svc->testConnection()) {
                return response()->json(['ok' => false, 'message' => 'Connection refused — check host, port and credentials.'], 422);
            }

            return response()->json(['ok' => true, 'preview' => $svc->preview()]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Run a full Jagger database import synchronously.
     *
     * Potentially destructive: clear_first=true truncates existing entity data before importing.
     * Returns a results view on success, redirects back with errors on failure.
     */
    public function run(Request $request): View|RedirectResponse
    {
        $this->abortIfDisabled();
        Gate::authorize('federation.create');

        $validated = $request->validate([
            'host'          => 'required|string|max:253',
            'port'          => 'required|integer|min:1|max:65535',
            'database'      => 'required|string|max:64',
            'username'      => 'required|string|max:80',
            'password'      => 'nullable|string|max:255',
            'only_local'    => 'nullable|boolean',
            'skip_existing' => 'nullable|boolean',
            'clear_first'   => 'nullable|boolean',
        ]);

        try {
            $svc = new JaggerImportService($validated);

            if (!$svc->testConnection()) {
                return back()
                    ->withInput()
                    ->withErrors(['connection' => 'Could not connect to the Jagger database. Verify credentials and network access.']);
            }

            $result = $svc->run(
                onlyLocal:    (bool) ($validated['only_local']    ?? true),
                skipExisting: (bool) ($validated['skip_existing'] ?? true),
                clearFirst:   (bool) ($validated['clear_first']   ?? false),
            );

            AuditLog::create([
                'user_id'    => Auth::id(),
                'entity_id'  => null,
                'action'     => 'jagger_import_run',
                'old_values' => null,
                'new_values' => [
                    'host'          => $validated['host'],
                    'database'      => $validated['database'],
                    'only_local'    => $validated['only_local'] ?? true,
                    'skip_existing' => $validated['skip_existing'] ?? true,
                    'clear_first'   => $validated['clear_first'] ?? false,
                    'success'       => $result['success'],
                ],
                'ip_address' => $request->ip() ?? '127.0.0.1',
            ]);

            if (! $result['success']) {
                return back()
                    ->withInput()
                    ->with('import_result', $result);
            }

            return view('admin.import.jagger-results', $result);
        } catch (Throwable $e) {
            return back()
                ->withInput()
                ->withErrors(['import' => 'Import failed: ' . $e->getMessage()]);
        }
    }
}
