<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Platform-provided equipment library. These are the standard / free-text
 * items dealers tick on a boat's "Included equipment" list (Bimini, GPS
 * chartplotter, anchor windlass, etc.). Grouped into sub-categories to
 * match the old software's tab strip (Exterior / Interior / Mooring / …).
 *
 * NOT tenant-scoped.
 */
class GlobalEquipment extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'global_equipment';

    public const CATEGORIES = [
        'exterior'    => 'Exterior',
        'interior'    => 'Interior',
        'mooring'     => 'Mooring',
        'sails'       => 'Sails',
        'electronics' => 'Electronics',
        'electrical'  => 'Electrical',
        'other'       => 'Other',
    ];

    protected $fillable = [
        'category',       // see CATEGORIES
        'label',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
