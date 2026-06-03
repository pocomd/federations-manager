<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeliverWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 5;
    public int $timeout = 30;

    public function __construct(
        private readonly string $deliveryId,
    ) {
        $this->onQueue('low');
    }

    public function handle(): void
    {
        $delivery = WebhookDelivery::with('endpoint')->find($this->deliveryId);
        if (! $delivery || ! $delivery->endpoint) {
            return;
        }

        $endpoint = $delivery->endpoint;
        $payload  = json_encode($delivery->payload, JSON_UNESCAPED_UNICODE);
        $sig      = 'sha256=' . hash_hmac('sha256', $payload, $endpoint->secret);

        $delivery->increment('attempt');

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type'       => 'application/json',
                    'X-Hub-Signature-256' => $sig,
                    'X-Webhook-Event'    => $delivery->event,
                ])
                ->send('POST', $endpoint->url, ['body' => $payload]);

            $delivery->update([
                'http_status'   => $response->status(),
                'response_body' => substr($response->body(), 0, 4096),
                'status'        => $response->successful() ? 'delivered' : 'failed',
            ]);
        } catch (\Throwable $e) {
            $delivery->update(['status' => 'failed', 'response_body' => $e->getMessage()]);
            Log::warning('DeliverWebhookJob: delivery failed', [
                'delivery_id' => $this->deliveryId,
                'error'       => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function backoff(): array
    {
        return [60, 120, 300, 600, 1800];
    }
}
