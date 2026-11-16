<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\SalesLead;
use App\Support\Pii\Search;
use Illuminate\Http\Request;

/**
 * Sales leads: a lightweight list of potential clients a salesperson is
 * tracking before they become a real Client. Tenant-scoped via SalesLead's
 * global scope. Names/emails are encrypted, so search and sort run in PHP
 * over the dealer's own leads (same approach as clients).
 */
class SalesLeadController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $leads = Search::filter(
            SalesLead::whereNull('converted_at')->get(),
            $q,
            fn (SalesLead $l) => [$l->first_name, $l->last_name, $l->company_name, $l->email, $l->phone],
        )->sortByDesc('created_at')->values();

        $leads = Search::paginate($leads, $request, 20);

        return view('leads.index', ['leads' => $leads, 'q' => $q]);
    }

    public function create()
    {
        return view('leads.create', ['lead' => new SalesLead(['status' => SalesLead::STATUS_NEW])]);
    }

    public function store(Request $request)
    {
        SalesLead::create($this->validated($request) + [
            'created_by_user_id' => (string) auth()->id(),
        ]);

        return redirect()->route('leads.index')->with('status', __('Lead added.'));
    }

    public function edit(string $id)
    {
        return view('leads.edit', ['lead' => SalesLead::findOrFail($id)]);
    }

    public function update(string $id, Request $request)
    {
        SalesLead::findOrFail($id)->update($this->validated($request));

        return redirect()->route('leads.index')->with('status', __('Lead updated.'));
    }

    public function destroy(string $id)
    {
        SalesLead::findOrFail($id)->delete();

        return redirect()->route('leads.index')->with('status', __('Lead deleted.'));
    }

    /** Turn a lead into a real client, then drop it from the active list. */
    public function convert(string $id)
    {
        $lead = SalesLead::findOrFail($id);

        $client = Client::create([
            'first_name'   => $lead->first_name,
            'last_name'    => $lead->last_name,
            'company_name' => $lead->company_name,
            'email'        => $lead->email,
            'phone'        => $lead->phone,
            'internal_notes' => $lead->note,
        ]);

        $lead->update(['converted_at' => now(), 'converted_client_id' => (string) $client->_id]);

        return redirect()->route('clients.show', $client->_id)
            ->with('status', __('Lead converted to a client.'));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'first_name'   => ['nullable', 'string', 'max:100'],
            'last_name'    => ['nullable', 'string', 'max:100'],
            'company_name' => ['nullable', 'string', 'max:150'],
            'email'        => ['nullable', 'email', 'max:150'],
            'phone'        => ['nullable', 'string', 'max:40'],
            'note'         => ['nullable', 'string', 'max:2000'],
            'status'       => ['required', 'in:' . implode(',', SalesLead::STATUSES)],
        ]);
    }
}
