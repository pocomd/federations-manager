<?php

declare(strict_types=1);

use App\Models\SchedulerSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SchedulerSettingsSeeder;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SchedulerSettingsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->guest = User::factory()->create();
    $this->guest->assignRole('Guest');
});

// ── Unit: SchedulerSetting::get() ──────────────────────────────────────────

it('SchedulerSetting::get() returns default when key is missing', function () {
    expect(SchedulerSetting::get('nonexistent_key', 'fallback'))->toBe('fallback');
    expect(SchedulerSetting::get('nonexistent_key'))->toBeNull();
});

it('SchedulerSetting::get() returns typed value for existing boolean setting', function () {
    $result = SchedulerSetting::get('metadata_auto_generate_enabled', false);
    expect($result)->toBeBool()->toBeTrue();
});

it('SchedulerSetting::get() returns typed integer for existing integer setting', function () {
    $result = SchedulerSetting::get('cert_notify_days_critical', 0);
    expect($result)->toBeInt()->toBe(14);
});

// ── Unit: SchedulerSetting::set() ──────────────────────────────────────────

it('SchedulerSetting::set() persists a new value', function () {
    SchedulerSetting::set('cert_notify_days_critical', '21');

    $setting = SchedulerSetting::find('cert_notify_days_critical');
    expect($setting->value)->toBe('21');
    expect(SchedulerSetting::get('cert_notify_days_critical'))->toBe(21);
});

it('SchedulerSetting::set() toggling boolean to false stores "0"', function () {
    SchedulerSetting::set('metadata_auto_generate_enabled', '0');
    expect(SchedulerSetting::get('metadata_auto_generate_enabled'))->toBeFalse();
});

// ── Feature: Scheduler index ───────────────────────────────────────────────

it('scheduler index returns 200 for Admin', function () {
    $this->actingAs($this->admin)
        ->get(route('scheduler.index'))
        ->assertOk()
        ->assertSee('Scheduler Settings');
});

it('scheduler index returns 403 for Guest', function () {
    $this->actingAs($this->guest)
        ->get(route('scheduler.index'))
        ->assertForbidden();
});

it('scheduler update saves settings and redirects', function () {
    $this->actingAs($this->admin)
        ->post(route('scheduler.update'), [
            'cert_notify_days_critical' => '7',
            'cert_notify_days_warning'  => '21',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(SchedulerSetting::get('cert_notify_days_critical'))->toBe(7);
    expect(SchedulerSetting::get('cert_notify_days_warning'))->toBe(21);
});

it('scheduler update returns 403 for Guest', function () {
    $this->actingAs($this->guest)
        ->post(route('scheduler.update'), ['cert_notify_days_critical' => '7'])
        ->assertForbidden();
});

// ── Validation tests ───────────────────────────────────────────────────────

it('rejects invalid HH:MM time format', function () {
    $this->actingAs($this->admin)
        ->post(route('scheduler.update'), ['validation_schedule_time' => '25:99'])
        ->assertSessionHasErrors('validation_schedule_time');
});

it('rejects time input with script injection', function () {
    $this->actingAs($this->admin)
        ->post(route('scheduler.update'), ['cert_check_time' => '<script>alert(1)</script>'])
        ->assertSessionHasErrors('cert_check_time');
});

it('rejects URL without https prefix', function () {
    $this->actingAs($this->admin)
        ->post(route('scheduler.update'), ['edugain_metadata_url' => 'http://mds.edugain.org/metadata.xml'])
        ->assertSessionHasErrors('edugain_metadata_url');
});

it('rejects URL with javascript: scheme', function () {
    $this->actingAs($this->admin)
        ->post(route('scheduler.update'), ['edugain_metadata_url' => 'javascript:alert(document.cookie)'])
        ->assertSessionHasErrors('edugain_metadata_url');
});

it('rejects cert threshold outside allowed range', function () {
    $this->actingAs($this->admin)
        ->post(route('scheduler.update'), ['cert_notify_days_critical' => '999'])
        ->assertSessionHasErrors('cert_notify_days_critical');
});

it('rejects metadata interval not in allowed list', function () {
    $this->actingAs($this->admin)
        ->post(route('scheduler.update'), ['metadata_auto_generate_interval' => '7'])
        ->assertSessionHasErrors('metadata_auto_generate_interval');
});

it('accepts valid settings and saves them', function () {
    $this->actingAs($this->admin)
        ->post(route('scheduler.update'), [
            'cert_notify_days_critical'       => '7',
            'cert_notify_days_warning'        => '21',
            'validation_schedule_time'        => '03:00',
            'cert_check_time'                 => '06:00',
            'edugain_metadata_url'            => 'https://mds.edugain.org/edugain-v2.xml',
            'metadata_auto_generate_interval' => '15',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(SchedulerSetting::get('cert_notify_days_critical'))->toBe(7);
    expect(SchedulerSetting::get('cert_notify_days_warning'))->toBe(21);
});

it('boolean fields default to false when not submitted', function () {
    $this->actingAs($this->admin)
        ->post(route('scheduler.update'), [])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(SchedulerSetting::get('metadata_auto_generate_enabled'))->toBeFalse();
});
