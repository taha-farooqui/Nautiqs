<?php

namespace App\Http\Controllers;

use App\Models\EmailLog;
use Illuminate\Http\Request;

/**
 * Spec §3 EMAIL_LOG — read-only audit trail. Lists every outbound email
 * the dealership has sent, with filters and a per-row "view body" drawer.
 */
class EmailLogController extends Controller
{
    public function index(Request $request)
    {
        $query = EmailLog::query()->orderBy('sent_at', 'desc');

        $type = $request->query('type');
        if ($type && in_array($type, [EmailLog::TYPE_QUOTE, EmailLog::TYPE_ORDER_CONFIRMATION, EmailLog::TYPE_FOLLOW_UP], true)) {
            $query->where('type', $type);
        }

        $status = $request->query('status');
        if ($status && in_array($status, [EmailLog::STATUS_SENT, EmailLog::STATUS_FAILED], true)) {
            $query->where('status', $status);
        }

        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('to_email',     'like', "%{$q}%")
                  ->orWhere('to_name',    'like', "%{$q}%")
                  ->orWhere('subject',    'like', "%{$q}%")
                  ->orWhere('quote_number','like', "%{$q}%");
            });
        }

        $logs = $query->paginate(25)->withQueryString();

        $counts = [
            'all'                 => EmailLog::count(),
            'sent'                => EmailLog::where('status', EmailLog::STATUS_SENT)->count(),
            'failed'              => EmailLog::where('status', EmailLog::STATUS_FAILED)->count(),
            'quote'               => EmailLog::where('type', EmailLog::TYPE_QUOTE)->count(),
            'order_confirmation'  => EmailLog::where('type', EmailLog::TYPE_ORDER_CONFIRMATION)->count(),
            'follow_up'           => EmailLog::where('type', EmailLog::TYPE_FOLLOW_UP)->count(),
        ];

        return view('email-log.index', [
            'logs'   => $logs,
            'counts' => $counts,
            'type'   => $type,
            'status' => $status,
            'q'      => $q,
        ]);
    }

    public function show(string $id)
    {
        $log = EmailLog::findOrFail($id);
        return view('email-log.show', compact('log'));
    }
}
