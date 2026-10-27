<?php

namespace App\Http\Controllers\Brand;

use App\Http\Controllers\Controller;
use App\Models\BrandEngine;
use App\Models\BrandEngineAccessory;
use App\Models\ManagedBrand;
use App\Services\Brand\BrandPricing;
use App\Services\Brand\BrandSync;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * An engine brand's catalogue: engines, and the kits and propellers linked to
 * them. Same pricing model as boats (public price + 3 levels) and the same
 * publishing: every save syncs the connected dealers.
 */
class EngineController extends Controller
{
    public function index(Request $request)
    {
        $account = $this->account();
        if (! $account->isEngine()) {
            return redirect()->route('brand.catalogue.index');
        }
        $tab = $request->query('tab') === 'accessories' ? 'accessories' : 'engines';
        $brands = ManagedBrand::orderBy('position')->orderBy('name')->get();

        return view('brand.engines.index', [
            'account' => $account, 'tab' => $tab, 'brands' => $brands,
            'engines' => BrandEngine::orderBy('is_archived')->orderBy('horsepower')->orderBy('code')->get(),
            'accessories' => BrandEngineAccessory::orderBy('type')->orderBy('label')->get(),
            'brandNames' => $brands->mapWithKeys(fn ($b) => [(string) $b->_id => $b->name]),
        ]);
    }

    /* -------------------------------------------------------------- engines */

    public function create()
    {
        return $this->engineForm(null);
    }

    public function edit(string $id)
    {
        return $this->engineForm(BrandEngine::findOrFail($id));
    }

    public function store(Request $request)
    {
        $data = $this->validateEngine($request);
        BrandEngine::create($this->engineFields($data) + ['is_archived' => false]);

        return $this->published(__('Engine added.'));
    }

    public function update(string $id, Request $request)
    {
        $engine = BrandEngine::findOrFail($id);
        $engine->update($this->engineFields($this->validateEngine($request)));

        return $this->published(__('Engine saved.'));
    }

    public function toggleArchive(string $id)
    {
        $engine = BrandEngine::findOrFail($id);
        $engine->update(['is_archived' => ! $engine->is_archived]);

        return $this->published($engine->is_archived ? __('Engine archived — hidden from your dealers.') : __('Engine restored.'));
    }

    /* ---------------------------------------------------- kits & propellers */

    public function createAccessory(Request $request)
    {
        return $this->accessoryForm(null, $request->query('type') === 'propeller' ? 'propeller' : 'kit');
    }

    public function editAccessory(string $id)
    {
        $a = BrandEngineAccessory::findOrFail($id);
        return $this->accessoryForm($a, $a->type);
    }

    public function storeAccessory(Request $request)
    {
        $data = $this->validateAccessory($request);
        $acc = BrandEngineAccessory::create($this->accessoryFields($data));
        $this->linkEngines((string) $acc->_id, $data['engine_ids'] ?? []);

        return $this->published(__('Kit or propeller added.'), 'accessories');
    }

    public function updateAccessory(string $id, Request $request)
    {
        $acc = BrandEngineAccessory::findOrFail($id);
        $data = $this->validateAccessory($request);
        $acc->update($this->accessoryFields($data));
        $this->linkEngines((string) $acc->_id, $data['engine_ids'] ?? []);

        return $this->published(__('Kit or propeller saved.'), 'accessories');
    }

    public function destroyAccessory(string $id)
    {
        $acc = BrandEngineAccessory::findOrFail($id);
        $this->linkEngines((string) $acc->_id, []);
        $acc->delete();

        return $this->published(__('Kit or propeller removed.'), 'accessories');
    }

    /* ------------------------------------------------------------ helpers */

    private function engineForm(?BrandEngine $engine)
    {
        $account = $this->account();
        $brands = ManagedBrand::orderBy('position')->orderBy('name')->get();
        $brand = $engine ? $brands->first(fn ($b) => (string) $b->_id === (string) $engine->managed_brand_id) : $brands->first();
        $pricing = new BrandPricing($account);

        return view('brand.engines.form', [
            'account' => $account, 'engine' => $engine, 'brands' => $brands,
            'accessories' => BrandEngineAccessory::orderBy('type')->orderBy('label')->get(),
            'levels' => collect($account->levelsResolved())->map(fn ($l, $n) => ['n' => $n, 'name' => $l['name'], 'pct' => $pricing->percent('engines', $n, $brand)])->values(),
        ]);
    }

    private function accessoryForm(?BrandEngineAccessory $acc, string $type)
    {
        $account = $this->account();
        $brands = ManagedBrand::orderBy('position')->orderBy('name')->get();
        $pricing = new BrandPricing($account);
        $brand = $acc ? $brands->first(fn ($b) => (string) $b->_id === (string) $acc->managed_brand_id) : $brands->first();
        $linked = $acc ? BrandEngine::where('accessory_ids', (string) $acc->_id)->get()->map(fn ($e) => (string) $e->_id)->all() : [];

        return view('brand.engines.accessory-form', [
            'account' => $account, 'acc' => $acc, 'type' => $type, 'brands' => $brands, 'linked' => $linked,
            'engines' => BrandEngine::where('is_archived', '!=', true)->orderBy('horsepower')->orderBy('code')->get(),
            'levels' => collect($account->levelsResolved())->map(fn ($l, $n) => ['n' => $n, 'name' => $l['name'],
                'kits' => $pricing->percent('kits', $n, $brand), 'propellers' => $pricing->percent('propellers', $n, $brand)])->values(),
        ]);
    }

    private function validateEngine(Request $request): array
    {
        return $request->validate([
            'managed_brand_id' => ['required', Rule::in(ManagedBrand::all()->map(fn ($b) => (string) $b->_id)->all())],
            'code'          => 'required|string|max:120',
            'horsepower'    => 'nullable|numeric|min:0|max:2000',
            'fuel'          => 'nullable|string|max:40',
            'description'   => 'nullable|string|max:2000',
            'price'         => 'required|numeric|min:0',
            'vat_rate'      => 'nullable|numeric|min:0|max:100',
            'currency'      => 'nullable|in:EUR,USD',
            'levels'        => 'nullable|array',
            'levels.*'      => 'nullable|numeric|min:0',
            'accessory_ids' => 'nullable|array',
            'accessory_ids.*' => 'string',
        ]);
    }

    private function engineFields(array $d): array
    {
        $own = BrandEngineAccessory::all()->map(fn ($a) => (string) $a->_id)->all();

        return [
            'managed_brand_id' => $d['managed_brand_id'], 'code' => trim($d['code']),
            'horsepower' => isset($d['horsepower']) && $d['horsepower'] !== '' ? (float) $d['horsepower'] : null,
            'fuel' => $d['fuel'] ?? null, 'description' => $d['description'] ?? null,
            'price' => round((float) $d['price'], 2),
            'vat_rate' => isset($d['vat_rate']) && $d['vat_rate'] !== '' ? (float) $d['vat_rate'] : 20,
            'currency' => in_array($d['currency'] ?? 'EUR', ['EUR', 'USD'], true) ? ($d['currency'] ?? 'EUR') : 'EUR',
            'level_prices' => BrandEngine::cleanLevelPrices($d['levels'] ?? [], $this->account()->levelCount()),
            'accessory_ids' => array_values(array_intersect(array_map('strval', $d['accessory_ids'] ?? []), $own)),
        ];
    }

    private function validateAccessory(Request $request): array
    {
        return $request->validate([
            'managed_brand_id' => ['required', Rule::in(ManagedBrand::all()->map(fn ($b) => (string) $b->_id)->all())],
            'type'        => 'required|in:kit,propeller',
            'label'       => 'required|string|max:500',
            'reference'   => 'nullable|string|max:120',
            'price'       => 'required|numeric|min:0',
            'vat_rate'    => 'nullable|numeric|min:0|max:100',
            'currency'    => 'nullable|in:EUR,USD',
            'levels'      => 'nullable|array',
            'levels.*'    => 'nullable|numeric|min:0',
            'engine_ids'  => 'nullable|array',
            'engine_ids.*'=> 'string',
        ]);
    }

    private function accessoryFields(array $d): array
    {
        return [
            'managed_brand_id' => $d['managed_brand_id'], 'type' => $d['type'], 'label' => trim($d['label']),
            'reference' => $d['reference'] ?? null, 'price' => round((float) $d['price'], 2),
            'vat_rate' => isset($d['vat_rate']) && $d['vat_rate'] !== '' ? (float) $d['vat_rate'] : 20,
            'currency' => in_array($d['currency'] ?? 'EUR', ['EUR', 'USD'], true) ? ($d['currency'] ?? 'EUR') : 'EUR',
            'level_prices' => BrandEngineAccessory::cleanLevelPrices($d['levels'] ?? [], $this->account()->levelCount()),
        ];
    }

    /** Set which of the brand's engines list this kit/propeller. */
    private function linkEngines(string $accId, array $engineIds): void
    {
        $engineIds = array_map('strval', $engineIds);
        foreach (BrandEngine::all() as $e) {
            $ids = $e->accessoryIds();
            $has = in_array($accId, $ids, true);
            $want = in_array((string) $e->_id, $engineIds, true);
            if ($want && ! $has) {
                $e->accessory_ids = array_values(array_merge($ids, [$accId]));
                $e->save();
            } elseif (! $want && $has) {
                $e->accessory_ids = array_values(array_diff($ids, [$accId]));
                $e->save();
            }
        }
    }

    private function published(string $msg, string $tab = 'engines')
    {
        app(BrandSync::class)->syncAccount($this->account());

        return redirect()->route('brand.engines.index', ['tab' => $tab])->with('status', $msg);
    }

    private function account()
    {
        return auth()->user()->brandAccount;
    }
}
