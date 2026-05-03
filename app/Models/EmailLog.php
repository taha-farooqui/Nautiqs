<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Spec §3 EMAIL_LOG — append-only audit trail for every email the
 * dealership sends to a client. Used by the quote-show page to detect
 * "already sent" state, and by the Email log page to power filters.
 *
 * One row per send attempt (not per quote). Re-sending the same quote
 * adds a new row so the history is preserved.
 */
class EmailLog extends Model
{
    use BelongsToTenant;

    protected $connection = 'mongodb';
    protected $collection = 'email_log';

    public const STATUS_SENT   = 'sent';
    public const STATUS_FAILED = 'failed';

    public const TYPE_QUOTE              = 'quote';
    public const TYPE_ORDER_CONFIRMATION = 'order_confirmation';
    public const TYPE_FOLLOW_UP          = 'follow_up';

    protected $fillable = [
        'company_id',
        'quote_id',           // Mongo id of the quote this email is about (nullable for ad-hoc)
        'quote_number',       // denormalised reference for display
        'type',               // quote | order_confirmation | follow_up
        'to_email',
        'to_name',
        'cc',                 // comma-separated extras (rare today; reserved)
        'reply_to_email',
        'subject',
        'body_html',          // exactly what was sent — letting us show "what did the client see?"
        'attachment_filename',// e.g. Q-2026-001.pdf — we don't store the bytes
        'status',             // sent | failed
        'error_message',      // null on success
        'sent_by_user_id',
        'sent_by_user_name',  // denormalised so deleting the user doesn't blank the log
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function quote()
    {
        return $this->belongsTo(Quote::class, 'quote_id');
    }
}
