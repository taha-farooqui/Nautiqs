<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Spec §3 EMAIL_TEMPLATE + §14. One template per dealership, used for every
 * outgoing client email (quote sending, order confirmation, follow-up).
 *
 * Variables substituted at send time:
 *   {{client_name}} {{client_first_name}} {{quote_number}}
 *   {{order_number}} {{boat_model}} {{total_ttc}}
 *   {{salesperson_name}} {{company_name}} {{date}}
 */
class EmailTemplate extends Model
{
    use BelongsToTenant;

    protected $connection = 'mongodb';
    protected $collection = 'email_templates';

    protected $fillable = [
        'company_id',
        'type',     // currently always 'default' — kept for forward compatibility
        'subject',
        'body',     // HTML produced by Trix
    ];
}
