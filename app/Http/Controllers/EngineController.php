<?php

namespace App\Http\Controllers;

use App\Models\Engine;
use App\Models\GlobalEngine;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

class EngineController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        // Per-dealer engines (private) — same as before.
        $privateQuery = Engine::query()
            ->where('is_archived', false)
            ->orderBy('brand')->orderBy('code');

        // Platform global engines — visible to every dealer.
        $globalQuery = GlobalEngine::query()
            ->where('is_active', true)
            ->orderBy('brand')->orderBy('code');

        if ($q !== '') {
            $needle = $q;
            $privateQuery->where(function ($w) use ($needle) {
                $w->where('brand', 'like', "%{$needle}%")
                  ->orWhere('code', 'like', "%{$needle}%")
                  ->orWhere('description', 'like', "%{$needle}%");
            });
            $globalQuery->where(function ($w) use ($needle) {
                $w->where('brand', 'like', "%{$needle}%")
                  ->orWhere('code', 'like', "%{$needle}%")
                  ->orWhere('description', 'like', "%{$needle}%");
            });
        }

        // Normalise into a single shape so the view can iterate without
        // caring which collection a row came from.
        $private = $privateQuery->get()->map(fn ($e) => $this->normalise($e, 'private'));
        $globals = $globalQuery->get()->map(fn ($e) => $this->normalise($e, 'global'));

        $merged = $private
            ->concat($globals)
            ->sortBy([['brand','asc'], ['code','asc']])
            ->values();

        // Manual paginator since we're merging two Eloquent collections.
        $perPage     = 50;
        $currentPage = (int) ($request->query('page') ?: 1);
        $engines     = new LengthAwarePaginator(
            $merged->forPage($currentPage, $perPage)->values(),
            $merged->count(),
            $perPage,
            $currentPage,
            ['path' => Paginator::resolveCurrentPath(), 'query' => $request->query()]
        );

        return view('engines.index', compact('engines', 'q'));
    }

    /**
     * Flatten an Engine | GlobalEngine row into the shape the view uses.
     * `source` lets the view decide whether to show edit/delete buttons.
     */
    private function normalise($row, string $source): object
    {
        return (object) [
            'id'         => (string) $row->_id,
            'source'     => $source,
            'brand'      => $row->brand,
            'code'       => $row->code,
            'horsepower' => $row->horsepower,
            'fuel'       => $row->fuel,
            'price'      => (float) ($row->price ?? 0),
            'vat_rate'   => (float) ($row->vat_rate ?? 0),
            'ttc'        => $row->priceTtc(),
        ];
    }

    public function create()
    {
        return view('engines.form', ['engine' => null]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        Engine::create(array_merge($data, [
            'company_id'  => auth()->user()->company_id,
            'is_archived' => false,
        ]));
        return redirect()->route('engines.index')->with('status', __('Engine added.'));
    }

    public function edit(string $id)
    {
        $engine = Engine::findOrFail($id);
        return view('engines.form', compact('engine'));
    }

    public function update(string $id, Request $request)
    {
        $engine = Engine::findOrFail($id);
        $engine->update($this->validated($request));
        return redirect()->route('engines.index')->with('status', __('Engine updated.'));
    }

    public function destroy(string $id)
    {
        $engine = Engine::findOrFail($id);
        $engine->delete();
        return back()->with('status', __('Engine removed.'));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'brand'       => 'required|string|max:100',
            'code'        => 'required|string|max:120',
            'horsepower'  => 'nullable|numeric|min:0',
            'fuel'        => 'nullable|in:petrol,diesel,electric,unknown',
            'description' => 'nullable|string|max:500',
            'cost'        => 'nullable|numeric|min:0',
            'price'       => 'required|numeric|min:0',
            'vat_rate'    => 'nullable|numeric|min:0|max:100',
            'currency'    => 'nullable|in:EUR,USD',
        ]);
    }
}
