<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

/**
 * Activity log — paginated view of every destructive superadmin action.
 * Read-only. Filters by action, target type, and free-text actor email.
 */
class AuditController extends Controller
{
    public function index(Request $request)
    {
        $q          = trim((string) $request->query('q', ''));
        $action     = trim((string) $request->query('action', ''));
        $targetType = trim((string) $request->query('target_type', ''));

        $query = AuditLog::orderBy('created_at', 'desc');

        if ($q !== '') {
            $regex = ['$regex' => preg_quote($q, '/'), '$options' => 'i'];
            $query->where(function ($w) use ($regex) {
                $w->whereRaw(['actor_email'  => $regex])
                  ->orWhereRaw(['target_label' => $regex])
                  ->orWhereRaw(['action'       => $regex]);
            });
        }
        if ($action !== '')      $query->where('action', $action);
        if ($targetType !== '')  $query->where('target_type', $targetType);

        $events = $query->paginate(40)->withQueryString();

        // Distinct facets for the filter dropdowns.
        $allActions     = AuditLog::distinct()->get(['action'])->pluck('action')->filter()->sort()->values();
        $allTargetTypes = AuditLog::distinct()->get(['target_type'])->pluck('target_type')->filter()->sort()->values();

        return view('admin.audit.index', compact('events', 'q', 'action', 'targetType', 'allActions', 'allTargetTypes'));
    }
}
