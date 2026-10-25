<?php

namespace App\Services\Brand;

use App\Models\BrandAccount;
use App\Models\Company;
use App\Models\Quote;

/**
 * The only door between a dealer's quote and a brand's screen.
 *
 * Builds a plain array holding what the dealer agreed to share — dealer,
 * salesperson, boat, lines, discounts, amounts, status, dates — and nothing
 * else. Brand views receive these arrays, never the Quote: no client
 * identity (only "private" / "company"), no purchase price or margin, no
 * internal notes, no trade-in. Other brands' lines are summed into one.
 */
class BrandQuotePresenter
{
    /** @var array<string, string> */
    private array $companyNames = [];

    public function __construct(private BrandAccount $account) {}

    /** Quotes carrying this account's products (any dealer, any status). */
    public function query()
    {
        return Quote::withoutGlobalScopes()->where('brand_account_ids', (string) $this->account->_id);
    }

    public function summary(Quote $q): array
    {
        $acc = (string) $this->account->_id;
        [$gross, $net] = $this->brandAmounts($q);

        return [
            'id'          => (string) $q->_id,
            'number'      => $q->number,
            'date'        => $q->created_at,
            'status'      => $q->status,
            'company_id'  => (string) $q->company_id,
            'dealer'      => $this->companyName((string) $q->company_id),
            'salesperson' => $q->created_by_name,
            'boat'        => trim(($q->model_snapshot['brand'] ?? '') . ' ' . ($q->model_snapshot['name'] ?? '')),
            'version'     => $q->variant_snapshot['name'] ?? null,
            'managed_brand_id' => $q->model_snapshot['managed_brand_id'] ?? null,
            'is_boat'     => ($q->model_snapshot['brand_account_id'] ?? null) === $acc,
            'engines'     => collect($q->options ?? [])->where('source', 'engine')
                                ->filter(fn ($r) => ($r['brand_account_id'] ?? null) === $acc)->pluck('label')->values()->all(),
            'client'      => $this->clientType($q),
            'value_ht'    => round($net, 2),
            'gross_ht'    => round($gross, 2),
            'discount_pct'=> $gross > 0 ? round((1 - $net / $gross) * 100, 1) : 0.0,
            'total_ht'    => (float) ($q->totals['total_ht'] ?? 0),
            'won_at'      => $q->won_at,
            'lost_at'     => $q->lost_at,
            'sent_at'     => $q->sent_at,
            'expires_at'  => $q->expires_at,
        ];
    }

    /** The full detail for one quote: this brand's lines, the rest summed. */
    public function detail(Quote $q): array
    {
        $acc = (string) $this->account->_id;
        $t = $q->totals ?? [];
        $lines = [];
        $otherCount = 0; $otherTotal = 0.0;

        if (($q->model_snapshot['brand_account_id'] ?? null) === $acc) {
            $gross = (float) ($t['base_price_gross'] ?? 0);
            $net   = (float) ($t['base_ht'] ?? 0);
            $lines[] = ['label' => trim(($q->variant_snapshot['name'] ?? '') ?: ($q->model_snapshot['name'] ?? '')), 'kind' => 'boat',
                'qty' => 1, 'unit' => $gross, 'discount_pct' => $gross > 0 ? round((1 - $net / $gross) * 100, 1) : 0, 'total' => $net];
        } else {
            $otherCount++; $otherTotal += (float) ($t['base_ht'] ?? 0);
        }

        foreach ($q->options ?? [] as $r) {
            if (($r['brand_account_id'] ?? null) === $acc) {
                $item = (float) ($r['item_discount_pct'] ?? 0);
                $cat  = (float) ($r['cat_discount_pct'] ?? 0);
                $lines[] = ['label' => $r['label'] ?? '', 'kind' => $r['source'] ?? 'option', 'category' => $r['category'] ?? null,
                    'qty' => (int) ($r['quantity'] ?? 1), 'unit' => (float) ($r['unit_price'] ?? 0),
                    'discount_pct' => round((1 - (1 - $item / 100) * (1 - $cat / 100)) * 100, 1),
                    'total' => (float) ($r['line_after_cat'] ?? 0)];
            } else {
                $otherCount++; $otherTotal += (float) ($r['line_after_cat'] ?? 0);
            }
        }
        foreach ($t['custom_items_rows'] ?? [] as $r) {
            $otherCount++; $otherTotal += (float) ($r['line_after_cat'] ?? 0);
        }

        $globalPct = (float) ($t['global_discount_pct'] ?? 0);

        return $this->summary($q) + [
            'lines'        => $lines,
            'other_count'  => $otherCount,
            'other_total'  => round($otherTotal, 2),
            'global_discount_pct' => $globalPct,
            'level'        => null,
        ];
    }

    /** Gross and net (after line discounts) of this account's products on a quote. */
    private function brandAmounts(Quote $q): array
    {
        $acc = (string) $this->account->_id;
        $t = $q->totals ?? [];
        $gross = 0.0; $net = 0.0;
        if (($q->model_snapshot['brand_account_id'] ?? null) === $acc) {
            $gross += (float) ($t['base_price_gross'] ?? 0);
            $net   += (float) ($t['base_ht'] ?? 0);
        }
        foreach ($q->options ?? [] as $r) {
            if (($r['brand_account_id'] ?? null) === $acc) {
                $gross += (float) ($r['line_gross'] ?? 0);
                $net   += (float) ($r['line_after_cat'] ?? 0);
            }
        }

        return [$gross, $net];
    }

    /** Private client / company — never who. */
    private function clientType(Quote $q): string
    {
        $snap = $q->client_snapshot ?? [];
        if (! empty($snap['is_guest']) && empty($snap['first_name']) && empty($snap['company_name'])) {
            return __('Client not specified');
        }

        return ! empty($snap['company_name']) ? __('Company client') : __('Private client');
    }

    private function companyName(string $id): string
    {
        if (! array_key_exists($id, $this->companyNames)) {
            $this->companyNames[$id] = Company::find($id)?->name ?? '—';
        }

        return $this->companyNames[$id];
    }
}
