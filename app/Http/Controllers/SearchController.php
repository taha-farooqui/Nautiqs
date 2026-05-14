<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\CompanyBoatModel;
use App\Models\CompanyBrand;
use App\Models\Quote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Global Cmd/Ctrl+K search. Returns a small JSON payload with grouped hits
 * across Quotes, Clients and Catalogue models. All queries are
 * tenant-scoped automatically via BelongsToTenant.
 */
class SearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['quotes' => [], 'clients' => [], 'models' => []]);
        }

        $quotes = Quote::query()
            ->where(function ($w) use ($q) {
                $w->where('number', 'like', "%{$q}%")
                  ->orWhere('client_snapshot.first_name', 'like', "%{$q}%")
                  ->orWhere('client_snapshot.last_name',  'like', "%{$q}%")
                  ->orWhere('model_snapshot.name',        'like', "%{$q}%");
            })
            ->orderBy('created_at', 'desc')
            ->limit(8)
            ->get(['number', 'client_snapshot', 'model_snapshot', 'totals', 'status'])
            ->map(fn ($qu) => [
                'id'     => (string) $qu->_id,
                'number' => $qu->number,
                'client' => trim(($qu->client_snapshot['first_name'] ?? '') . ' ' . ($qu->client_snapshot['last_name'] ?? '')),
                'model'  => $qu->model_snapshot['name'] ?? '',
                'amount' => '€' . number_format($qu->totals['total_ttc'] ?? 0, 0, ',', ' '),
                'status' => $qu->status,
                'url'    => route('quotes.show', $qu->_id),
            ]);

        $clients = Client::query()
            ->where(function ($w) use ($q) {
                $w->where('first_name',   'like', "%{$q}%")
                  ->orWhere('last_name',  'like', "%{$q}%")
                  ->orWhere('company_name','like', "%{$q}%")
                  ->orWhere('email',      'like', "%{$q}%")
                  ->orWhere('phone',      'like', "%{$q}%")
                  ->orWhere('city',       'like', "%{$q}%");
            })
            ->orderBy('last_name')
            ->limit(8)
            ->get(['first_name', 'last_name', 'company_name', 'email', 'city'])
            ->map(fn ($c) => [
                'id'    => (string) $c->_id,
                'name'  => trim($c->first_name . ' ' . $c->last_name),
                'sub'   => $c->company_name ?: ($c->email ?: $c->city ?: ''),
                'email' => $c->email,
                'url'   => route('clients.show', $c->_id),
            ]);

        // Catalogue models — searchable by name, code, internal code, and
        // optionally the brand name. We resolve brand names up front to
        // avoid an N+1 inside the map.
        $modelRows = CompanyBoatModel::query()
            ->where('is_archived', false)
            ->where(function ($w) use ($q) {
                $w->where('name',          'like', "%{$q}%")
                  ->orWhere('code',          'like', "%{$q}%")
                  ->orWhere('internal_code', 'like', "%{$q}%")
                  ->orWhere('complement',    'like', "%{$q}%");
            })
            ->orderBy('name')
            ->limit(8)
            ->get(['name', 'code', 'internal_code', 'complement', 'company_brand_id']);

        // Also match models whose brand name matches the query — separate
        // query so we don't have to join client-side. Merged + de-duped.
        $brandMatchIds = CompanyBrand::where('is_active', true)
            ->where('name', 'like', "%{$q}%")
            ->get(['_id'])
            ->pluck('_id')
            ->map(fn ($i) => (string) $i)
            ->all();
        if (! empty($brandMatchIds)) {
            $byBrand = CompanyBoatModel::query()
                ->where('is_archived', false)
                ->whereIn('company_brand_id', $brandMatchIds)
                ->orderBy('name')
                ->limit(8)
                ->get(['name', 'code', 'internal_code', 'complement', 'company_brand_id']);
            $modelRows = $modelRows->merge($byBrand)->unique('_id')->take(8)->values();
        }

        $brandNames = CompanyBrand::whereIn('_id', $modelRows->pluck('company_brand_id')->filter()->unique()->values()->all())
            ->get(['_id', 'name'])
            ->keyBy(fn ($b) => (string) $b->_id)
            ->map(fn ($b) => $b->name);

        $models = $modelRows->map(fn ($m) => [
            'id'    => (string) $m->_id,
            'name'  => trim($m->name . ($m->complement ? ' ' . $m->complement : '')),
            'sub'   => trim(($brandNames[(string) $m->company_brand_id] ?? '') . ($m->code ? ' · ' . $m->code : '')),
            'url'   => route('catalogue.models.edit', $m->_id),
        ])->values();

        return response()->json([
            'quotes'  => $quotes,
            'clients' => $clients,
            'models'  => $models,
        ]);
    }
}
