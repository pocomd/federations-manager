<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Entity;
use App\Models\Federation;
use App\Models\FederationValidator;
use App\Services\Metadata\ExternalValidatorService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class FederationValidatorController extends Controller
{
    public function index(Federation $federation): View
    {
        Gate::authorize('federation.edit');

        $validators = $federation->validators()->get();

        return view('federations.validators.index', compact('federation', 'validators'));
    }

    public function create(Federation $federation): View
    {
        Gate::authorize('federation.edit');

        return view('federations.validators.create', compact('federation'));
    }

    public function store(Request $request, Federation $federation): RedirectResponse
    {
        Gate::authorize('federation.edit');

        $data = $request->merge([
            'enabled'                 => $request->boolean('enabled'),
            'enabled_on_registration' => $request->boolean('enabled_on_registration'),
            'mandatory'               => $request->boolean('mandatory'),
        ])->validate($this->rules());

        $validator = FederationValidator::create([
            'federation_id' => $federation->id,
            ...$data,
        ]);

        AuditLog::create([
            'user_id'    => Auth::id(),
            'action'     => 'validator_created',
            'model_type' => FederationValidator::class,
            'model_id'   => $validator->id,
            'changes'    => json_encode(['name' => $validator->name]),
            'ip_address' => request()->ip() ?? '127.0.0.1',
        ]);

        $redirect = redirect()
            ->route('federations.validators.index', $federation)
            ->with('success', "Validator \"{$validator->name}\" created.");

        if ($request->has('_open_test')) {
            $redirect = $redirect->with('auto_test_validator', [
                'id'   => $validator->id,
                'name' => $validator->name,
            ]);
        }

        return $redirect;
    }

    public function edit(Federation $federation, FederationValidator $validator): View
    {
        Gate::authorize('federation.edit');

        return view('federations.validators.edit', compact('federation', 'validator'));
    }

    public function update(Request $request, Federation $federation, FederationValidator $validator): RedirectResponse
    {
        Gate::authorize('federation.edit');

        $data = $request->merge([
            'enabled'                 => $request->boolean('enabled'),
            'enabled_on_registration' => $request->boolean('enabled_on_registration'),
            'mandatory'               => $request->boolean('mandatory'),
        ])->validate($this->rules());

        $validator->update($data);

        AuditLog::create([
            'user_id'    => Auth::id(),
            'action'     => 'validator_updated',
            'model_type' => FederationValidator::class,
            'model_id'   => $validator->id,
            'changes'    => json_encode(['name' => $validator->name]),
            'ip_address' => request()->ip() ?? '127.0.0.1',
        ]);

        return redirect()
            ->route('federations.validators.index', $federation)
            ->with('success', "Validator \"{$validator->name}\" updated.");
    }

    public function destroy(Federation $federation, FederationValidator $validator): RedirectResponse
    {
        Gate::authorize('federation.edit');

        $name = $validator->name;
        $id   = $validator->id;

        $validator->delete();

        AuditLog::create([
            'user_id'    => Auth::id(),
            'action'     => 'validator_deleted',
            'model_type' => FederationValidator::class,
            'model_id'   => $id,
            'changes'    => json_encode(['name' => $name]),
            'ip_address' => request()->ip() ?? '127.0.0.1',
        ]);

        return redirect()
            ->route('federations.validators.index', $federation)
            ->with('success', "Validator \"{$name}\" deleted.");
    }

    public function runValidator(Request $request, Federation $federation, FederationValidator $validator): JsonResponse
    {
        Gate::authorize('federation.edit');

        $request->validate([
            'entity_id' => ['required', 'uuid', 'exists:entities,id'],
        ]);

        $entity = Entity::findOrFail($request->entity_id);
        $result = app(ExternalValidatorService::class)->validateEntity($entity, $validator);

        return response()->json($result);
    }

    private function rules(): array
    {
        return [
            'name'                     => ['required', 'string', 'max:255'],
            'description'              => ['nullable', 'string', 'max:1000'],
            'url'                      => ['required', 'url', 'starts_with:https://'],
            'http_method'              => ['required', Rule::in(['GET', 'POST'])],
            'metadata_arg_name'        => ['required', 'string', 'max:100', 'alpha_dash'],
            'optional_args'            => ['nullable', 'string', 'max:500'],
            'args_separator'           => ['required', 'string', 'max:10'],
            'timeout'                  => ['required', 'integer', 'min:5', 'max:120'],
            'response_code_element'    => ['required', 'string', 'max:100'],
            'response_message_element' => ['required', 'string', 'max:100'],
            'success_value'            => ['required', 'string', 'max:10'],
            'warning_value'            => ['required', 'string', 'max:10'],
            'error_value'              => ['required', 'string', 'max:10'],
            'critical_value'           => ['required', 'string', 'max:10'],
            'enabled'                  => ['boolean'],
            'enabled_on_registration'  => ['boolean'],
            'mandatory'                => ['boolean'],
        ];
    }
}
