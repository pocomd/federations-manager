<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\Webhook\WebhookService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
});

it('index returns 200 for Admin', function () {
    $this->actingAs($this->admin)
        ->get(route('webhooks.index'))
        ->assertOk()
        ->assertViewIs('webhooks.index');
});

it('index returns 403 for Guest', function () {
    $guest = User::factory()->create();
    $guest->assignRole('Guest');

    $this->actingAs($guest)
        ->get(route('webhooks.index'))
        ->assertForbidden();
});

it('store creates a webhook endpoint and redirects', function () {
    $this->actingAs($this->admin)
        ->post(route('webhooks.store'), [
            'url'    => 'https://example.com/hook',
            'events' => ['entity.created', 'entity.approved'],
        ])
        ->assertRedirect(route('webhooks.index'));

    expect(WebhookEndpoint::where('url', 'https://example.com/hook')->exists())->toBeTrue();
});

it('dispatch sends delivery job via http fake', function () {
    Http::fake(['https://example.com/hook' => Http::response('ok', 200)]);
    Queue::fake();

    $endpoint = WebhookEndpoint::create([
        'url'    => 'https://example.com/hook',
        'secret' => str_repeat('a', 64),
        'events' => ['entity.created'],
        'active' => true,
    ]);

    app(WebhookService::class)->dispatch('entity.created', ['entity_id' => 'https://test.example.com/sp']);

    $delivery = WebhookDelivery::where('webhook_endpoint_id', $endpoint->id)->first();
    expect($delivery)->not->toBeNull();
    expect($delivery->event)->toBe('entity.created');
});
