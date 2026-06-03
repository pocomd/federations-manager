<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Entity;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * AuditLogController
 *
 * Paginated, filterable view of the audit_logs table.
 * All methods require the user.view permission (Admin / Operator).
 *
 * Filters accepted:
 *   entity_id  — UUID of the entity whose log entries to show
 *   user_id    — UUID of the acting user
 *   action     — partial-match string (e.g. "created", "updated")
 *   date_from  — ISO date string (inclusive lower bound on created_at)
 *   date_to    — ISO date string (inclusive upper bound on created_at)
 */
class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $canViewAll = Gate::check('user.view');

        $query = AuditLog::query()
            ->with(['user', 'entity'])
            ->latest('created_at');

        if ($entityId = $request->input('entity_id')) {
            $query->where('entity_id', $entityId);
        }

        if ($canViewAll) {
            if ($userId = $request->input('user_id')) {
                $query->where('user_id', $userId);
            }
        } else {
            $query->where('user_id', $request->user()->id);
        }

        if ($action = $request->input('action')) {
            $query->where('action', 'like', '%' . $action . '%');
        }

        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $logs = $query->paginate(50)->withQueryString();

        $users    = User::orderBy('name')->get(['id', 'name', 'email']);
        $entities = Entity::orderBy('entity_id')->get(['id', 'entity_id']);

        $actions = AuditLog::query()
            ->selectRaw('DISTINCT action')
            ->orderBy('action')
            ->pluck('action');

        return view('audit.index', compact('logs', 'users', 'entities', 'actions', 'canViewAll'));
    }
}
