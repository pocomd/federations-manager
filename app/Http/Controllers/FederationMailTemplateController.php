<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Entity;
use App\Models\Federation;
use App\Models\MailTemplate;
use App\Services\Mail\MailTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class FederationMailTemplateController extends Controller
{
    public function index(Federation $federation): View
    {
        Gate::authorize('federation.edit');

        $systemTemplates = MailTemplate::system()
            ->active()
            ->orderBy('group')
            ->orderBy('name')
            ->get();

        $overrides = MailTemplate::where('federation_id', $federation->id)
            ->get()
            ->keyBy(fn ($t) => $t->group . '|' . $t->lang);

        return view('federations.mail.templates', compact('federation', 'systemTemplates', 'overrides'));
    }

    public function edit(Federation $federation, MailTemplate $template): View
    {
        Gate::authorize('federation.edit');

        // Always edit/create from a system template as the base
        $system = $template->federation_id === null
            ? $template
            : MailTemplate::system()->where('group', $template->group)->where('lang', $template->lang)->firstOrFail();

        $override = MailTemplate::where('federation_id', $federation->id)
            ->where('group', $system->group)
            ->where('lang', $system->lang)
            ->first();

        return view('federations.mail.template-edit', compact('federation', 'system', 'override'));
    }

    public function store(Request $request, Federation $federation): RedirectResponse
    {
        Gate::authorize('federation.edit');

        $validated = $request->validate([
            'source_template_id' => ['required', 'uuid'],
            'subject'            => ['required', 'string', 'max:255'],
            'body'               => ['required', 'string'],
        ]);

        $source = MailTemplate::system()->findOrFail($validated['source_template_id']);

        MailTemplate::updateOrCreate(
            [
                'federation_id' => $federation->id,
                'group'         => $source->group,
                'lang'          => $source->lang,
            ],
            [
                'name'      => $source->name,
                'subject'   => $validated['subject'],
                'body'      => $validated['body'],
                'is_active' => true,
            ]
        );

        AuditLog::create([
            'user_id'    => Auth::id(),
            'action'     => 'federation_mail_template_customized',
            'new_values' => ['template' => $source->name, 'federation' => $federation->name],
            'ip_address' => $request->ip() ?? '127.0.0.1',
        ]);

        return redirect()
            ->route('federations.mail.templates.index', $federation)
            ->with('success', "Template \"{$source->name}\" saved as federation custom template.");
    }

    public function destroy(Request $request, Federation $federation, MailTemplate $template): RedirectResponse
    {
        Gate::authorize('federation.edit');

        // Safety: only delete federation-scoped templates
        abort_if($template->federation_id !== $federation->id, 403);

        $name = $template->name;
        $template->delete();

        AuditLog::create([
            'user_id'    => Auth::id(),
            'action'     => 'federation_mail_template_reset',
            'new_values' => ['template' => $name, 'federation' => $federation->name],
            'ip_address' => $request->ip() ?? '127.0.0.1',
        ]);

        return redirect()
            ->route('federations.mail.templates.index', $federation)
            ->with('success', "Template \"{$name}\" reset to system default.");
    }

    public function preview(Request $request, Federation $federation): JsonResponse
    {
        Gate::authorize('federation.edit');

        $request->validate([
            'template_id'     => ['required', 'uuid', 'exists:mail_templates,id'],
            'entity_id'       => ['nullable', 'uuid', 'exists:entities,id'],
            'subject_override' => ['nullable', 'string', 'max:255'],
            'body_override'    => ['nullable', 'string'],
        ]);

        $template = MailTemplate::findOrFail($request->template_id);

        // Allow live preview of unsaved edits by overriding subject/body
        if ($request->filled('subject_override') || $request->filled('body_override')) {
            $template = $template->replicate();
            $template->subject = $request->input('subject_override', $template->subject);
            $template->body    = $request->input('body_override', $template->body);
        }

        $entity = $request->entity_id
            ? Entity::find($request->entity_id)
            : $federation->entities()->wherePivot('status', 'active')->first();

        $rendered = app(MailTemplateService::class)->render($template, [
            'entity'     => $entity,
            'federation' => $federation,
        ]);

        return response()->json($rendered);
    }
}
