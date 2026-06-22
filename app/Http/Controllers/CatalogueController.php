<?php

namespace App\Http\Controllers;

use App\Models\CompanyBoatModel;
use App\Models\CompanyBoatVariant;
use App\Models\CompanyBrand;
use App\Models\CompanyOption;
use App\Models\GlobalBoatModel;
use App\Models\GlobalBoatVariant;
use App\Models\GlobalBrand;
use App\Models\GlobalOption;
use App\Services\CatalogueService;
use App\Services\OptionImporter;
use Illuminate\Http\Request;

/**
 * Spec §6 + §7 — dealer-facing catalogue. Browse global brands, activate
 * them into the workspace, manage private brands/models/variants/options,
 * customise prices on the workspace copies.
 */
class CatalogueController extends Controller
{
    public function __construct(private CatalogueService $catalogue)
    {
    }

    /* ----------------------------------------------------------- BRANDS */

    /**
     * Brands page = "available globally" + "your workspace" (active and
     * deactivated) + "create private brand" form.
     */
    public function brands(Request $request)
    {
        // Brands are platform-managed and auto-activated for every dealer.
        // There's no workspace/available split anymore — just one list of
        // everything the dealer has access to. Tabs + activation UI gone
        // 2026-05-23.
        $companyBrands = CompanyBrand::orderBy('name')->get();

        return view('catalogue.brands', [
            'companyBrands' => $companyBrands,
        ]);
    }

    public function activateBrand(string $globalBrandId)
    {
        $brand = GlobalBrand::findOrFail($globalBrandId);
        $this->catalogue->activateGlobalBrand(auth()->user()->company_id, $brand);
        return back()->with('status', __('Brand «:name» activated — all models copied to your workspace.', ['name' => $brand->name]));
    }

    public function deactivateBrand(string $companyBrandId)
    {
        $companyBrand = CompanyBrand::findOrFail($companyBrandId);
        $this->catalogue->deactivateGlobalBrand(auth()->user()->company_id, $companyBrand);
        return back()->with('status', __('Brand «:name» deactivated. Your customisations are kept.', ['name' => $companyBrand->name]));
    }

    public function reactivateBrand(string $companyBrandId)
    {
        $companyBrand = CompanyBrand::findOrFail($companyBrandId);
        $companyBrand->update(['is_active' => true]);
        return back()->with('status', __('Brand «:name» re-activated.', ['name' => $companyBrand->name]));
    }

    public function storePrivateBrand(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:200',
            'description' => 'nullable|string|max:1000',
        ]);
        $brand = $this->catalogue->createPrivateBrand(auth()->user()->company_id, $data);
        return redirect()->route('catalogue.brands')
            ->with('status', __('Private brand «:name» created. Add your first model →', ['name' => $brand->name]));
    }

    /**
     * Inline brand picker — JSON list of brands for the catalogue form's
     * autocomplete. Merges:
     *   1. The dealer's active workspace brands (CompanyBrand)
     *   2. Every global brand they haven't activated yet (GlobalBrand)
     *
     * Workspace brands are returned with a bare _id; globals are prefixed
     * with `global:<id>` so storeModel() / updateModel() can detect them
     * and auto-activate before persisting the boat.
     */
    public function brandLookup(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $workspace = CompanyBrand::where('is_active', true)
            ->when($q !== '', fn ($w) => $w->where('name', 'like', "%{$q}%"))
            ->orderBy('name')
            ->limit(30)
            ->get(['_id', 'name'])
            ->map(fn ($b) => [
                'id'     => (string) $b->_id,
                'name'   => $b->name,
                'source' => 'workspace',
            ]);

        // Names already in the workspace — don't surface their global twin
        // a second time. Compared case-insensitively.
        $skip = $workspace->pluck('name')->map(fn ($n) => mb_strtolower($n));

        $globals = \App\Models\GlobalBrand::where('is_active', true)
            ->when($q !== '', fn ($w) => $w->where('name', 'like', "%{$q}%"))
            ->orderBy('name')
            ->limit(30)
            ->get(['_id', 'name'])
            ->reject(fn ($b) => $skip->contains(mb_strtolower($b->name)))
            ->map(fn ($b) => [
                'id'     => 'global:' . (string) $b->_id,
                'name'   => $b->name,
                'source' => 'global',
            ]);

        $merged = $workspace
            ->concat($globals)
            ->sortBy(fn ($r) => mb_strtolower($r['name']))
            ->take(50)
            ->values();

        return response()->json($merged);
    }

    /**
     * Create a new private brand from the catalogue form's inline picker.
     * Returns JSON so the form can append the new brand and pre-select it
     * without a page reload.
     */
    public function storeInlineBrand(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:200',
        ]);
        $brand = $this->catalogue->createPrivateBrand(auth()->user()->company_id, $data);
        return response()->json([
            'id'   => (string) $brand->_id,
            'name' => $brand->name,
        ], 201);
    }

    public function destroyPrivateBrand(string $companyBrandId)
    {
        $brand = CompanyBrand::findOrFail($companyBrandId);
        if ($brand->source !== CompanyBrand::SOURCE_PRIVATE) {
            return back()->withErrors(['brand' => __('Only private brands can be deleted. Deactivate global brands instead.')]);
        }
        // Delete cascade — models, variants, options.
        // NB: Laravel-Mongodb's query-builder ->pluck('_id') returns NULLs;
        // we have to fetch the models then pluck on the collection.
        $modelIds = CompanyBoatModel::where('company_brand_id', (string) $brand->_id)
            ->get(['_id'])->pluck('_id')->map(fn ($i) => (string) $i)->all();
        CompanyOption::whereIn('company_model_id', $modelIds)->delete();
        CompanyBoatVariant::whereIn('company_model_id', $modelIds)->delete();
        CompanyBoatModel::whereIn('_id', $modelIds)->delete();
        $brand->delete();
        return redirect()->route('catalogue.brands')->with('status', __('Private brand removed.'));
    }

    /* -------------------------------------------------- MODELS / VARIANTS */

    /**
     * Catalogue list — flat one-row-per-variant table matching the old
     * software's "Catalogues" screen. Each row links to the tabbed editor
     * for the parent boat (model). The dealer manages their own catalogue;
     * we no longer surface a "global" tab.
     */
    public function models(Request $request)
    {
        $brandFilter  = $request->query('brand');
        $q            = trim((string) $request->query('q', ''));

        $companyBrandsActive = CompanyBrand::where('is_active', true)
            ->orderBy('name')
            ->get();

        $brandIds = $companyBrandsActive
            ->when($brandFilter, fn ($c) => $c->where('_id', $brandFilter))
            ->pluck('_id')->map(fn ($i) => (string) $i)->all();

        $modelsQuery = CompanyBoatModel::whereIn('company_brand_id', $brandIds)
            ->where('is_archived', false);

        if ($q !== '') {
            $modelsQuery->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                  ->orWhere('code', 'like', "%{$q}%");
            });
        }

        $models = $modelsQuery->orderBy('name')->get();

        $modelIds = $models->pluck('_id')->map(fn ($i) => (string) $i)->all();
        $variants = CompanyBoatVariant::whereIn('company_model_id', $modelIds)
            ->where('is_archived', false)
            ->orderBy('name')
            ->get();

        $brandsById = $companyBrandsActive->keyBy(fn ($b) => (string) $b->_id);
        $modelsById = $models->keyBy(fn ($m) => (string) $m->_id);

        $rows = $variants->map(function ($v) use ($brandsById, $modelsById) {
            $model = $modelsById[(string) $v->company_model_id] ?? null;
            $brand = $model ? ($brandsById[(string) $model->company_brand_id] ?? null) : null;
            return ['variant' => $v, 'model' => $model, 'brand' => $brand];
        });

        // Boats with no version yet still get one row so they appear in the
        // catalogue as "Draft". A boat is Draft until its first version is
        // added (the list derives the status from whether a variant exists).
        $modelsWithVariants = $variants
            ->pluck('company_model_id')->map(fn ($i) => (string) $i)->unique()->all();
        $versionlessRows = $models
            ->reject(fn ($m) => in_array((string) $m->_id, $modelsWithVariants, true))
            ->map(fn ($m) => [
                'variant' => null,
                'model'   => $m,
                'brand'   => $brandsById[(string) $m->company_brand_id] ?? null,
            ])
            ->values();
        $rows = $rows->concat($versionlessRows);

        return view('catalogue.models', [
            'rows'        => $rows,
            'brands'      => $companyBrandsActive,
            'brandFilter' => $brandFilter,
        ]);
    }

    /**
     * Cherry-pick activation: snapshot a single global variant (and its
     * parent model + the model's options if they don't yet exist) into the
     * workspace. The parent brand MUST already be activated and active in
     * the dealer's workspace — we don't auto-create or auto-reactivate it,
     * because the dealer just deactivated it on purpose.
     */
    public function activateVariant(string $globalVariantId)
    {
        $globalVariant = GlobalBoatVariant::findOrFail($globalVariantId);
        $globalModel   = GlobalBoatModel::findOrFail($globalVariant->model_id);
        $globalBrand   = GlobalBrand::findOrFail($globalModel->brand_id);

        $error = $this->snapshotGlobalVariantOrFail($globalVariant, $globalModel, $globalBrand);

        if ($error) {
            return back()->withErrors(['variants' => $error]);
        }

        return back()->with('status', __('Added «:name» to your workspace.', ['name' => $globalVariant->name]));
    }

    /**
     * Bulk-activate global variants ticked from the "Available" tab.
     * Per-variant errors are collected and returned together so the user
     * sees one toast describing what was skipped and why.
     */
    public function activateVariantsBulk(Request $request)
    {
        $ids = $request->input('variant_ids', []);
        if (empty($ids) || ! is_array($ids)) {
            return back()->withErrors(['variants' => __('Pick at least one variant first.')]);
        }

        $added   = 0;
        $skipped = []; // [brandName => count]

        foreach ($ids as $globalVariantId) {
            $globalVariant = GlobalBoatVariant::find($globalVariantId);
            if (! $globalVariant) continue;

            $globalModel = GlobalBoatModel::find($globalVariant->model_id);
            $globalBrand = $globalModel ? GlobalBrand::find($globalModel->brand_id) : null;
            if (! $globalModel || ! $globalBrand) continue;

            $error = $this->snapshotGlobalVariantOrFail($globalVariant, $globalModel, $globalBrand);
            if ($error) {
                $skipped[$globalBrand->name] = ($skipped[$globalBrand->name] ?? 0) + 1;
                continue;
            }
            $added++;
        }

        if ($added > 0 && empty($skipped)) {
            return back()->with('status', __(':count variant(s) added to your workspace.', ['count' => $added]));
        }

        if ($added === 0 && ! empty($skipped)) {
            return back()->withErrors(['variants' => $this->formatSkippedMessage($skipped)]);
        }

        // Mixed result — partial success.
        return back()
            ->with('status', __(':count variant(s) added.', ['count' => $added]))
            ->withErrors(['variants' => $this->formatSkippedMessage($skipped)]);
    }

    /**
     * Snapshot a global variant into the dealer's workspace. Returns null
     * on success, or a user-facing error string when activation should be
     * blocked (e.g. the parent brand isn't active in the workspace).
     */
    private function snapshotGlobalVariantOrFail(
        GlobalBoatVariant $globalVariant,
        GlobalBoatModel $globalModel,
        GlobalBrand $globalBrand,
    ): ?string {
        $companyId = auth()->user()->company_id;

        $companyBrand = CompanyBrand::where('company_id', $companyId)
            ->where('global_brand_id', (string) $globalBrand->_id)
            ->first();

        if (! $companyBrand) {
            return "Activate the brand «{$globalBrand->name}» first — go to Brands → Available globally.";
        }

        if (! $companyBrand->is_active) {
            return "The brand «{$globalBrand->name}» is deactivated in your workspace. Re-activate it from the Brands page first.";
        }

        // Snapshot the parent model + options (idempotent).
        $companyModel = $this->catalogue->snapshotGlobalModel($companyId, $companyBrand, $globalModel);

        $exists = CompanyBoatVariant::where('company_id', $companyId)
            ->where('global_variant_id', (string) $globalVariant->_id)
            ->first();

        if (! $exists) {
            CompanyBoatVariant::create([
                'company_id'         => $companyId,
                'company_model_id'   => (string) $companyModel->_id,
                'global_variant_id'  => (string) $globalVariant->_id,
                'source'             => 'global',
                'name'               => $globalVariant->name,
                'base_price'         => (float) $globalVariant->base_price,
                'cost'               => (float) $globalVariant->cost,
                'currency'           => $globalVariant->currency ?? 'EUR',
                'included_equipment' => $globalVariant->included_equipment ?? [],
                'is_active'          => true,
                'is_archived'        => false,
            ]);
        } else {
            $exists->update(['is_active' => true, 'is_archived' => false]);
        }

        return null;
    }

    private function formatSkippedMessage(array $skipped): string
    {
        $brands = collect($skipped)
            ->map(fn ($n, $brand) => "{$n} from «{$brand}»")
            ->values()
            ->join(', ');
        return "Skipped {$brands} — activate the brand from the Brands page first.";
    }

    public function toggleVariant(string $companyVariantId, Request $request)
    {
        $variant = CompanyBoatVariant::findOrFail($companyVariantId);
        $variant->update(['is_active' => ! $variant->is_active]);
        return back()->with('status', $variant->is_active
            ? "«{$variant->name}» enabled."
            : "«{$variant->name}» hidden from the quote builder.");
    }

    /* ------------------------------------------- PRIVATE MODEL CRUD */

    /**
     * Render the boat editor in "new" mode — same view as edit, but the
     * model is an unsaved instance and the form posts to storeModel(). Once
     * the dealer hits Save, they're redirected to the real editor URL with
     * the saved id and the Versions / Options / Equipment tabs become
     * available. This unifies the UX so the dealer never sees a different
     * "Add" form.
     */
    public function createModel(Request $request)
    {
        $brands   = CompanyBrand::where('is_active', true)->orderBy('name')->get();
        $variants = collect();
        $options  = collect();

        $libraryEquipment = \App\Models\GlobalEquipment::where('is_active', true)
            ->orderBy('category')->orderBy('label')->get()
            ->groupBy('category');
        $libraryOptions = \App\Models\GlobalOptionItem::where('is_active', true)
            ->orderBy('category')->orderBy('label')->get();
        $libraryEngines = \App\Models\GlobalEngine::where('is_active', true)
            ->orderBy('brand')->orderBy('code')->get();

        // Empty placeholder — the view detects $model->exists === false and
        // hides the tabs that don't apply yet.
        $model = new CompanyBoatModel([
            'company_brand_id' => $request->query('brand'),
            'is_active'        => true,
            'year'             => (int) date('Y'),
            'default_margin_pct' => 20,
        ]);

        return view('catalogue.model-edit', compact(
            'model', 'brands', 'variants', 'options',
            'libraryEquipment', 'libraryOptions', 'libraryEngines'
        ));
    }

    public function storeModel(Request $request)
    {
        $data = $request->validate([
            // Brand is optional at creation: a dealer can save a boat with
            // just its name and fill in the brand / versions / options later.
            'company_brand_id'    => 'nullable',
            'code'                => 'nullable|string|max:60',
            'internal_code'       => 'nullable|string|max:60',
            'name'                => 'required|string|max:200',
            'complement'          => 'nullable|string|max:120',
            'year'                => 'nullable|integer|min:1900|max:2100',
            'type'                => 'nullable|in:open,cabin,semi-rigid,day-cruiser,fishing,sail,unknown',
            'propulsion'          => 'nullable|in:outboard,inboard,sail,unknown',
            'length_total'        => 'nullable|numeric|min:0',
            'length_hull'         => 'nullable|numeric|min:0',
            'length_waterline'    => 'nullable|numeric|min:0',
            'draft_min'           => 'nullable|numeric|min:0',
            'draft_max'           => 'nullable|numeric|min:0',
            'beam'                => 'nullable|numeric|min:0',
            'weight'              => 'nullable|numeric|min:0',
            'capacity'            => 'nullable|array',
            'supplier'            => 'nullable|string|max:200',
            'notes'               => 'nullable|string|max:5000',
            'default_margin_pct'  => 'nullable|numeric|min:0|max:100',
            'is_active'           => 'nullable|boolean',

            // Nested arrays — when the dealer fills versions / options /
            // equipment on the same Add-boat page they all post together.
            'versions'                  => 'nullable|array',
            'versions.*.name'           => 'required_with:versions|string|max:200',
            'versions.*.base_price'     => 'required_with:versions|numeric|min:0',
            'versions.*.cost'           => 'nullable|numeric|min:0',
            'versions.*.currency'       => 'nullable|in:EUR,USD',
            'versions.*.equipment'      => 'nullable|array',
            'versions.*.equipment.*'    => 'string|max:200',

            'new_options'                  => 'nullable|array',
            'new_options.*.category'       => 'required_with:new_options|string|max:100',
            'new_options.*.label'          => 'required_with:new_options|string|max:200',
            'new_options.*.price'          => 'required_with:new_options|numeric|min:0',
            'new_options.*.cost'           => 'nullable|numeric|min:0',

            'library_option_ids'      => 'nullable|array',
            'library_option_ids.*'    => 'string',

            'included_equipment_refs' => 'nullable|array',
            'included_equipment_refs.*' => 'string',
        ]);

        // Inline picker may have handed us a `global:<id>` reference; swap
        // it for a real CompanyBrand id, activating the global brand into
        // the workspace if this is the first time the dealer's used it.
        // When left blank the boat is saved brandless and can be assigned a
        // brand later from the editor.
        $data['company_brand_id'] = filled($data['company_brand_id'] ?? null)
            ? $this->resolveBrandId($data['company_brand_id'])
            : null;

        // Pull nested arrays out of the boat payload before persisting.
        $versions       = $data['versions'] ?? [];
        $newOptions     = $data['new_options'] ?? [];
        $libraryOptIds  = $data['library_option_ids'] ?? [];
        unset($data['versions'], $data['new_options'], $data['library_option_ids']);
        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        // Boat code is no longer asked from the dealer — auto-generate from
        // the name + a short random suffix so internal lookups that read
        // CompanyBoatModel.code still resolve.
        if (empty($data['code'])) {
            $data['code'] = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::slug($data['name'])) . '-' . \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(4));
        }

        $model = CompanyBoatModel::create(array_merge($data, [
            'company_id'      => auth()->user()->company_id,
            'global_model_id' => null,
            'source'          => CompanyBoatModel::SOURCE_PRIVATE,
            'is_archived'     => false,
        ]));

        // Versions
        foreach ($versions as $v) {
            CompanyBoatVariant::create([
                'company_id'         => auth()->user()->company_id,
                'company_model_id'   => (string) $model->_id,
                'global_variant_id'  => null,
                'source'             => 'private',
                'name'               => $v['name'],
                'base_price'         => (float) $v['base_price'],
                'cost'               => (float) ($v['cost'] ?? 0),
                'currency'           => $v['currency'] ?? 'EUR',
                'included_equipment' => $this->normaliseEquipmentList($v['equipment'] ?? []),
                'is_active'          => true,
                'is_archived'        => false,
            ]);
        }

        // Custom options
        foreach ($newOptions as $o) {
            CompanyOption::create([
                'company_id'        => auth()->user()->company_id,
                'company_model_id'  => (string) $model->_id,
                'global_option_id'  => null,
                'source'            => 'private',
                'category'          => $o['category'],
                'label'             => $o['label'],
                'price'             => (float) $o['price'],
                'cost'              => (float) ($o['cost'] ?? 0),
                'currency'          => $o['currency'] ?? 'EUR',
                'position'          => 0,
                'is_archived'       => false,
            ]);
        }

        // Library options copied onto this boat
        if (! empty($libraryOptIds)) {
            $request->merge(['option_ids' => $libraryOptIds]);
            $this->importGlobalOptions((string) $model->_id, $request);
        }

        return redirect()->route('catalogue.models.edit', $model->_id)
            ->with('status', __('Boat «:name» created.', ['name' => $model->name]));
    }

    public function editModel(string $modelId)
    {
        $model    = CompanyBoatModel::findOrFail($modelId);
        $brands   = CompanyBrand::where('is_active', true)->orderBy('name')->get();
        $variants = CompanyBoatVariant::where('company_model_id', $modelId)->where('is_archived', false)->get();
        $options  = CompanyOption::where('company_model_id', $modelId)->where('is_archived', false)->orderBy('position')->get();

        // Global library merged into the editor for one-click picking.
        $libraryEquipment = \App\Models\GlobalEquipment::where('is_active', true)
            ->orderBy('category')->orderBy('label')->get()
            ->groupBy('category');

        $libraryOptions = \App\Models\GlobalOptionItem::where('is_active', true)
            ->orderBy('category')->orderBy('label')->get();

        $libraryEngines = \App\Models\GlobalEngine::where('is_active', true)
            ->orderBy('brand')->orderBy('code')->get();

        return view('catalogue.model-edit', compact(
            'model', 'brands', 'variants', 'options',
            'libraryEquipment', 'libraryOptions', 'libraryEngines'
        ));
    }

    public function updateModel(string $modelId, Request $request)
    {
        $model = CompanyBoatModel::findOrFail($modelId);
        $data = $request->validate([
            'company_brand_id'    => 'nullable',
            'code'                => 'nullable|string|max:60',
            'internal_code'       => 'nullable|string|max:60',
            'name'                => 'required|string|max:200',
            'complement'          => 'nullable|string|max:120',
            'year'                => 'nullable|integer|min:1900|max:2100',
            'type'                => 'nullable|in:open,cabin,semi-rigid,day-cruiser,fishing,sail,unknown',
            'propulsion'          => 'nullable|in:outboard,inboard,sail,unknown',
            'length_total'        => 'nullable|numeric|min:0',
            'length_hull'         => 'nullable|numeric|min:0',
            'length_waterline'    => 'nullable|numeric|min:0',
            'draft_min'           => 'nullable|numeric|min:0',
            'draft_max'           => 'nullable|numeric|min:0',
            'beam'                => 'nullable|numeric|min:0',
            'weight'              => 'nullable|numeric|min:0',
            'capacity'            => 'nullable|array',
            'included_equipment_refs' => 'nullable|array',
            'included_equipment_refs.*' => 'string',
            'supplier'            => 'nullable|string|max:200',
            'notes'               => 'nullable|string|max:5000',
            'default_margin_pct'  => 'nullable|numeric|min:0|max:100',
            'is_active'           => 'nullable|boolean',
        ]);

        // Equipment refs come from the form as "source:id" strings; store as is.
        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        // If the brand changed, resolve `global:<id>` references and verify
        // ownership of any workspace brand id.
        if (! empty($data['company_brand_id'])) {
            $data['company_brand_id'] = $this->resolveBrandId($data['company_brand_id']);
        } else {
            unset($data['company_brand_id']);
        }

        // Don't blank out the existing code if the form didn't submit one
        // (the field has been removed from the dealer form).
        if (empty($data['code'])) {
            unset($data['code']);
        }

        $model->update($data);
        return back()->with('status', __('Boat saved.'));
    }

    public function destroyModel(string $modelId)
    {
        $model = CompanyBoatModel::findOrFail($modelId);
        if ($model->source !== CompanyBoatModel::SOURCE_PRIVATE) {
            return back()->withErrors(['model' => 'Global models can only be archived, not deleted.']);
        }
        CompanyOption::where('company_model_id', $modelId)->delete();
        CompanyBoatVariant::where('company_model_id', $modelId)->delete();
        $model->delete();
        return redirect()->route('catalogue.models')->with('status', __('Model deleted.'));
    }

    /* ---------------------------------------------- VARIANT EDITS */

    /**
     * Standalone "Add variant" wizard — pick brand, then model, then fill
     * the variant's details + included equipment in one form.
     */
    public function createVariant(Request $request)
    {
        $brands = CompanyBrand::where('is_active', true)->orderBy('name')->get();

        $brandId = $request->query('brand');
        $models  = $brandId
            ? CompanyBoatModel::where('company_brand_id', $brandId)
                ->where('is_archived', false)
                ->orderBy('name')->get()
            : collect();

        return view('catalogue.variant-create', [
            'brands'   => $brands,
            'models'   => $models,
            'brandId'  => $brandId,
        ]);
    }

    public function storeVariantStandalone(Request $request)
    {
        $data = $request->validate([
            'company_brand_id'  => 'required',
            'company_model_id'  => 'required',
            'name'              => 'required|string|max:200',
            'base_price'        => 'required|numeric|min:0',
            'cost'              => 'nullable|numeric|min:0',
            'currency'          => 'nullable|in:EUR,USD',
            'equipment'         => 'array',
            'equipment.*.label' => 'nullable|string|max:200',
            'equipment.*.type'  => 'nullable|in:standard,free_text',
        ]);

        // Both ids are tenant-scoped, so findOrFail enforces ownership.
        // Brand may arrive as `global:<id>` from the inline picker —
        // resolve + auto-activate if so.
        $data['company_brand_id'] = $this->resolveBrandId($data['company_brand_id']);
        $model = CompanyBoatModel::findOrFail($data['company_model_id']);

        // Drop blank equipment rows.
        $equipment = collect($data['equipment'] ?? [])
            ->filter(fn ($row) => !empty(trim($row['label'] ?? '')))
            ->map(fn ($row) => [
                'label' => trim($row['label']),
                'type'  => $row['type'] ?? 'standard',
            ])
            ->values()
            ->all();

        $variant = CompanyBoatVariant::create([
            'company_id'         => auth()->user()->company_id,
            'company_model_id'   => (string) $model->_id,
            'global_variant_id'  => null,
            'source'             => 'private',
            'name'               => $data['name'],
            'base_price'         => (float) $data['base_price'],
            'cost'               => (float) ($data['cost'] ?? 0),
            'currency'           => $data['currency'] ?? 'EUR',
            'included_equipment' => $equipment,
            'is_active'          => true,
            'is_archived'        => false,
        ]);

        return redirect()->route('catalogue.models', ['tab' => 'workspace'])
            ->with('status', __('Variant «:name» added to your workspace.', ['name' => $variant->name]));
    }

    public function storeVariant(string $modelId, Request $request)
    {
        $model = CompanyBoatModel::findOrFail($modelId);
        $data = $request->validate([
            'name'         => 'required|string|max:200',
            'base_price'   => 'required|numeric|min:0',
            'cost'         => 'nullable|numeric|min:0',
            'currency'     => 'nullable|in:EUR,USD',
            'equipment'    => 'nullable|array',
            'equipment.*'  => 'string|max:200',
        ]);
        CompanyBoatVariant::create([
            'company_id'         => auth()->user()->company_id,
            'company_model_id'   => $modelId,
            'global_variant_id'  => null,
            'source'             => 'private',
            'name'               => $data['name'],
            'base_price'         => (float) $data['base_price'],
            'cost'               => (float) ($data['cost'] ?? 0),
            'currency'           => $data['currency'] ?? 'EUR',
            'included_equipment' => $this->normaliseEquipmentList($data['equipment'] ?? []),
            'is_active'          => true,
            'is_archived'        => false,
        ]);
        return back()->with('status', __('Variant added.'));
    }

    public function updateVariant(string $variantId, Request $request)
    {
        $variant = CompanyBoatVariant::findOrFail($variantId);
        $data = $request->validate([
            'name'         => 'required|string|max:200',
            'base_price'   => 'required|numeric|min:0',
            'cost'         => 'nullable|numeric|min:0',
            'currency'     => 'nullable|in:EUR,USD',
            'equipment'    => 'nullable|array',
            'equipment.*'  => 'string|max:200',
        ]);
        $variant->update([
            'name'               => $data['name'],
            'base_price'         => (float) $data['base_price'],
            'cost'               => (float) ($data['cost'] ?? $variant->cost),
            'currency'           => $data['currency'] ?? $variant->currency,
            'included_equipment' => $this->normaliseEquipmentList($data['equipment'] ?? []),
        ]);
        return back()->with('status', __('Variant «:name» updated.', ['name' => $variant->name]));
    }

    /**
     * Bulk "Save versions" from the boat editor's Versions tab. The posted
     * `versions[]` is the COMPLETE desired list for this model: rows with an
     * `id` are updated, rows without are created (private), and any existing
     * variant whose id is absent from the payload is removed (global-sourced
     * → soft-archived, private → deleted). Existing quotes keep their
     * snapshots, so removals are safe.
     */
    public function syncVariants(string $modelId, Request $request)
    {
        $model = CompanyBoatModel::findOrFail($modelId);
        $data = $request->validate([
            'versions'               => 'nullable|array',
            'versions.*.id'          => 'nullable|string',
            'versions.*.name'        => 'required|string|max:200',
            'versions.*.base_price'  => 'required|numeric|min:0',
            'versions.*.cost'        => 'nullable|numeric|min:0',
            'versions.*.currency'    => 'nullable|in:EUR,USD',
            'versions.*.equipment'   => 'nullable|array',
            'versions.*.equipment.*' => 'string|max:200',
        ]);

        $this->syncVariantRows($modelId, $data['versions'] ?? []);

        return back()->with('status', __('Versions saved.'));
    }

    /**
     * Upsert the given version rows for a model and delete/archive any of the
     * model's variants not present in the list. Shared by syncVariants() and
     * the unified saveAll().
     */
    private function syncVariantRows(string $modelId, array $rows): void
    {
        $companyId = auth()->user()->company_id;
        $keptIds   = [];

        foreach ($rows as $row) {
            $payload = [
                'name'               => $row['name'],
                'base_price'         => (float) $row['base_price'],
                'cost'               => (float) ($row['cost'] ?? 0),
                'currency'           => $row['currency'] ?? 'EUR',
                'included_equipment' => $this->normaliseEquipmentList($row['equipment'] ?? []),
            ];

            $existing = ! empty($row['id'])
                ? CompanyBoatVariant::where('_id', $row['id'])->where('company_model_id', $modelId)->first()
                : null;

            if ($existing) {
                $existing->update($payload);
                $keptIds[] = (string) $existing->_id;
            } else {
                $created = CompanyBoatVariant::create(array_merge($payload, [
                    'company_id'        => $companyId,
                    'company_model_id'  => $modelId,
                    'global_variant_id' => null,
                    'source'            => 'private',
                    'is_active'         => true,
                    'is_archived'       => false,
                ]));
                $keptIds[] = (string) $created->_id;
            }
        }

        foreach (CompanyBoatVariant::where('company_model_id', $modelId)->whereNotIn('_id', $keptIds)->get() as $orphan) {
            if ($orphan->source === 'global') {
                $orphan->update(['is_archived' => true, 'is_active' => false]);
            } else {
                $orphan->delete();
            }
        }
    }

    /**
     * Bulk "Save options" from the boat editor's Options tab. Same sync
     * semantics as syncVariants. Each row carries a single currency; non-EUR
     * amounts are converted to EUR via the live FX rate (original kept for
     * audit), and row order sets `position`.
     */
    public function syncOptions(string $modelId, Request $request)
    {
        CompanyBoatModel::findOrFail($modelId);
        $data = $request->validate([
            'options'            => 'nullable|array',
            'options.*.id'       => 'nullable|string',
            'options.*.category' => 'required|string|max:100',
            'options.*.label'    => 'required|string|max:200',
            'options.*.price'    => 'required|numeric|min:0',
            'options.*.cost'     => 'nullable|numeric|min:0',
            'options.*.currency' => 'nullable|in:EUR,USD',
        ]);

        $this->syncOptionRows($modelId, $data['options'] ?? []);

        return back()->with('status', __('Options saved.'));
    }

    /**
     * Upsert the given option rows for a model and delete/archive any of the
     * model's options not present in the list. FX-converts non-EUR amounts.
     * Shared by syncOptions() and the unified saveAll().
     */
    private function syncOptionRows(string $modelId, array $rows): void
    {
        $companyId = auth()->user()->company_id;
        $fx        = app(\App\Services\FxRateService::class);
        $keptIds   = [];

        foreach ($rows as $i => $row) {
            $priceIn = (float) $row['price'];
            $costIn  = (float) ($row['cost'] ?? 0);
            $ccy     = $row['currency'] ?? 'EUR';

            $priceEur = $priceIn;
            $costEur  = $costIn;
            $fxRate   = null;
            if ($ccy !== 'EUR') {
                $rate = $fx->rate($ccy, 'EUR');
                if ($rate !== null) {
                    $priceEur = round($priceIn * $rate, 2);
                    $costEur  = round($costIn * $rate, 2);
                    $fxRate   = $rate;
                }
            }

            $payload = [
                'category'                => $row['category'],
                'label'                   => $row['label'],
                'price'                   => $priceEur,
                'cost'                    => $costEur,
                'currency'                => 'EUR',
                'original_price'          => $priceIn,
                'original_price_currency' => $ccy,
                'original_cost'           => $costIn,
                'original_cost_currency'  => $ccy,
                'fx_rate_used'            => $ccy !== 'EUR' ? $fxRate : null,
                'fx_rate_date'            => $ccy !== 'EUR' ? now() : null,
                'position'                => $i,
            ];

            $existing = ! empty($row['id'])
                ? CompanyOption::where('_id', $row['id'])->where('company_model_id', $modelId)->first()
                : null;

            if ($existing) {
                $existing->update($payload);
                $keptIds[] = (string) $existing->_id;
            } else {
                $created = CompanyOption::create(array_merge($payload, [
                    'company_id'       => $companyId,
                    'company_model_id' => $modelId,
                    'global_option_id' => null,
                    'source'           => 'private',
                    'is_archived'      => false,
                ]));
                $keptIds[] = (string) $created->_id;
            }
        }

        foreach (CompanyOption::where('company_model_id', $modelId)->whereNotIn('_id', $keptIds)->get() as $orphan) {
            if ($orphan->source === 'global') {
                $orphan->update(['is_archived' => true]);
            } else {
                $orphan->delete();
            }
        }
    }

    /**
     * Unified save from the boat editor: persists the boat fields, versions,
     * and options in ONE request so the dealer can't lose work by switching
     * tabs. Redirects back to the tab they saved from.
     */
    public function saveAll(string $modelId, Request $request)
    {
        $model = CompanyBoatModel::findOrFail($modelId);
        $data = $request->validate([
            // Boat
            'company_brand_id'       => 'nullable',
            'name'                   => 'required|string|max:200',
            'default_margin_pct'     => 'nullable|numeric|min:0|max:100',
            'is_active'              => 'nullable|boolean',
            // Versions
            'versions'               => 'nullable|array',
            'versions.*.id'          => 'nullable|string',
            'versions.*.name'        => 'required|string|max:200',
            'versions.*.base_price'  => 'required|numeric|min:0',
            'versions.*.cost'        => 'nullable|numeric|min:0',
            'versions.*.currency'    => 'nullable|in:EUR,USD',
            'versions.*.equipment'   => 'nullable|array',
            'versions.*.equipment.*' => 'string|max:200',
            // Options
            'options'                => 'nullable|array',
            'options.*.id'           => 'nullable|string',
            'options.*.category'     => 'required|string|max:100',
            'options.*.label'        => 'required|string|max:200',
            'options.*.price'        => 'required|numeric|min:0',
            'options.*.cost'         => 'nullable|numeric|min:0',
            'options.*.currency'     => 'nullable|in:EUR,USD',
            'active_tab'             => 'nullable|in:boat,versions,options',
        ]);

        // Boat fields
        $boat = [
            'name'      => $data['name'],
            'is_active' => (bool) ($data['is_active'] ?? false),
        ];
        if (array_key_exists('default_margin_pct', $data)) {
            $boat['default_margin_pct'] = $data['default_margin_pct'];
        }
        if (! empty($data['company_brand_id'])) {
            $boat['company_brand_id'] = $this->resolveBrandId($data['company_brand_id']);
        }
        $model->update($boat);

        // Versions + options
        $this->syncVariantRows($modelId, $data['versions'] ?? []);
        $this->syncOptionRows($modelId, $data['options'] ?? []);

        return redirect()
            ->route('catalogue.models.edit', [$model->_id, 'tab' => $data['active_tab'] ?? 'boat'])
            ->with('status', __('Boat saved.'));
    }

    /**
     * Brand IDs coming from the inline picker are either:
     *   - a real `CompanyBrand._id` (workspace brand), or
     *   - `global:<GlobalBrand._id>` — meaning the dealer picked a brand
     *     from the platform library that's not yet in their workspace.
     *
     * In the global case we auto-activate the brand on the fly (idempotent:
     * if a CompanyBrand for that global already exists we re-use it, even
     * if it was deactivated) and return its `_id`. Plain workspace IDs are
     * returned unchanged after a tenant ownership check.
     *
     * Throws ModelNotFoundException for unknown IDs.
     */
    private function resolveBrandId(string $id): string
    {
        if (str_starts_with($id, 'global:')) {
            $globalId = substr($id, strlen('global:'));
            $global   = \App\Models\GlobalBrand::findOrFail($globalId);
            $companyId = auth()->user()->company_id;

            // Re-use an existing CompanyBrand row that snapshots this
            // global (whether active or not) so dealers don't end up with
            // duplicates after activate/deactivate cycles.
            $existing = CompanyBrand::where('company_id', $companyId)
                ->where('global_brand_id', (string) $global->_id)
                ->first();

            if ($existing) {
                if (! $existing->is_active) {
                    $existing->update(['is_active' => true]);
                }
                return (string) $existing->_id;
            }

            $companyBrand = $this->catalogue->activateGlobalBrand($companyId, $global);
            return (string) $companyBrand->_id;
        }

        // Workspace ID — findOrFail enforces tenant ownership via the
        // TenantScope on CompanyBrand.
        return (string) CompanyBrand::findOrFail($id)->_id;
    }

    /**
     * Accept either a flat list of labels (["Bimini", "Hot water"]) — the
     * new paste-modal shape — and turn it into the [{label, type}] array
     * we persist on the variant. Blanks are stripped, duplicates kept (the
     * UI dedupes client-side already).
     */
    private function normaliseEquipmentList(array $list): array
    {
        return collect($list)
            ->map(fn ($l) => trim((string) $l))
            ->filter(fn ($l) => $l !== '')
            ->map(fn ($l) => ['label' => $l, 'type' => 'standard'])
            ->values()
            ->all();
    }

    public function destroyVariant(string $variantId)
    {
        $variant = CompanyBoatVariant::findOrFail($variantId);
        // For global-sourced variants we soft-archive, so the link to the
        // global record stays intact for future updates. Private variants
        // can be hard-deleted.
        if ($variant->source === 'global') {
            $variant->update(['is_archived' => true, 'is_active' => false]);
        } else {
            $variant->delete();
        }
        return back()->with('status', __('Variant removed.'));
    }

    /* ---------------------------------------------- OPTION EDITS */

    /**
     * Copy a platform-global option onto this specific boat. Once copied
     * the dealer can adjust price/cost/etc. per-boat without affecting
     * the original library item or other boats.
     */
    public function importGlobalOptions(string $modelId, Request $request)
    {
        $model = CompanyBoatModel::findOrFail($modelId);
        $ids = $request->input('option_ids', []);
        if (empty($ids) || ! is_array($ids)) {
            return back()->withErrors(['options' => 'Pick at least one option from the library.']);
        }

        $count = 0;
        foreach ($ids as $globalId) {
            $g = \App\Models\GlobalOptionItem::find($globalId);
            if (! $g) continue;

            // Skip if this boat already has this exact label+category.
            $dup = CompanyOption::where('company_model_id', $modelId)
                ->where('label', $g->label)
                ->where('category', $g->category)
                ->exists();
            if ($dup) continue;

            CompanyOption::create([
                'company_id'        => auth()->user()->company_id,
                'company_model_id'  => $modelId,
                'global_option_id'  => null,  // we don't link — it's a snapshot
                'source'            => 'global', // imported from global library
                'category'          => $g->category,
                'label'             => $g->label,
                'price'             => (float) $g->price,
                'cost'              => 0.0,
                'currency'          => $g->currency ?? 'EUR',
                'position'          => 0,
                'is_archived'       => false,
            ]);
            $count++;
        }

        return back()->with('status', __(':count option(s) added from the library.', ['count' => $count]));
    }

    public function storeOption(string $modelId, Request $request)
    {
        CompanyBoatModel::findOrFail($modelId);
        $data = $request->validate([
            'category'  => 'required|string|max:100',
            'label'     => 'required|string|max:200',
            'price'     => 'required|numeric|min:0',
            'cost'      => 'nullable|numeric|min:0',
            'currency'  => 'nullable|in:EUR,USD',
            'position'  => 'nullable|integer',
        ]);
        CompanyOption::create([
            'company_id'        => auth()->user()->company_id,
            'company_model_id'  => $modelId,
            'global_option_id'  => null,
            'source'            => 'private',
            'category'          => $data['category'],
            'label'             => $data['label'],
            'price'             => (float) $data['price'],
            'cost'              => (float) ($data['cost'] ?? 0),
            'currency'          => $data['currency'] ?? 'EUR',
            'position'          => (int) ($data['position'] ?? 0),
            'is_archived'       => false,
        ]);
        return back()->with('status', __('Option added.'));
    }

    public function updateOption(string $optionId, Request $request)
    {
        $option = CompanyOption::findOrFail($optionId);
        $data = $request->validate([
            'category'       => 'required|string|max:100',
            'label'          => 'required|string|max:200',
            'price'          => 'required|numeric|min:0',
            'price_currency' => 'nullable|in:EUR,USD',
            'cost'           => 'nullable|numeric|min:0',
            'cost_currency'  => 'nullable|in:EUR,USD',
            // legacy single-currency field — kept for backwards-compatibility
            // with older form posts that don't send the per-field dropdowns.
            'currency'       => 'nullable|in:EUR,USD',
            'position'       => 'nullable|integer',
        ]);

        $priceIn = (float) $data['price'];
        $costIn  = $data['cost'] !== null && $data['cost'] !== '' ? (float) $data['cost'] : (float) $option->cost;
        $priceCcy = $data['price_currency'] ?? $data['currency'] ?? 'EUR';
        $costCcy  = $data['cost_currency']  ?? $data['currency'] ?? 'EUR';

        // FX: convert non-EUR amounts to EUR using a live rate. Stash the
        // original so the dealer can see what was entered before the
        // conversion. If the rate lookup fails, persist the typed value
        // verbatim and flag a warning — at least the data isn't lost.
        $fx = app(\App\Services\FxRateService::class);
        $priceEur = $priceIn;
        $costEur  = $costIn;
        $fxRate   = 1.0;
        $fxError  = null;

        if ($priceCcy !== 'EUR' && $priceIn > 0) {
            $rate = $fx->rate($priceCcy, 'EUR');
            if ($rate === null) {
                $fxError = 'Could not fetch FX rate for ' . $priceCcy . '→EUR. Value saved as entered.';
            } else {
                $priceEur = round($priceIn * $rate, 2);
                $fxRate   = $rate;
            }
        }
        if ($costCcy !== 'EUR' && $costIn > 0) {
            $rate = $fx->rate($costCcy, 'EUR');
            if ($rate === null) {
                $fxError = 'Could not fetch FX rate for ' . $costCcy . '→EUR. Value saved as entered.';
            } else {
                $costEur = round($costIn * $rate, 2);
                $fxRate  = $rate;
            }
        }

        $option->update([
            'category'                => $data['category'],
            'label'                   => $data['label'],
            'price'                   => $priceEur,
            'cost'                    => $costEur,
            'currency'                => 'EUR',
            'original_price'          => $priceIn,
            'original_price_currency' => $priceCcy,
            'original_cost'           => $costIn,
            'original_cost_currency'  => $costCcy,
            'fx_rate_used'            => ($priceCcy !== 'EUR' || $costCcy !== 'EUR') ? $fxRate : null,
            'fx_rate_date'            => ($priceCcy !== 'EUR' || $costCcy !== 'EUR') ? now() : null,
            'position'                => (int) ($data['position'] ?? $option->position),
        ]);

        $msg = $fxError ?: __('Option updated.');
        return back()->with('status', $msg);
    }

    public function destroyOption(string $optionId)
    {
        $option = CompanyOption::findOrFail($optionId);
        if ($option->source === 'global') {
            $option->update(['is_archived' => true]);
        } else {
            $option->delete();
        }
        return back()->with('status', __('Option removed.'));
    }

    public function reorderOptions(string $modelId, Request $request)
    {
        CompanyBoatModel::findOrFail($modelId);
        $data = $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'string',
        ]);
        foreach ($data['ids'] as $i => $id) {
            CompanyOption::where('_id', $id)
                ->where('company_model_id', $modelId)
                ->update(['position' => $i]);
        }
        return response()->json(['ok' => true]);
    }

    /* ---------------------------------------------- UPDATES TAB */

    public function updates()
    {
        // Phase 2 — actual notification list. For now empty state but with
        // the new wiring already pointing here.
        return view('catalogue.updates');
    }

    /* ----------------------------------------------- OPTIONS BULK IMPORT */

    /**
     * Stream the option-import template for a specific boat — the boat is
     * implied by the URL so the file omits the CODE MODELE column entirely.
     * The dealer doesn't need to know or type the boat's internal code.
     */
    public function optionsTemplateForBoat(string $modelId)
    {
        $companyId = (string) auth()->user()->company_id;
        $boat = CompanyBoatModel::where('_id', $modelId)
            ->where('company_id', $companyId)
            ->firstOrFail();

        $path = $this->buildOptionsTemplateXlsx(boatName: $boat->name);
        $safeName = preg_replace('/[^A-Za-z0-9_-]+/', '-', $boat->name);
        return response()->download($path, "nautiqs-options-{$safeName}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function importOptionsForBoat(string $modelId, Request $request, OptionImporter $importer)
    {
        $request->validate([
            'file' => 'required|file|max:10240|mimes:csv,txt,xlsx,xlsm',
        ], [], [
            'file' => __('File'),
        ]);

        $companyId = (string) auth()->user()->company_id;
        $boat = CompanyBoatModel::where('_id', $modelId)
            ->where('company_id', $companyId)
            ->firstOrFail();

        $result = $importer->import($request->file('file'), $companyId, (string) $boat->_id);

        $msg = __(':created created, :updated updated, :skipped skipped.', [
            'created' => $result['created'],
            'updated' => $result['updated'],
            'skipped' => $result['skipped'],
        ]);

        return redirect()->route('catalogue.models.edit', $boat->_id)
            ->with('status', $msg)
            ->with('import_result', $result);
    }

    /**
     * Parse an uploaded options file and return the rows as JSON WITHOUT
     * persisting. Used by the Add-boat screen: the boat doesn't exist yet, so
     * instead of importing-to-a-boat we hand the parsed rows back to the form,
     * which pre-fills the options repeater. They then save together with the
     * new boat via storeModel(). Reuses the exact same parser as edit mode.
     */
    public function parseOptionsFile(Request $request, OptionImporter $importer)
    {
        $request->validate([
            'file' => 'required|file|max:10240|mimes:csv,txt,xlsx,xlsm',
        ], [], [
            'file' => __('File'),
        ]);

        $parsed = $importer->parse($request->file('file'));

        if ($parsed['fatal'] !== null) {
            return response()->json([
                'ok'      => false,
                'message' => $parsed['fatal'],
                'rows'    => [],
                'errors'  => [],
                'skipped' => 0,
            ], 422);
        }

        // Only the fields the create-mode repeater captures (category, label,
        // price, cost — all already in EUR).
        $rows = array_map(fn ($r) => [
            'category' => $r['category'],
            'label'    => $r['label'],
            'price'    => $r['price'],
            'cost'     => $r['cost'],
        ], $parsed['rows']);

        return response()->json([
            'ok'      => true,
            'rows'    => $rows,
            'errors'  => $parsed['errors'],
            'skipped' => $parsed['skipped'],
        ]);
    }

    /**
     * Boat-less options template download for the Add-boat screen (the boat
     * has no id yet). Same 7-column layout as the per-boat template.
     */
    public function optionsTemplate()
    {
        $path = $this->buildOptionsTemplateXlsx();
        return response()->download($path, 'nautiqs-options-template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Build a temp XLSX with the option-import columns and a few sample
     * rows so dealers can see exactly how it should look.
     *
     * When $boatName is set, the file is tailored for a single boat: the
     * CODE MODELE column is omitted (the import knows which boat from the
     * URL) and the samples reference the boat by name. This is the flow
     * launched from inside the boat editor's Options tab.
     */
    private function buildOptionsTemplateXlsx(?string $boatName = null): string
    {
        // Seven columns. PA / PV Currency default to EUR; if the dealer
        // writes USD, the import converts to EUR using a live FX rate.
        // TVA accepts 20 or 0.2. Code is auto-generated from (category,
        // label) on the server so re-imports update in place.
        $headers = ['FAMILLE', 'DESIGNATION', 'PA HT', 'PA CURRENCY', 'PV HT', 'PV CURRENCY', 'TVA'];
        $samples = [
            ['Transport',    'Bandol → Marseille',        3400, 'EUR', 4858.60, 'EUR', 20],
            ['Électronique', 'Garmin GPSMAP 1243xsv',     2100, 'EUR', 3200,    'EUR', 20],
            ['Confort',      'Plancher teck cockpit',     2800, 'EUR', 4500,    'EUR', 20],
            ['Confort',      'Bimini + rideaux',           950, 'EUR', 1800,    'EUR', 20],
            ['Électronique', 'Raymarine Axiom 12 (US)',   3200, 'USD', 4500,    'USD', 20],
        ];

        $strings = []; $sMap = [];
        $idx = function (string $s) use (&$strings, &$sMap): int {
            if (isset($sMap[$s])) return $sMap[$s];
            $i = count($strings);
            $strings[] = $s;
            $sMap[$s] = $i;
            return $i;
        };
        $colLetter = function (int $i): string {
            $i++; $s = '';
            while ($i > 0) { $r = ($i - 1) % 26; $s = chr(65 + $r) . $s; $i = intdiv($i - 1, 26); }
            return $s;
        };

        $rowsXml = '<row r="1">';
        foreach ($headers as $c => $h) {
            $rowsXml .= '<c r="' . $colLetter($c) . '1" t="s" s="1"><v>' . $idx($h) . '</v></c>';
        }
        $rowsXml .= '</row>';

        foreach ($samples as $r => $row) {
            $rowNum = $r + 2;
            $rowsXml .= '<row r="' . $rowNum . '">';
            foreach ($row as $c => $val) {
                $ref = $colLetter($c) . $rowNum;
                if ($val === '' || $val === null) continue;
                if (is_int($val) || is_float($val)) {
                    $rowsXml .= '<c r="' . $ref . '"><v>' . $val . '</v></c>';
                } else {
                    $rowsXml .= '<c r="' . $ref . '" t="s"><v>' . $idx((string) $val) . '</v></c>';
                }
            }
            $rowsXml .= '</row>';
        }

        $sstCount = 0;
        foreach (array_merge([$headers], $samples) as $row) {
            foreach ($row as $v) if (is_string($v) && $v !== '') $sstCount++;
        }
        $sstXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . $sstCount . '" uniqueCount="' . count($strings) . '">';
        foreach ($strings as $s) {
            $sstXml .= '<si><t xml:space="preserve">' . htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</t></si>';
        }
        $sstXml .= '</sst>';

        // Width per column (FAMILLE, DESIGNATION, PA HT, PA CURRENCY, PV HT, PV CURRENCY, TVA).
        $widths = [18, 40, 12, 14, 12, 14, 8];
        $colsXml = '<cols>';
        foreach ($widths as $i => $w) {
            $colsXml .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $w . '" customWidth="1"/>';
        }
        $colsXml .= '</cols>';

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews><sheetView tabSelected="1" workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . $colsXml
            . '<sheetData>' . $rowsXml . '</sheetData>'
            . '</worksheet>';

        $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Options" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';

        $workbookRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';

        $stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF0E4F79"/></patternFill></fill></fills>'
            . '<borders count="1"><border/></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf></cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';

        $contentTypesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';

        $rootRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';

        $path = tempnam(sys_get_temp_dir(), 'nautiqs-options-') . '.xlsx';
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', $contentTypesXml);
        $zip->addFromString('_rels/.rels', $rootRelsXml);
        $zip->addFromString('xl/workbook.xml', $workbookXml);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRelsXml);
        $zip->addFromString('xl/sharedStrings.xml', $sstXml);
        $zip->addFromString('xl/styles.xml', $stylesXml);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->close();

        return $path;
    }
}
