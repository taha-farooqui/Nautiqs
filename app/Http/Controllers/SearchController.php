<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Quote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Global Cmd/Ctrl+K search. Returns a small JSON payload with grouped hits
 * across Quotes and Clients. All queries are tenant-scoped automatically
 * via BelongsToTenant.
 */
class SearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['quotes' => [], 'clients' => []]);
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

        return response()->json([
            'quotes'  => $quotes,
            'clients' => $clients,
        ]);
    }
}
