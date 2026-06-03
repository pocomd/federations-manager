<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\DeliverWebhookJob;
use App\Models\AuditLog;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    public function index(): \Illuminate\View\View
    {
        Gate::authorize('federation.create');

        $endpoints = WebhookEndpoint::withCount('deliveries')
            ->with('createdBy')
            ->latest()
            ->get();

        return view('webhooks.index', compact('endpoints'));
    }

    public function create(): \Illuminate\View\View
    {
        Gate::authorize('federation.create');

        return view('webhooks.create');
    }

    /**
     * Register a new webhook endpoint with a randomly generated HMAC secret.
     *
     * The secret is generated once here and stored in plaintext — it is used to sign webhook payloads.
     */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('federation.create');

        $data = $request->validate([
            'url'         => ['required', 'url', 'max:500'],
            'events'      => ['required', 'array', 'min:1'],
            'events.*'    => ['string'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $endpoint = WebhookEndpoint::create([
            'url'         => $data['url'],
            'secret'      => Str::random(64),
            'events'      => $data['events'],
            'description' => $data['description'] ?? null,
            'active'      => true,
            'created_by'  => auth()->id(),
        ]);

        AuditLog::create([
            'user_id'    => Auth::id(),
            'entity_id'  => null,
            'action'     => 'webhook_created',
            'old_values' => null,
            'new_values' => ['url' => $data['url'], 'events' => $data['events'], 'description' => $data['description'] ?? null],
            'ip_address' => $request->ip() ?? '127.0.0.1',
        ]);

        return redirect()->route('webhooks.index')
            ->with('success', 'Webhook endpoint created.');
    }

    public function show(WebhookEndpoint $webhook): \Illuminate\View\View
    {
        Gate::authorize('federation.create');

        $deliveries = $webhook->deliveries()->latest('created_at')->paginate(25);

        return view('webhooks.show', compact('webhook', 'deliveries'));
    }

    public function destroy(WebhookEndpoint $webhook, Request $request): RedirectResponse
    {
        Gate::authorize('federation.create');

        AuditLog::create([
            'user_id'    => Auth::id(),
            'entity_id'  => null,
            'action'     => 'webhook_deleted',
            'old_values' => ['url' => $webhook->url, 'events' => $webhook->events, 'description' => $webhook->description],
            'new_values' => null,
            'ip_address' => $request->ip() ?? '127.0.0.1',
        ]);

        $webhook->delete();

        return redirect()->route('webhooks.index')
            ->with('success', 'Webhook endpoint deleted.');
    }

    /**
     * Re-queue a failed webhook delivery.
     *
     * Resets the delivery attempt counter and status to 'pending' before dispatching.
     *
     * @dispatches  DeliverWebhookJob (queued)
     */
    public function retryDelivery(WebhookEndpoint $webhook, WebhookDelivery $delivery): RedirectResponse
    {
        Gate::authorize('federation.create');

        $delivery->update(['status' => 'pending', 'attempt' => 0]);

        DeliverWebhookJob::dispatch($delivery->id);

        return redirect()->back()
            ->with('success', 'Webhook delivery queued for retry.');
    }
}
