<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Per-dealership, per-year atomic counter for Q-YYYY-NNN (quotes) and
 * BC-YYYY-NNN (order confirmations). Resets each year per spec §11.1.
 *
 * Document shape:
 *   { company_id, type: 'quote'|'order', year: 2026, last: 42 }
 */
class QuoteCounter extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'quote_counters';

    protected $fillable = ['company_id', 'type', 'year', 'last'];

    protected $casts = [
        'year' => 'integer',
        'last' => 'integer',
    ];

    /**
     * Atomically claim the next number for (company, type, year).
     * Returns the formatted reference string.
     */
    public static function nextReference(string $companyId, string $type = 'quote', ?int $year = null): string
    {
        $year ??= (int) date('Y');

        $collection = (new self)->getConnection()
            ->getCollection('quote_counters');

        $result = $collection->findOneAndUpdate(
            ['company_id' => $companyId, 'type' => $type, 'year' => $year],
            ['$inc' => ['last' => 1]],
            [
                'upsert'         => true,
                'returnDocument' => \MongoDB\Operation\FindOneAndUpdate::RETURN_DOCUMENT_AFTER,
            ]
        );

        $n = $result['last'] ?? 1;
        $prefix = $type === 'order' ? 'BC' : 'Q';

        return sprintf('%s-%d-%03d', $prefix, $year, $n);
    }
}
