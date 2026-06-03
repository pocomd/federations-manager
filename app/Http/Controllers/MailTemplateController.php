<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MailTemplate;
use App\Services\Mail\MailTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MailTemplateController extends Controller
{
    public function index(): View
    {
        Gate::authorize('federation.create');

        $templates = MailTemplate::orderBy('group')
            ->orderBy('name')
            ->get()
            ->groupBy('group');

        return view('mail.templates.index', compact('templates'));
    }

    public function create(): View
    {
        Gate::authorize('federation.create');

        return view('mail.templates.create', [
            'groups'       => MailTemplate::GROUPS,
            'placeholders' => MailTemplate::PLACEHOLDERS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('federation.create');

        $validated = $request->validate($this->rules());
        $validated['is_active'] = (bool) ($validated['is_active'] ?? false);

        MailTemplate::create($validated);

        return redirect()->route('mail.templates.index')
            ->with('success', 'Template created.');
    }

    public function edit(MailTemplate $template): View
    {
        Gate::authorize('federation.create');

        return view('mail.templates.edit', [
            'template'     => $template,
            'groups'       => MailTemplate::GROUPS,
            'placeholders' => MailTemplate::PLACEHOLDERS,
        ]);
    }

    public function update(Request $request, MailTemplate $template): RedirectResponse
    {
        Gate::authorize('federation.create');

        $validated = $request->validate($this->rules());
        $validated['is_active'] = (bool) ($validated['is_active'] ?? false);

        $template->update($validated);

        return redirect()->route('mail.templates.index')
            ->with('success', 'Template updated.');
    }

    public function destroy(MailTemplate $template): RedirectResponse
    {
        Gate::authorize('federation.create');

        $template->delete();

        return redirect()->route('mail.templates.index')
            ->with('success', 'Template deleted.');
    }

    public function preview(MailTemplate $template, MailTemplateService $service): Response
    {
        Gate::authorize('federation.create');

        $rendered = $service->render($template, [
            'federation'      => null,
            'entity'          => null,
            'contact'         => null,
            'cert_expiry_date'=> 'YYYY-MM-DD',
            'cert_subject'    => 'CN=example.org',
        ]);

        return response($rendered['body'])
            ->header('Content-Type', 'text/plain');
    }

    private function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'max:255'],
            'group'     => ['required', Rule::in(array_keys(MailTemplate::GROUPS))],
            'subject'   => ['required', 'string', 'max:255'],
            'body'      => ['required', 'string'],
            'lang'      => ['required', 'string', 'max:10'],
            'is_active' => ['boolean'],
        ];
    }
}
