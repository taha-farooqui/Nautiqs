<?php

namespace App\Models;

use App\Casts\EncryptedPii;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Pii\PiiCipher;
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
        'attachments',        // extra files the dealer attached: [{name, size}]
        'status',             // sent | failed
        'error_message',      // null on success
        'sent_by_user_id',
        'sent_by_user_name',  // denormalised so deleting the user doesn't blank the log
        'sent_at',
        'automated',          // true when sent by the follow-up scheduler (no human actor)
    ];

    /**
     * Encrypted at rest. The body and subject are the email exactly as sent,
     * so they carry the client's name and whatever a dealer-edited template
     * put in them; the SMTP error is here because a rejection routinely
     * quotes the address back ("550 <jean@…>: recipient rejected"). Left
     * readable: the dealer's own reply-to address, the quote number, and the
     * type and status the page filters on.
     */
    public const PII = ['to_email', 'to_name', 'cc', 'subject', 'body_html', 'error_message'];

    protected $casts = [
        'sent_at'       => 'datetime',
        'automated'     => 'boolean',
        'to_email'      => EncryptedPii::class,
        'to_name'       => EncryptedPii::class,
        'cc'            => EncryptedPii::class,
        'subject'       => EncryptedPii::class,
        'body_html'     => EncryptedPii::class,
        'error_message' => EncryptedPii::class,
    ];

    public function quote()
    {
        return $this->belongsTo(Quote::class, 'quote_id');
    }

    /**
     * Extra files attached to a send: [{name, size}]. A filename is very
     * often the client's name ("CNI-Dupont.jpg"), so each name is encrypted;
     * the size is not personal and stays readable.
     *
     * Accessor and mutator rather than a cast: a cast cannot reach inside an
     * embedded array, and an 'array' cast would store it as a JSON string,
     * which is the bug the August snapshot repair had to undo.
     */
    public function getAttachmentsAttribute($value): array
    {
        return array_map(
            fn ($a) => is_array($a) ? PiiCipher::decryptKeys($a, ['name']) : $a,
            self::asList($value)
        );
    }

    public function setAttachmentsAttribute($value): void
    {
        $this->attributes['attachments'] = array_map(
            fn ($a) => is_array($a) ? PiiCipher::encryptKeys($a, ['name']) : $a,
            self::asList($value)
        );
    }

    /** @return array<int, mixed> */
    private static function asList($value): array
    {
        if ($value instanceof \Traversable) {
            $value = iterator_to_array($value);
        }
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_map(
            fn ($a) => $a instanceof \Traversable ? iterator_to_array($a) : $a,
            $value
        ));
    }
}
