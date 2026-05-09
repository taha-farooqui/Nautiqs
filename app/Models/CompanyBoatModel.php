<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Spec §7.1 — a model inside a dealership's workspace.
 *
 * `global_model_id` is set when the model originated from the platform
 * catalogue (so future catalogue updates can match by global id rather
 * than by code, which the dealer is free to rename).
 */
class CompanyBoatModel extends Model
{
    use BelongsToTenant;

    protected $connection = 'mongodb';
    protected $collection = 'company_boat_models';

    public const SOURCE_GLOBAL  = 'global';
    public const SOURCE_PRIVATE = 'private';

    // Hull / propulsion enums modelled after the old software's pickers.
    public const TYPES = ['open', 'cabin', 'semi-rigid', 'day-cruiser', 'fishing', 'sail', 'unknown'];
    public const PROPULSIONS = ['outboard', 'inboard', 'sail', 'unknown'];

    protected $fillable = [
        'company_id',
        'company_brand_id',
        'global_model_id',    // null for private
        'source',             // global | private

        // Identity
        'code',                // Fabricant code (e.g. IDEA60)
        'internal_code',       // Interne — the dealer's own ref
        'name',                // commercial name (e.g. 60 OPEN LINE)
        'complement',          // sub-name shown next to Modèle (e.g. Cabin/Open variant of same hull)
        'year',                // model year

        // Classification
        'type',                // open | cabin | semi-rigid | day-cruiser | fishing | sail | unknown
        'propulsion',          // outboard | inboard | sail | unknown

        // Dimensions (metres)
        'length_total',
        'length_hull',
        'length_waterline',
        'draft_min',
        'draft_max',
        'beam',                // largeur
        'weight',              // kg

        // Capacity grid — cells from the old software's A/B/C/D matrix:
        //   passengers row (A,B,C,D) and passengers+luggage row (A,B,C,D)
        'capacity',            // ['passengers' => [a,b,c,d], 'passengers_luggage' => [a,b,c,d]]

        // Equipment — list of ticked equipment ids drawn from the merged
        // global library + per-company private equipment. Each item is a
        // pair: ['source' => 'global'|'private', 'id' => '...'].
        'included_equipment_refs',

        // Sourcing / commercial
        'supplier',            // Fournisseur (importer)
        'notes',               // free text
        'default_margin_pct',
        'is_active',           // active = visible in quote builder
        'is_archived',
    ];

    protected $casts = [
        'year'              => 'integer',
        'length_total'      => 'float',
        'length_hull'       => 'float',
        'length_waterline'  => 'float',
        'draft_min'         => 'float',
        'draft_max'         => 'float',
        'beam'              => 'float',
        'weight'            => 'float',
        'capacity'          => 'array',
        'included_equipment_refs' => 'array',
        'default_margin_pct' => 'float',
        'is_active'          => 'boolean',
        'is_archived'        => 'boolean',
    ];

    public function brand()
    {
        return $this->belongsTo(CompanyBrand::class, 'company_brand_id');
    }

    public function variants()
    {
        return $this->hasMany(CompanyBoatVariant::class, 'company_model_id');
    }

    public function options()
    {
        return $this->hasMany(CompanyOption::class, 'company_model_id');
    }
}
