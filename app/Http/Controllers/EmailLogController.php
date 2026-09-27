<?php

namespace App\Http\Controllers;

use App\Models\EmailLog;
use App\Support\Pii\Search;
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

        // Recipient, name and subject are encrypted, so a search runs in PHP
        // over the rows the type and status filters already let through.
        $q = trim((string) $request->query('q', ''));
        $logs = $q === ''
            ? $query->paginate(25)->withQueryString()
            : Search::paginate(
                Search::filter($query->get(), $q, fn (EmailLog $l) => [
                    $l->to_email, $l->to_name, $l->subject, $l->quote_number,
                ]),
                $request,
                25
            );

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
