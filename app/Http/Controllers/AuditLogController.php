<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    /**
     * Display a paginated, filterable list of audit log entries.
     */
    public function index(Request $request): View
    {
        Gate::authorize('access-audit-logs');

        $query = AuditLog::query()->with('user')->latest('created_at');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('action')) {
            $query->where('action', 'like', '%'.$request->input('action').'%');
        }

        if ($request->filled('model_type')) {
            $query->where('model_type', $request->input('model_type'));
        }

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->input('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->input('end_date'));
        }

        $logs = $query->paginate(25)->withQueryString();

        // For the filter dropdowns.
        $users = User::orderBy('name')->get(['id', 'name']);
        $actions = AuditLog::query()->select('action')->distinct()->orderBy('action')->pluck('action');
        $modelTypes = AuditLog::query()->whereNotNull('model_type')->select('model_type')->distinct()->orderBy('model_type')->pluck('model_type');

        return view('audit-logs.index', compact('logs', 'users', 'actions', 'modelTypes'));
    }
}
