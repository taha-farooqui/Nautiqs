<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClientRequest;
use App\Models\Client;
use App\Models\Quote;
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

        $query = Client::query()->orderBy('last_name');

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('first_name', 'like', "%{$q}%")
                  ->orWhere('last_name',    'like', "%{$q}%")
                  ->orWhere('company_name', 'like', "%{$q}%")
                  ->orWhere('email',        'like', "%{$q}%")
                  ->orWhere('phone',        'like', "%{$q}%")
                  ->orWhere('city',         'like', "%{$q}%");
            });
        }

        $clients = $query->paginate(20)->withQueryString();

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

        return redirect()
            ->route('clients.show', $client->_id)
            ->with('status', 'Client created.');
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

        return redirect()
            ->route('clients.show', $client->_id)
            ->with('status', 'Client updated.');
    }

    public function destroy(string $id)
    {
        $client = Client::findOrFail($id);

        // Protect against orphaning quotes — block delete if any quote
        // references this client.
        if (Quote::where('client_id', $id)->exists()) {
            return back()->withErrors([
                'delete' => 'Cannot delete a client who has quotes. Archive the quotes first.',
            ]);
        }

        $client->delete();

        return redirect()
            ->route('clients.index')
            ->with('status', 'Client deleted.');
    }
}
