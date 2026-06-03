<?php

declare(strict_types=1);

namespace App\Services\Webhook;

use App\Jobs\DeliverWebhookJob;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;

class WebhookService
{
    public function dispatch(string $event, array $payload): void
    {
        $endpoints = WebhookEndpoint::where('active', true)->get();

        foreach ($endpoints as $endpoint) {
            if (! $endpoint->subscribesTo($event)) {
                continue;
            }

            $delivery = WebhookDelivery::create([
                'webhook_endpoint_id' => $endpoint->id,
                'event'               => $event,
                'payload'             => array_merge($payload, ['event' => $event, 'timestamp' => now()->toIso8601String()]),
                'status'              => 'pending',
                'attempt'             => 0,
            ]);

            DeliverWebhookJob::dispatch($delivery->id);
        }
    }
}
