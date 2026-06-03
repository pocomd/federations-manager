<?php

declare(strict_types=1);

namespace App\Http\Requests\Federation;

use App\Services\Signing\SigningDriverFactory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * StoreFederationRequest
 *
 * Validates the payload for creating a new federation.
 * The `uri` field maps to the SAML2 registrationAuthority — it must be a
 * globally unique HTTPS URI identifying this federation in metadata.
 */
class StoreFederationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gate checked in controller
    }

    public function rules(): array
    {
        return [
            // Federation display name — shown in UI and published metadata
            'name' => ['required', 'string', 'max:255'],

            // Human-readable description of the federation's scope and purpose
            'description' => ['nullable', 'string', 'max:4096'],

            // Registration authority URI — must be globally unique HTTPS URI
            // Maps to mdrpi:RegistrationInfo @registrationAuthority in member metadata
            'uri' => [
                'required',
                'string',
                'max:512',
                'url',
                Rule::unique('federations', 'uri'),
            ],

            // Lifecycle status — defaults to active on creation
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],

            // URL where this federation publishes its aggregate metadata XML
            'metadata_url' => ['nullable', 'url', 'max:1024'],

            // Signing backend — must be one of the active drivers
            'signing_driver' => [
                'required',
                'string',
                Rule::in(array_keys(app(SigningDriverFactory::class)->activeDrivers())),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'uri.unique' => 'A federation with this registration authority URI already exists.',
            'uri.url'    => 'The registration authority URI must be a valid URL.',
        ];
    }
}
