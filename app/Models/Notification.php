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

    /**
     * Localised title. Maps the stable `type` key (e.g. quote.created) to a
     * translation entry; falls back to the stored English title for unknown
     * types so legacy rows still render.
     */
    public function displayTitle(): string
    {
        $key = match ($this->type) {
            'quote.created'           => 'Quote created',
            'quote.sent'              => 'Quote sent',
            'quote.won'               => 'Quote won',
            'quote.lost'              => 'Quote lost',
            'quote.deleted'           => 'Quote deleted',
            'client.created'          => 'Client added',
            'client.updated'          => 'Client updated',
            'client.deleted'          => 'Client deleted',
            'email_template.updated'  => 'Email template updated',
            default                   => null,
        };
        return $key ? __($key) : (string) $this->title;
    }
}
