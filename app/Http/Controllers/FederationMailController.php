<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\SendFederationMailJob;
use App\Models\Federation;
use App\Models\MailLog;
use App\Models\MailTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FederationMailController extends Controller
{
    public function compose(Federation $federation): View
    {
        Gate::authorize('federation.edit');

        // System templates + federation overrides merged: federation copy replaces system entry
        $systemTemplates     = MailTemplate::system()->active()->orderBy('group')->orderBy('name')->get();
        $federationTemplates = MailTemplate::where('federation_id', $federation->id)->active()->get()
            ->keyBy(fn ($t) => $t->group . '|' . $t->lang);

        $templates = $systemTemplates->map(function ($tpl) use ($federationTemplates) {
            $key = $tpl->group . '|' . $tpl->lang;
            return $federationTemplates->has($key)
                ? $federationTemplates[$key]->setAttribute('_is_custom', true)->setAttribute('_system_name', $tpl->name)
                : $tpl->setAttribute('_is_custom', false);
        });

        $idpCount = $federation->entities()
            ->wherePivot('status', 'active')
            ->where('type', 'idp')
            ->count();

        $spCount = $federation->entities()
            ->wherePivot('status', 'active')
            ->where('type', 'sp')
            ->count();

        $entityCount = $idpCount + $spCount;

        // Approximate email count — sum of contacts of all active entities
        $allEmailCount = $federation->entities()->wherePivot('status', 'active')
            ->withCount('contacts')->get()->sum('contacts_count');
        $idpEmailCount = $federation->entities()->wherePivot('status', 'active')
            ->where('type', 'idp')->withCount('contacts')->get()->sum('contacts_count');
        $spEmailCount  = $federation->entities()->wherePivot('status', 'active')
            ->where('type', 'sp')->withCount('contacts')->get()->sum('contacts_count');

        return view('federations.mail.compose', compact(
            'federation',
            'templates',
            'entityCount',
            'idpCount',
            'spCount',
            'allEmailCount',
            'idpEmailCount',
            'spEmailCount',
        ));
    }

    /**
     * Queue a bulk email to active federation entity contacts.
     *
     * Filters by entity type (all/idp/sp) and contact types. Runs asynchronously.
     *
     * @dispatches  SendFederationMailJob (queued)
     */
    public function send(Request $request, Federation $federation): RedirectResponse
    {
        Gate::authorize('federation.edit');

        $validated = $request->validate([
            'subject'         => ['required', 'string', 'max:255'],
            'body'            => ['required', 'string'],
            'entity_type'     => ['required', Rule::in(['all', 'idp', 'sp'])],
            'contact_types'   => ['required', 'array', 'min:1'],
            'contact_types.*' => [Rule::in(['technical', 'support', 'security', 'administrative'])],
            'template_id'     => ['nullable', 'uuid', 'exists:mail_templates,id'],
        ]);

        SendFederationMailJob::dispatch(
            $federation,
            $validated['subject'],
            $validated['body'],
            $validated['entity_type'],
            $validated['contact_types'],
            $request->user()
        );

        return redirect()
            ->route('federations.show', $federation)
            ->with('success', 'Emails queued for sending.');
    }

    public function mailLog(Federation $federation): View
    {
        Gate::authorize('federation.edit');

        $logs = MailLog::where('federation_id', $federation->id)
            ->with(['entity', 'sentBy'])
            ->latest('created_at')
            ->paginate(25);

        return view('federations.mail.log', compact('federation', 'logs'));
    }
}
