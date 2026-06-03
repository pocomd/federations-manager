<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Federation;
use App\Models\FederationRequiredAttribute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class FederationRequiredAttributeController extends Controller
{
    public function store(Request $request, Federation $federation): RedirectResponse
    {
        Gate::authorize('federation.edit');

        $data = $request->validate([
            'attribute_definition_id' => ['required', 'uuid', 'exists:attribute_definitions,id'],
            'is_required'             => ['required', 'boolean'],
            'notes'                   => ['nullable', 'string', 'max:500'],
        ]);

        $existing = $federation->requiredAttributes()
            ->where('attribute_definition_id', $data['attribute_definition_id'])
            ->exists();

        if ($existing) {
            return redirect()
                ->route('federations.show', $federation)
                ->with('error', 'That attribute is already listed for this federation.');
        }

        $ra = FederationRequiredAttribute::create([
            'federation_id'           => $federation->id,
            'attribute_definition_id' => $data['attribute_definition_id'],
            'is_required'             => $data['is_required'],
            'notes'                   => $data['notes'] ?? null,
        ]);

        AuditLog::create([
            'user_id'    => Auth::id(),
            'action'     => 'federation_attribute_added',
            'model_type' => FederationRequiredAttribute::class,
            'model_id'   => $ra->id,
            'changes'    => json_encode(['federation_id' => $federation->id]),
            'ip_address' => request()->ip() ?? '127.0.0.1',
        ]);

        return redirect()
            ->route('federations.show', $federation)
            ->with('success', 'Attribute requirement added.');
    }

    public function destroy(Federation $federation, FederationRequiredAttribute $attribute): RedirectResponse
    {
        Gate::authorize('federation.edit');

        $id = $attribute->id;
        $attribute->delete();

        AuditLog::create([
            'user_id'    => Auth::id(),
            'action'     => 'federation_attribute_removed',
            'model_type' => FederationRequiredAttribute::class,
            'model_id'   => $id,
            'changes'    => json_encode(['federation_id' => $federation->id]),
            'ip_address' => request()->ip() ?? '127.0.0.1',
        ]);

        return redirect()
            ->route('federations.show', $federation)
            ->with('success', 'Attribute requirement removed.');
    }
}
