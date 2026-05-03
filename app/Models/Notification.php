<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Per-user notifications. We DON'T use the BelongsToTenant trait because
 * notifications are scoped to user_id, not company_id — only the user who
 * triggered the action sees them.
 */
class Notification extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'notifications';

    protected $fillable = [
        'user_id',
        'company_id', // kept for diagnostics, never used as a scope
        'type',       // e.g. quote.created, client.deleted
        'title',
        'message',
        'icon',       // remixicon class
        'color',      // tailwind color group: primary | emerald | amber | red | gray
        'link',       // optional URL the user lands on when clicked
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }

    public function markRead(): void
    {
        if ($this->read_at === null) {
            $this->update(['read_at' => now()]);
        }
    }
}
