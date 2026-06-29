<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Per-dealership atomic counter for document references.
 *   - Quotes:  D-YYYY-MM-XX  — resets every MONTH so the running number never
 *              reveals total volume (keyed by company + year + month).
 *   - Orders:  BC-YYYY-NNN   — resets each year (keyed by company + year).
 *
 * Document shape:
 *   { company_id, type: 'quote'|'order', year: 2026, month?: 6, last: 42 }
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
    public static function nextReference(string $companyId, string $type = 'quote', ?int $year = null, ?int $month = null): string
    {
        $year ??= (int) date('Y');

        // Quotes count per month (D-YYYY-MM-XX); orders count per year (BC-YYYY-NNN).
        $isQuote = $type !== 'order';
        if ($isQuote) {
            $month ??= (int) date('n');
        }

        $filter = ['company_id' => $companyId, 'type' => $type, 'year' => $year];
        if ($isQuote) {
            $filter['month'] = $month;
        }

        $collection = (new self)->getConnection()
            ->getCollection('quote_counters');

        $result = $collection->findOneAndUpdate(
            $filter,
            ['$inc' => ['last' => 1]],
            [
                'upsert'         => true,
                'returnDocument' => \MongoDB\Operation\FindOneAndUpdate::RETURN_DOCUMENT_AFTER,
            ]
        );

        $n = $result['last'] ?? 1;

        if ($isQuote) {
            // D-2026-06-01, D-2026-06-02, … resets to 01 next month.
            return sprintf('D-%d-%02d-%02d', $year, $month, $n);
        }

        return sprintf('BC-%d-%03d', $year, $n);
    }
}
