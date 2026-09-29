<?php

namespace App\Http\Controllers;

use App\Models\Engine;
use App\Models\EngineAccessory;
use Illuminate\Http\Request;

/**
 * Kits and propellers, and which engines they go with.
 *
 * The link lives on the engine (Engine::accessory_ids), so an accessory's
 * engine list is saved by adding or removing its id on each engine. Engines
 * are loaded and saved one by one rather than updated in bulk by _id: on this
 * driver a bulk match on string ids can silently match nothing, and a dealer
 * has tens of engines, not thousands.
 */
class EngineAccessoryController extends Controller
{
    public function index(Request $request)
    {
        $q    = trim((string) $request->query('q', ''));
        $type = in_array($request->query('type'), EngineAccessory::TYPES, true) ? $request->query('type') : null;

        $accessories = EngineAccessory::query()
            ->when($type, fn ($w) => $w->where('type', $type))
            ->orderBy('type')->orderBy('label')
            ->get();

        if ($q !== '') {
            $needle = mb_strtolower($q);
            $accessories = $accessories->filter(fn ($a) =>
                str_contains(mb_strtolower($a->label . ' ' . $a->reference), $needle))->values();
        }

        // How many engines each accessory is linked to, from one pass over
        // the dealer's engines rather than one query per row.
        $engineCount = [];
        foreach (Engine::where('is_archived', false)->get(['accessory_ids']) as $e) {
            foreach ($e->accessoryIds() as $id) {
                $engineCount[$id] = ($engineCount[$id] ?? 0) + 1;
            }
        }

        $counts = [
            'all'       => EngineAccessory::count(),
            'kit'       => EngineAccessory::where('type', EngineAccessory::TYPE_KIT)->count(),
            'propeller' => EngineAccessory::where('type', EngineAccessory::TYPE_PROPELLER)->count(),
        ];

        return view('engines.accessories.index', compact('accessories', 'engineCount', 'counts', 'q', 'type'));
    }

    public function create(Request $request)
    {
        return view('engines.accessories.form', [
            'accessory' => null,
            'type'      => in_array($request->query('type'), EngineAccessory::TYPES, true) ? $request->query('type') : EngineAccessory::TYPE_KIT,
            'engines'   => $this->engineChoices(),
            'linked'    => [],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $accessory = EngineAccessory::create(array_merge($this->fields($data), [
            'company_id' => auth()->user()->company_id,
            'currency'   => 'EUR',
        ]));

        $this->syncEngines((string) $accessory->_id, $data['engine_ids'] ?? []);

        return redirect()->route('engine-accessories.index', ['type' => $accessory->type])
            ->with('status', __('Accessory added.'));
    }

    public function edit(string $id)
    {
        $accessory = EngineAccessory::findOrFail($id);

        return view('engines.accessories.form', [
            'accessory' => $accessory,
            'type'      => $accessory->type,
            'engines'   => $this->engineChoices(),
            'linked'    => $accessory->engines()->get(['_id'])->map(fn ($e) => (string) $e->_id)->all(),
        ]);
    }

    public function update(string $id, Request $request)
    {
        $accessory = EngineAccessory::findOrFail($id);
        $data = $this->validated($request);

        $accessory->update($this->fields($data));
        $this->syncEngines((string) $accessory->_id, $data['engine_ids'] ?? []);

        return redirect()->route('engine-accessories.index', ['type' => $accessory->type])
            ->with('status', __('Accessory updated.'));
    }

    public function destroy(string $id)
    {
        $accessory = EngineAccessory::findOrFail($id);

        // Unlink first, so no engine keeps suggesting something that is gone.
        // Quotes already written keep their own copy of the line.
        $this->syncEngines((string) $accessory->_id, []);
        $accessory->delete();

        return back()->with('status', __('Accessory removed.'));
    }

    /* ------------------------------------------------------------ helpers */

    private function validated(Request $request): array
    {
        return $request->validate([
            'type'         => 'required|in:' . implode(',', EngineAccessory::TYPES),
            'label'        => 'required|string|max:500',
            'reference'    => 'nullable|string|max:120',
            'cost'         => 'nullable|numeric|min:0',
            'price'        => 'required|numeric|min:0',
            'vat_rate'     => 'nullable|numeric|min:0|max:100',
            'engine_ids'   => 'nullable|array',
            'engine_ids.*' => 'string',
        ]);
    }

    private function fields(array $data): array
    {
        return [
            'type'      => $data['type'],
            'label'     => trim($data['label']),
            'reference' => filled($data['reference'] ?? null) ? trim($data['reference']) : null,
            'cost'      => isset($data['cost']) && $data['cost'] !== '' ? round((float) $data['cost'], 2) : null,
            'price'     => round((float) $data['price'], 2),
            'vat_rate'  => isset($data['vat_rate']) && $data['vat_rate'] !== '' ? (float) $data['vat_rate'] : 20.0,
        ];
    }

    /** The dealer's engines for the picker, smallest first. */
    private function engineChoices()
    {
        return Engine::where('is_archived', false)->get()
            ->sortBy(fn ($e) => [(string) $e->brand, (float) ($e->horsepower ?? 0), (string) $e->code])
            ->values();
    }

    /**
     * Make exactly $engineIds carry this accessory. The tenant scope limits
     * the engines loaded to the current dealer, so an id from elsewhere in
     * the request simply matches nothing.
     */
    private function syncEngines(string $accessoryId, array $engineIds): void
    {
        $want = array_flip(array_map('strval', $engineIds));

        foreach (Engine::all() as $engine) {
            $ids  = $engine->accessoryIds();
            $has  = in_array($accessoryId, $ids, true);
            $should = isset($want[(string) $engine->_id]);

            if ($should && ! $has) {
                $ids[] = $accessoryId;
            } elseif (! $should && $has) {
                $ids = array_values(array_diff($ids, [$accessoryId]));
            } else {
                continue;
            }

            $engine->accessory_ids = $ids;
            $engine->save();
        }
    }
}
