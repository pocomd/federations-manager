<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Entity;
use App\Services\Entity\EntityImportService;
use App\Services\Entity\EntityMetadataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class EntityImportController extends Controller
{
    public function __construct(private readonly EntityImportService $importService)
    {
    }

    public function showXmlImport(): View
    {
        Gate::authorize('entity.create');

        return view('entities.import-xml');
    }

    /**
     * Parse an XML EntityDescriptor (uploaded file or pasted text) and store the result in session.
     * Redirects to the import preview step on success.
     */
    public function importFromXml(Request $request): RedirectResponse
    {
        Gate::authorize('entity.create');

        $request->validate([
            'xml_file' => ['nullable', 'file', 'mimes:xml', 'max:2048'],
            'xml'      => ['nullable', 'string'],
        ]);

        if ($request->hasFile('xml_file')) {
            $xml = $request->file('xml_file')->get();
        } else {
            $xml = $request->input('xml', '');
        }

        if (empty(trim($xml))) {
            return back()->withInput()->withErrors(['xml' => 'Please paste XML or upload an XML file.']);
        }

        try {
            $parsed = $this->importService->fromXml($xml);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['xml' => $e->getMessage()]);
        }

        session(['import_data' => $parsed]);

        return redirect()->route('entities.import.preview');
    }

    public function showArrayImport(): View
    {
        Gate::authorize('entity.create');

        return view('entities.import-array');
    }

    /**
     * Parse a JSON entity object and store the result in session.
     * Redirects to the import preview step on success.
     */
    public function importFromArray(Request $request): RedirectResponse
    {
        Gate::authorize('entity.create');

        $request->validate(['json' => ['required', 'string']]);

        $decoded = json_decode($request->input('json'), true);

        if (!is_array($decoded)) {
            return back()->withInput()->withErrors(['json' => 'Invalid JSON — must be a JSON object.']);
        }

        try {
            $parsed = $this->importService->fromArray($decoded);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['json' => $e->getMessage()]);
        }

        session(['import_data' => $parsed]);

        return redirect()->route('entities.import.preview');
    }

    public function previewImport(): View|RedirectResponse
    {
        Gate::authorize('entity.create');

        $data = session('import_data');

        if (!$data) {
            return redirect()->route('entities.import.xml')
                ->with('error', 'No import data found. Please parse XML or JSON first.');
        }

        return view('entities.import-preview', ['data' => $data]);
    }

    /**
     * Persist the session-stored import data as a new Entity record.
     *
     * Clears the import_data session key and invalidates the entity's XML cache after import.
     *
     * @sideeffects  Session::forget('import_data'), Cache::forget(xmlCacheKey)
     */
    public function confirmImport(Request $request): RedirectResponse
    {
        Gate::authorize('entity.create');

        $data = session('import_data');

        if (!$data) {
            return redirect()->route('entities.import.xml')
                ->with('error', 'No import data found. Please parse XML or JSON first.');
        }

        try {
            $entity = $this->importService->import($data, $request->user());
        } catch (\Exception $e) {
            return back()->with('error', 'Import failed: ' . $e->getMessage());
        }

        session()->forget('import_data');

        Cache::forget(EntityMetadataService::xmlCacheKey($entity));

        return redirect()->route('entities.show', $entity)
            ->with('success', 'Entity imported successfully.');
    }

    public function reloadEntity(Request $request, Entity $entity): RedirectResponse
    {
        Gate::authorize('entity.edit');
        Gate::authorize('update', $entity);

        $request->validate([
            'file'    => ['nullable', 'file', 'max:2048'],
            'content' => ['nullable', 'string'],
        ]);

        $raw = $request->hasFile('file')
            ? $request->file('file')->get()
            : trim($request->input('content', ''));

        if (empty($raw)) {
            return back()->withErrors(['content' => 'Please paste XML/JSON or upload a file.']);
        }

        try {
            $parsed = str_starts_with(ltrim($raw), '<')
                ? $this->importService->fromXml($raw)
                : $this->importService->fromArray(
                    tap(json_decode($raw, true), function ($d) {
                        if (!is_array($d)) {
                            throw new \InvalidArgumentException('Invalid input — must be XML or a JSON object.');
                        }
                    })
                );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['content' => $e->getMessage()]);
        }

        if ($parsed['entity_id'] !== $entity->entity_id) {
            return back()->withErrors([
                'content' => "Entity ID mismatch: the file contains \"{$parsed['entity_id']}\" but this entity is \"{$entity->entity_id}\".",
            ]);
        }

        session(["entity_reload_{$entity->id}" => $parsed]);

        return redirect()->route('entities.edit', $entity)
            ->with('info', 'Form populated from metadata — review the fields and click Save Changes to apply.');
    }
}
