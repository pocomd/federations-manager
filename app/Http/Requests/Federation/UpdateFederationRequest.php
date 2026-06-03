<?php

declare(strict_types=1);

namespace App\Http\Requests\Federation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * UpdateFederationRequest
 *
 * Validates the payload for updating an existing federation.
 * The `uri` uniqueness rule ignores the current federation record so
 * a no-change update does not trigger a uniqueness violation.
 */
class UpdateFederationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gate checked in controller
    }

    public function rules(): array
    {
        /** @var \App\Models\Federation $federation */
        $federation = $this->route('federation');

        return [
            'name' => ['required', 'string', 'max:255'],

            'description' => ['nullable', 'string', 'max:4096'],

            'uri' => [
                'required',
                'string',
                'max:512',
                'url',
                Rule::unique('federations', 'uri')->ignore($federation?->id),
            ],

            'status' => ['sometimes', Rule::in(['active', 'inactive'])],

            'metadata_url' => ['nullable', 'url', 'max:1024'],
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
