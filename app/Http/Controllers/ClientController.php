<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClientRequest;
use App\Models\Client;
use App\Models\Quote;
use App\Support\Pii\Search;
use Illuminate\Http\Request;

/**
 * Spec §16.1 Clients page.
 * All queries are automatically scoped to auth()->user()->company_id via
 * the BelongsToTenant global scope — no cross-tenant access is possible.
 */
class ClientController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        // Names, email and phone are encrypted, so the database can neither
        // match nor sort them. The dealer's own clients are filtered and
        // filed by surname here instead — see App\Support\Pii\Search.
        $clients = Search::filter(Client::query()->get(), $q, fn (Client $c) => [
            $c->first_name, $c->last_name, $c->company_name, $c->email, $c->phone, $c->city,
        ])->sortBy(fn (Client $c) => Search::nameKey($c->last_name, $c->first_name))->values();

        $clients = Search::paginate($clients, $request, 20);

        return view('clients.index', [
            'clients' => $clients,
            'q'       => $q,
        ]);
    }

    public function create()
    {
        return view('clients.create', ['client' => new Client]);
    }

    public function store(ClientRequest $request)
    {
        $client = Client::create($request->validated());
        $this->rememberLeadSource($request->input('lead_source'));

        return redirect()
            ->route('clients.show', $client->_id)
            ->with('status', __('Client created.'));
    }

    public function show(string $id)
    {
        $client = Client::findOrFail($id);

        $quotes = Quote::where('client_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('clients.show', [
            'client' => $client,
            'quotes' => $quotes,
        ]);
    }

    public function edit(string $id)
    {
        $client = Client::findOrFail($id);

        return view('clients.edit', ['client' => $client]);
    }

    public function update(ClientRequest $request, string $id)
    {
        $client = Client::findOrFail($id);
        $client->update($request->validated());
        $this->rememberLeadSource($request->input('lead_source'));

        return redirect()
            ->route('clients.show', $client->_id)
            ->with('status', __('Client updated.'));
    }

    public function destroy(string $id)
    {
        $client = Client::findOrFail($id);

        // Protect against orphaning quotes — block delete if any quote
        // references this client.
        if (Quote::where('client_id', $id)->exists()) {
            return back()->withErrors([
                'delete' => __('Cannot delete a client who has quotes. Archive the quotes first.'),
            ]);
        }

        $client->delete();

        return redirect()
            ->route('clients.index')
            ->with('status', __('Client deleted.'));
    }
    /**
     * A source typed by hand joins this dealership's dropdown for next time
     * (spec-adjacent request: "Pakistan Auto Show (PAPS) 2026"). Scoped to
     * the caller's own company — never shared across tenants.
     */
    private function rememberLeadSource(?string $source): void
    {
        auth()->user()?->company?->rememberLeadSource($source);
    }
}
