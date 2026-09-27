<?php

namespace App\Support\Pii;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Search and sort over encrypted fields, done in PHP.
 *
 * The database cannot look inside encrypted values: the same surname is a
 * different string every time it is stored, so a `like` or an `orderBy` on it
 * matches nothing and sorts at random. The dealer's own rows are loaded
 * instead — the tenant scope still applies — decrypted by the model, and
 * filtered and sorted here.
 *
 * That is cheap at dealership scale: the largest catalogue of clients on the
 * platform today is 14, and a dealer with a few thousand is still well under
 * a tenth of a second. If one ever outgrows that, the next step is a keyed
 * hash of each searchable value stored beside it (a "blind index"), which
 * gives exact-match search in the database at the cost of partial matches.
 *
 * Matching is a superset of what the database `like` did: case-blind as
 * before, now accent-blind too ("helene" finds "Hélène"), and a query that is
 * a phone number matches on digits alone ("0628" finds "06 28 92 21 93").
 * Nothing the old search found is missed.
 */
final class Search
{
    /**
     * Keep the rows in which the query appears in any of the row's values.
     * Order is preserved, so a query that arrives sorted stays sorted.
     *
     * @param  callable(mixed): array<int, mixed>  $values
     */
    public static function filter(Collection $rows, string $q, callable $values): Collection
    {
        $needle = self::fold($q);
        if ($needle === '') {
            return $rows;
        }

        $digits = self::phoneDigits($q);

        return $rows->filter(function ($row) use ($values, $needle, $digits) {
            foreach ($values($row) as $v) {
                if ($v === null || $v === '') {
                    continue;
                }
                if (str_contains(self::fold((string) $v), $needle)) {
                    return true;
                }
                if ($digits !== null && str_contains(self::phoneDigits((string) $v, true) ?? '', $digits)) {
                    return true;
                }
            }
            return false;
        })->values();
    }

    /** Surname then first name, filed the way a French speaker files them. */
    public static function nameKey(?string $last, ?string $first): string
    {
        return self::fold(trim(($last ?? '') . ' ' . ($first ?? '')));
    }

    /** A page of an in-memory list, shaped like the database paginator the views already use. */
    public static function paginate(Collection $items, Request $request, int $perPage): LengthAwarePaginator
    {
        $page = max(1, (int) $request->query('page', 1));

        return new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
    }

    /** Lowercase, accents stripped, whitespace collapsed. */
    public static function fold(?string $s): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', Str::ascii((string) $s))));
    }

    /**
     * The digits of a phone-number-shaped string, with a French +33 prefix
     * brought back to the leading 0 it stands for, so "+33 6 28…" and
     * "06 28…" compare equal. For the query, null unless it really is a phone
     * number — three digits or more and nothing but digits and separators —
     * so that searching "2026" does not start matching every phone number
     * with a 2 in it.
     */
    private static function phoneDigits(string $s, bool $anyValue = false): ?string
    {
        if (! $anyValue && ! preg_match('/^[\d\s.\-+()\/]+$/', trim($s))) {
            return null;
        }

        $d = preg_replace('/\D/', '', $s);

        // Only an explicit international prefix is rewritten, so a partial
        // "+33 6 28" matches too, and a number that merely begins 33 does not.
        $compact = preg_replace('/\s/', '', $s);
        if (str_starts_with($compact, '+33')) {
            $d = '0' . substr($d, 2);
        } elseif (str_starts_with($compact, '0033')) {
            $d = '0' . substr($d, 4);
        }

        if (strlen($d) < 3) {
            return $anyValue ? $d : null;
        }

        return $d;
    }
}
