<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\SalesLead;
use App\Services\Xlsx;
use App\Support\Pii\Search;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
        $q      = trim((string) $request->query('q', ''));
        $status = in_array($request->query('status'), SalesLead::STATUSES, true) ? $request->query('status') : null;

        $all = SalesLead::whereNull('converted_at')->get();

        // Pipeline counters for the filter chips.
        $counts = ['all' => $all->count()] + collect(SalesLead::STATUSES)
            ->mapWithKeys(fn ($s) => [$s => $all->where('status', $s)->count()])->all();

        $leads = Search::filter(
            $status ? $all->where('status', $status)->values() : $all,
            $q,
            fn (SalesLead $l) => [$l->first_name, $l->last_name, $l->company_name, $l->email, $l->phone],
        )
            // Flagged leads float to the top, then most recent first.
            ->sortByDesc(fn (SalesLead $l) => [$l->is_flagged ? 1 : 0, (string) ($l->created_at ?? '')])
            ->values();

        $leads = Search::paginate($leads, $request, 20);

        return view('leads.index', ['leads' => $leads, 'q' => $q, 'status' => $status, 'counts' => $counts]);
    }

    /** Star / unstar a lead to highlight it at the top of the list. */
    public function flag(string $id)
    {
        $lead = SalesLead::findOrFail($id);
        $lead->update(['is_flagged' => ! $lead->is_flagged]);

        return back();
    }

    /** Export the (optionally filtered) leads as an Excel file. */
    public function export(Request $request)
    {
        $q      = trim((string) $request->query('q', ''));
        $status = in_array($request->query('status'), SalesLead::STATUSES, true) ? $request->query('status') : null;
        $labels = SalesLead::statusLabels();

        $leads = Search::filter(
            SalesLead::whereNull('converted_at')->get()->when($status, fn ($c) => $c->where('status', $status)->values()),
            $q,
            fn (SalesLead $l) => [$l->first_name, $l->last_name, $l->company_name, $l->email, $l->phone],
        )->sortByDesc('created_at')->values();

        $path = Xlsx::write(['PROSPECTS' => [
            'headers' => [__('First name'), __('Last name'), __('Company'), __('Email'), __('Phone'), __('Status'), __('Note'), __('Added')],
            'rows' => $leads->map(fn (SalesLead $l) => [
                $l->first_name, $l->last_name, $l->company_name, $l->email, $l->phone,
                $labels[$l->status] ?? $l->status, $l->note, $l->created_at?->format('d/m/Y'),
            ])->all(),
            'widths' => [18, 18, 22, 26, 16, 14, 40, 12],
        ]], 'nautiqs-prospects-');

        return response()->download($path, 'nautiqs-prospects-' . now()->format('Y-m-d') . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
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
