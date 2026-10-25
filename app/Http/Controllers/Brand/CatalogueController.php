<?php

namespace App\Http\Controllers\Brand;

use App\Http\Controllers\Controller;
use App\Models\BrandAccess;
use App\Models\BrandModel;
use App\Models\BrandOption;
use App\Models\BrandVariant;
use App\Models\ManagedBrand;
use App\Services\Brand\BrandPricing;
use App\Services\Brand\BrandSync;
use Illuminate\Http\Request;

/**
 * A boat brand's catalogue: models, their versions and options, public
 * prices and per-level purchase prices. Every save is pushed to the
 * connected dealers by BrandSync. All queries are pinned to the signed-in
 * manager's account by BrandScope.
 */
class CatalogueController extends Controller
{
    public function index(Request $request)
    {
        $account = $this->account();
        if ($account->isEngine()) {
            return redirect()->route('brand.engines.index');
        }

        $brands = ManagedBrand::orderBy('position')->orderBy('name')->get();
        $brand  = $brands->first(fn ($b) => (string) $b->_id === (string) $request->query('brand')) ?? $brands->first();

        $models = $brand
            ? BrandModel::where('managed_brand_id', (string) $brand->_id)->orderBy('is_archived')->orderBy('name')->get()
            : collect();
        $ids = $models->map(fn ($m) => (string) $m->_id)->all();
        $variants = BrandVariant::whereIn('brand_model_id', $ids)->where('is_archived', '!=', true)->get()->groupBy('brand_model_id');
        $options  = BrandOption::whereIn('brand_model_id', $ids)->where('is_archived', '!=', true)->get()->groupBy('brand_model_id');
        $counts   = BrandModel::where('is_archived', '!=', true)->get(['managed_brand_id'])->countBy('managed_brand_id');

        return view('brand.catalogue.index', compact('account', 'brands', 'brand', 'models', 'variants', 'options', 'counts'));
    }

    public function create(Request $request)
    {
        $account = $this->account();
        $brands  = ManagedBrand::orderBy('position')->orderBy('name')->get();
        $brand   = $brands->first(fn ($b) => (string) $b->_id === (string) $request->query('brand')) ?? $brands->first();
        abort_unless($brand, 404);

        return view('brand.catalogue.edit', [
            'account' => $account, 'brands' => $brands, 'brand' => $brand, 'model' => null,
            'versions' => [], 'options' => [], 'levels' => $this->levelInfo($account, $brand),
        ]);
    }

    public function store(Request $request)
    {
        $account = $this->account();
        $data = $this->validated($request);
        $brand = ManagedBrand::findOrFail($request->input('managed_brand_id'));

        $model = BrandModel::create($this->modelFields($data) + [
            'managed_brand_id' => (string) $brand->_id, 'is_archived' => false,
        ]);
        $this->saveRows($model, $data);
        $changes = $this->publish();

        return redirect()->route('brand.catalogue.edit', $model->_id)
            ->with('status', $this->savedMessage($changes));
    }

    public function edit(string $id)
    {
        $account = $this->account();
        $model   = BrandModel::findOrFail($id);
        $brand   = ManagedBrand::findOrFail($model->managed_brand_id);

        $versions = BrandVariant::where('brand_model_id', $id)->where('is_archived', '!=', true)
            ->orderBy('position')->get()->map(fn ($v) => [
                'id' => (string) $v->_id, 'name' => $v->name, 'price' => $v->price, 'currency' => $v->currency ?: 'EUR',
                'levels' => array_map(fn ($p) => $p === null ? '' : $p, $this->levelsOf($v)),
                'equipment' => collect($v->included_equipment ?? [])->pluck('label')->filter()->implode("\n"),
            ])->values()->all();
        $options = BrandOption::where('brand_model_id', $id)->where('is_archived', '!=', true)
            ->orderBy('position')->get()->map(fn ($o) => [
                'id' => (string) $o->_id, 'category' => $o->category, 'label' => $o->label, 'description' => $o->description,
                'price' => $o->price, 'vat_rate' => $o->vat_rate ?? 20,
                'levels' => array_map(fn ($p) => $p === null ? '' : $p, $this->levelsOf($o)),
            ])->values()->all();

        return view('brand.catalogue.edit', [
            'account' => $account, 'brands' => ManagedBrand::orderBy('position')->orderBy('name')->get(), 'brand' => $brand,
            'model' => $model, 'versions' => $versions, 'options' => $options, 'levels' => $this->levelInfo($account, $brand),
        ]);
    }

    public function update(string $id, Request $request)
    {
        $model = BrandModel::findOrFail($id);
        $data  = $this->validated($request);

        $model->update($this->modelFields($data));
        $this->saveRows($model, $data);
        $changes = $this->publish();

        return redirect()->route('brand.catalogue.edit', $model->_id)->with('status', $this->savedMessage($changes));
    }

    /** Archive hides a model from every dealer; unarchive brings it back. */
    public function toggleArchive(string $id)
    {
        $model = BrandModel::findOrFail($id);
        $model->update(['is_archived' => ! $model->is_archived]);
        $this->publish();

        return redirect()->route('brand.catalogue.index', ['brand' => $model->managed_brand_id])
            ->with('status', $model->is_archived ? __('Model archived — hidden from your dealers.') : __('Model restored.'));
    }

    /* ------------------------------------------------------------ helpers */

    private function validated(Request $request): array
    {
        return $request->validate([
            'name'        => 'required|string|max:160',
            'code'        => 'nullable|string|max:60',
            'year'        => 'nullable|integer|min:1950|max:2100',
            'length_total'=> 'nullable|numeric|min:0|max:200',
            'beam'        => 'nullable|numeric|min:0|max:50',
            'draft_max'   => 'nullable|numeric|min:0|max:20',
            'weight'      => 'nullable|numeric|min:0|max:200000',
            'passengers'  => 'nullable|integer|min:0|max:500',
            'description' => 'nullable|string|max:5000',

            'versions'              => 'nullable|array',
            'versions.*.id'         => 'nullable|string',
            'versions.*.name'       => 'required|string|max:200',
            'versions.*.price'      => 'required|numeric|min:0',
            'versions.*.currency'   => 'nullable|in:EUR,USD',
            'versions.*.levels'     => 'nullable|array',
            'versions.*.levels.*'   => 'nullable|numeric|min:0',
            'versions.*.equipment'  => 'nullable|string|max:10000',

            'options'               => 'nullable|array',
            'options.*.id'          => 'nullable|string',
            'options.*.category'    => 'nullable|string|max:100',
            'options.*.label'       => 'required|string|max:300',
            'options.*.description' => 'nullable|string|max:5000',
            'options.*.price'       => 'required|numeric',
            'options.*.vat_rate'    => 'nullable|numeric|min:0|max:100',
            'options.*.levels'      => 'nullable|array',
            'options.*.levels.*'    => 'nullable|numeric|min:0',
        ]);
    }

    private function modelFields(array $d): array
    {
        $num = fn ($k) => isset($d[$k]) && $d[$k] !== '' ? (float) $d[$k] : null;

        return [
            'name' => trim($d['name']), 'code' => $d['code'] ?? null,
            'year' => isset($d['year']) && $d['year'] !== '' ? (int) $d['year'] : null,
            'length_total' => $num('length_total'), 'beam' => $num('beam'), 'draft_max' => $num('draft_max'),
            'weight' => $num('weight'),
            'passengers' => isset($d['passengers']) && $d['passengers'] !== '' ? (int) $d['passengers'] : null,
            'description' => $d['description'] ?? null,
        ];
    }

    /** Upsert the posted versions and options; rows no longer posted are archived. */
    private function saveRows(BrandModel $model, array $data): void
    {
        $modelId = (string) $model->_id;
        $base = ['brand_model_id' => $modelId, 'managed_brand_id' => (string) $model->managed_brand_id];

        $keep = [];
        foreach (array_values($data['versions'] ?? []) as $i => $row) {
            $fields = [
                'name' => trim($row['name']), 'price' => round((float) $row['price'], 2),
                'currency' => $row['currency'] ?? 'EUR',
                'level_prices' => BrandVariant::cleanLevelPrices($row['levels'] ?? []),
                'included_equipment' => collect(preg_split('/\r\n|\r|\n/', (string) ($row['equipment'] ?? '')))
                    ->map(fn ($l) => trim($l))->filter()->values()->map(fn ($l) => ['label' => $l, 'type' => 'standard'])->all(),
                'position' => $i, 'is_archived' => false,
            ];
            $existing = ! empty($row['id']) ? BrandVariant::where('brand_model_id', $modelId)->where('_id', $row['id'])->first() : null;
            if ($existing) {
                $existing->update($fields);
            } else {
                $existing = BrandVariant::create($fields + $base);
            }
            $keep[] = (string) $existing->_id;
        }
        foreach (BrandVariant::where('brand_model_id', $modelId)->get() as $v) {
            if (! in_array((string) $v->_id, $keep, true) && ! $v->is_archived) $v->update(['is_archived' => true]);
        }

        $keep = [];
        foreach (array_values($data['options'] ?? []) as $i => $row) {
            $fields = [
                'category' => trim((string) ($row['category'] ?? '')) ?: 'Options', 'label' => trim($row['label']),
                'description' => $row['description'] ?? null, 'price' => round((float) $row['price'], 2),
                'vat_rate' => isset($row['vat_rate']) && $row['vat_rate'] !== '' ? (float) $row['vat_rate'] : 20,
                'currency' => 'EUR', 'level_prices' => BrandOption::cleanLevelPrices($row['levels'] ?? []),
                'position' => $i, 'is_archived' => false,
            ];
            $existing = ! empty($row['id']) ? BrandOption::where('brand_model_id', $modelId)->where('_id', $row['id'])->first() : null;
            if ($existing) {
                $existing->update($fields);
            } else {
                $existing = BrandOption::create($fields + $base);
            }
            $keep[] = (string) $existing->_id;
        }
        foreach (BrandOption::where('brand_model_id', $modelId)->get() as $o) {
            if (! in_array((string) $o->_id, $keep, true) && ! $o->is_archived) $o->update(['is_archived' => true]);
        }
    }

    private function levelsOf($item): array
    {
        $out = [];
        for ($l = 1; $l <= \App\Models\BrandAccount::LEVEL_COUNT; $l++) $out[] = $item->exactLevelPrice($l);
        return $out;
    }

    /** Level names and the % each applies here, for the editor's placeholders. */
    private function levelInfo($account, ManagedBrand $brand): array
    {
        $pricing = new BrandPricing($account);
        $out = [];
        foreach ($account->levelsResolved() as $n => $lvl) {
            $out[] = ['n' => $n, 'name' => $lvl['name'],
                'versions' => $pricing->percent('versions', $n, $brand), 'options' => $pricing->percent('options', $n, $brand)];
        }
        return $out;
    }

    private function publish(): int
    {
        return array_sum(app(BrandSync::class)->syncAccount($this->account()));
    }

    private function savedMessage(int $changes): string
    {
        $dealers = BrandAccess::where('brand_account_id', (string) $this->account()->_id)
            ->where('status', BrandAccess::STATUS_ACTIVE)->count();

        return $dealers
            ? __('Saved and published to :n connected dealers.', ['n' => $dealers])
            : __('Saved. Invite dealers to share it with them.');
    }

    private function account()
    {
        return auth()->user()->brandAccount;
    }
}
