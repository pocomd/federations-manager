<?php

declare(strict_types=1);

use App\Models\Entity;
use App\Models\Federation;
use App\Models\FederationValidator;
use App\Models\User;
use App\Services\Metadata\ExternalValidatorService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->guest = User::factory()->create();
    $this->guest->assignRole('Guest');

    $this->federation = Federation::factory()->create([
        'name'   => 'Test Federation',
        'status' => 'active',
    ]);
});

it('index returns 200 for Admin', function () {
    $this->actingAs($this->admin)
        ->get(route('federations.validators.index', $this->federation))
        ->assertOk()
        ->assertSee('Validators');
});

it('index returns 403 for Guest', function () {
    $this->actingAs($this->guest)
        ->get(route('federations.validators.index', $this->federation))
        ->assertForbidden();
});

it('store creates validator with valid data', function () {
    $this->actingAs($this->admin)
        ->post(route('federations.validators.store', $this->federation), [
            'name'                     => 'SWAMID Validator',
            'url'                      => 'https://validator.swamid.se/check',
            'http_method'              => 'GET',
            'metadata_arg_name'        => 'metadata',
            'args_separator'           => '&',
            'response_code_element'    => 'returncode',
            'response_message_element' => 'message',
            'success_value'            => '0',
            'warning_value'            => '1',
            'error_value'              => '2',
            'critical_value'           => '3',
            'enabled'                  => '1',
        ])
        ->assertRedirect(route('federations.validators.index', $this->federation))
        ->assertSessionHas('success');

    expect(FederationValidator::where('name', 'SWAMID Validator')->exists())->toBeTrue();
});

it('store rejects URL without https prefix', function () {
    $this->actingAs($this->admin)
        ->post(route('federations.validators.store', $this->federation), [
            'name'                     => 'Bad Validator',
            'url'                      => 'http://insecure.example.org/check',
            'http_method'              => 'GET',
            'metadata_arg_name'        => 'metadata',
            'args_separator'           => '&',
            'response_code_element'    => 'returncode',
            'response_message_element' => 'message',
            'success_value'            => '0',
            'warning_value'            => '1',
            'error_value'              => '2',
            'critical_value'           => '3',
        ])
        ->assertSessionHasErrors('url');
});

it('store rejects invalid http_method', function () {
    $this->actingAs($this->admin)
        ->post(route('federations.validators.store', $this->federation), [
            'name'                     => 'Bad Validator',
            'url'                      => 'https://validator.example.org/check',
            'http_method'              => 'DELETE',
            'metadata_arg_name'        => 'metadata',
            'args_separator'           => '&',
            'response_code_element'    => 'returncode',
            'response_message_element' => 'message',
            'success_value'            => '0',
            'warning_value'            => '1',
            'error_value'              => '2',
            'critical_value'           => '3',
        ])
        ->assertSessionHasErrors('http_method');
});

it('update changes validator settings', function () {
    $validator = FederationValidator::create([
        'federation_id'            => $this->federation->id,
        'name'                     => 'Old Name',
        'url'                      => 'https://validator.example.org/v1',
        'http_method'              => 'GET',
        'metadata_arg_name'        => 'metadata',
        'args_separator'           => '&',
        'response_code_element'    => 'returncode',
        'response_message_element' => 'message',
        'success_value'            => '0',
        'warning_value'            => '1',
        'error_value'              => '2',
        'critical_value'           => '3',
    ]);

    $this->actingAs($this->admin)
        ->put(route('federations.validators.update', [$this->federation, $validator]), [
            'name'                     => 'New Name',
            'url'                      => 'https://validator.example.org/v2',
            'http_method'              => 'POST',
            'metadata_arg_name'        => 'metadata',
            'args_separator'           => '&',
            'response_code_element'    => 'returncode',
            'response_message_element' => 'message',
            'success_value'            => '0',
            'warning_value'            => '1',
            'error_value'              => '2',
            'critical_value'           => '3',
        ])
        ->assertRedirect(route('federations.validators.index', $this->federation))
        ->assertSessionHas('success');

    expect($validator->fresh()->name)->toBe('New Name');
});

it('destroy removes validator', function () {
    $validator = FederationValidator::create([
        'federation_id'            => $this->federation->id,
        'name'                     => 'To Delete',
        'url'                      => 'https://validator.example.org/delete',
        'http_method'              => 'GET',
        'metadata_arg_name'        => 'metadata',
        'args_separator'           => '&',
        'response_code_element'    => 'returncode',
        'response_message_element' => 'message',
        'success_value'            => '0',
        'warning_value'            => '1',
        'error_value'              => '2',
        'critical_value'           => '3',
    ]);

    $this->actingAs($this->admin)
        ->delete(route('federations.validators.destroy', [$this->federation, $validator]))
        ->assertRedirect(route('federations.validators.index', $this->federation))
        ->assertSessionHas('success');

    expect(FederationValidator::find($validator->id))->toBeNull();
});

it('runValidator returns JSON with status field', function () {
    $validator = FederationValidator::create([
        'federation_id'            => $this->federation->id,
        'name'                     => 'Mock Validator',
        'url'                      => 'https://validator.example.org/check',
        'http_method'              => 'GET',
        'metadata_arg_name'        => 'metadata',
        'args_separator'           => '&',
        'response_code_element'    => 'returncode',
        'response_message_element' => 'message',
        'success_value'            => '0',
        'warning_value'            => '1',
        'error_value'              => '2',
        'critical_value'           => '3',
    ]);

    $entity = Entity::factory()->idp()->create();

    $this->instance(ExternalValidatorService::class, new class extends ExternalValidatorService {
        public function validateEntity(\App\Models\Entity $entity, FederationValidator $validator): array
        {
            return ['status' => 'success', 'code' => '0', 'message' => 'OK', 'raw' => ''];
        }
    });

    $this->actingAs($this->admin)
        ->postJson(route('federations.validators.run', [$this->federation, $validator]), [
            'entity_id' => $entity->id,
        ])
        ->assertOk()
        ->assertJsonStructure(['status', 'message']);
});

it('runValidator requires entity_id', function () {
    $validator = FederationValidator::create([
        'federation_id'            => $this->federation->id,
        'name'                     => 'Mock Validator',
        'url'                      => 'https://validator.example.org/check',
        'http_method'              => 'GET',
        'metadata_arg_name'        => 'metadata',
        'args_separator'           => '&',
        'response_code_element'    => 'returncode',
        'response_message_element' => 'message',
        'success_value'            => '0',
        'warning_value'            => '1',
        'error_value'              => '2',
        'critical_value'           => '3',
    ]);

    $this->actingAs($this->admin)
        ->postJson(route('federations.validators.run', [$this->federation, $validator]), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('entity_id');
});
