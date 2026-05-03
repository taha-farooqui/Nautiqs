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
        $tab = $request->query('tab', 'workspace'); // workspace | available

        $globalBrands  = GlobalBrand::where('is_active', true)->orderBy('name')->get();
        $companyBrands = CompanyBrand::orderBy('name')->get();

        // Index of globals that are activated AND still active. Deactivated
        // copies should appear under "Available" again so the dealer can
        // re-add them via Activate, rather than being hidden under a stale
        // "Already in your workspace" pill.
        $activatedGlobalIds = $companyBrands
            ->where('source', CompanyBrand::SOURCE_GLOBAL)
            ->where('is_active', true)
            ->pluck('global_brand_id')
            ->filter()
            ->all();

        return view('catalogue.brands', [
            'tab'                 => $tab,
            'globalBrands'        => $globalBrands,
            'companyBrands'       => $companyBrands,
            'activatedGlobalIds'  => $activatedGlobalIds,
        ]);
    }

    public function activateBrand(string $globalBrandId)
    {
        $brand = GlobalBrand::findOrFail($globalBrandId);
        $this->catalogue->activateGlobalBrand(auth()->user()->company_id, $brand);
        return back()->with('status', "Brand «{$brand->name}» activated — all models copied to your workspace.");
    }

    public function deactivateBrand(string $companyBrandId)
    {
        $companyBrand = CompanyBrand::findOrFail($companyBrandId);
        $this->catalogue->deactivateGlobalBrand(auth()->user()->company_id, $companyBrand);
        return back()->with('status', "Brand «{$companyBrand->name}» deactivated. Your customisations are kept.");
    }

    public function reactivateBrand(string $companyBrandId)
    {
        $companyBrand = CompanyBrand::findOrFail($companyBrandId);
        $companyBrand->update(['is_active' => true]);
        return back()->with('status', "Brand «{$companyBrand->name}» re-activated.");
    }

    public function storePrivateBrand(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:200',
            'description' => 'nullable|string|max:1000',
        ]);
        $brand = $this->catalogue->createPrivateBrand(auth()->user()->company_id, $data);
        return redirect()->route('catalogue.brands')
            ->with('status', "Private brand «{$brand->name}» created. Add your first model →");
    }

    public function destroyPrivateBrand(string $companyBrandId)
    {
        $brand = CompanyBrand::findOrFail($companyBrandId);
        if ($brand->source !== CompanyBrand::SOURCE_PRIVATE) {
            return back()->withErrors(['brand' => 'Only private brands can be deleted. Deactivate global brands instead.']);
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
        return redirect()->route('catalogue.brands')->with('status', 'Private brand removed.');
    }

    /* -------------------------------------------------- MODELS / VARIANTS */

    /**
     * Models & variants page. Two tabs:
     *   - "Available" → global catalogue (read-only, with checkboxes to add
     *                   variants to the workspace from brands not yet activated)
     *   - "My workspace" → company tier (editable prices + per-variant toggles)
     */
    public function models(Request $request)
    {
        $tab = $request->query('tab', 'workspace'); // workspace | available

        // Shared brand list for the filter dropdown (global brands only).
        $globalBrands = GlobalBrand::where('is_active', true)->orderBy('name')->get();
        $brandFilter  = $request->query('brand');

        // Workspace count is shown on both tabs' "My workspace (N)" pill, so
        // compute it once and pass through.
        $workspaceCount = $this->countWorkspaceVariants();

        if ($tab === 'available') {
            return $this->renderAvailableTab($globalBrands, $brandFilter, $workspaceCount);
        }

        return $this->renderWorkspaceTab($globalBrands, $brandFilter, $workspaceCount);
    }

    /**
     * Number of active, reachable variants in the dealer's workspace —
     * variants that are visible in their quote builder.
     */
    private function countWorkspaceVariants(): int
    {
        $activeBrandIds = CompanyBrand::where('is_active', true)
            ->get(['_id'])->pluck('_id')->map(fn ($i) => (string) $i)->all();

        $activeModelIds = CompanyBoatModel::whereIn('company_brand_id', $activeBrandIds)
            ->where('is_archived', false)
            ->get(['_id'])->pluck('_id')->map(fn ($i) => (string) $i)->all();

        return CompanyBoatVariant::whereIn('company_model_id', $activeModelIds)
            ->where('is_active', true)
            ->where('is_archived', false)
            ->count();
    }

    private function renderWorkspaceTab($globalBrands, ?string $brandFilter, int $workspaceCount)
    {
        // Brands the dealer has activated (regardless of is_active so we
        // can show deactivated rows greyed-out, but not in their builder).
        $companyBrands = CompanyBrand::orderBy('name')->get();

        $companyBrandsActive = $companyBrands->where('is_active', true);

        $brandIds = $companyBrandsActive
            ->when($brandFilter, fn ($c) => $c->where('_id', $brandFilter))
            ->pluck('_id')->map(fn ($i) => (string) $i)->all();

        $models = CompanyBoatModel::whereIn('company_brand_id', $brandIds)
            ->where('is_archived', false)
            ->orderBy('name')
            ->get();

        $modelIds = $models->pluck('_id')->map(fn ($i) => (string) $i)->all();
        $variants = CompanyBoatVariant::whereIn('company_model_id', $modelIds)
            ->where('is_archived', false)
            ->orderBy('name')
            ->get();

        $brandsById = $companyBrandsActive->keyBy(fn ($b) => (string) $b->_id);
        $modelsById = $models->keyBy(fn ($m) => (string) $m->_id);

        // Flat rows: one row per variant (table view).
        $rows = $variants->map(function ($v) use ($brandsById, $modelsById) {
            $model = $modelsById[(string) $v->company_model_id] ?? null;
            $brand = $model ? ($brandsById[(string) $model->company_brand_id] ?? null) : null;
            return [
                'variant' => $v,
                'model'   => $model,
                'brand'   => $brand,
            ];
        });

        return view('catalogue.models', [
            'tab'            => 'workspace',
            'rows'           => $rows,
            'brands'         => $companyBrandsActive->values(),
            'globalBrands'   => $globalBrands,
            'brandFilter'    => $brandFilter,
            'totalModels'    => $models->count(),
            'workspaceCount' => $workspaceCount,
        ]);
    }

    private function renderAvailableTab($globalBrands, ?string $brandFilter, int $workspaceCount)
    {
        $models = GlobalBoatModel::where('is_archived', false)
            ->when($brandFilter, fn ($q) => $q->where('brand_id', $brandFilter))
            ->orderBy('name')
            ->get();

        $modelIds = $models->pluck('_id')->map(fn ($i) => (string) $i)->all();
        $variants = GlobalBoatVariant::whereIn('model_id', $modelIds)
            ->where('is_archived', false)
            ->orderBy('name')
            ->get();

        $brandsById = $globalBrands->keyBy(fn ($b) => (string) $b->_id);
        $modelsById = $models->keyBy(fn ($m) => (string) $m->_id);

        // Which global variants are already activated AND still reachable in
        // the dealer's workspace? A variant counts as activated only when:
        //   - its own is_active=true and is_archived=false
        //   - its parent company brand is also is_active=true
        // Deactivating a brand on the Brands page should re-expose its
        // variants in Available so they can be pulled back in.
        // NB: Laravel-Mongodb's query-builder ->pluck('_id') returns NULLs;
        // hydrate the models first, then pluck on the collection.
        $activeBrandIds = CompanyBrand::where('is_active', true)
            ->get(['_id'])->pluck('_id')->map(fn ($i) => (string) $i)->all();

        $activeModelIds = CompanyBoatModel::whereIn('company_brand_id', $activeBrandIds)
            ->where('is_archived', false)
            ->get(['_id'])->pluck('_id')->map(fn ($i) => (string) $i)->all();

        $activatedVariantIds = CompanyBoatVariant::whereNotNull('global_variant_id')
            ->whereIn('company_model_id', $activeModelIds)
            ->where('is_active', true)
            ->where('is_archived', false)
            ->pluck('global_variant_id')
            ->all();

        $rows = $variants->map(function ($v) use ($brandsById, $modelsById) {
            $model = $modelsById[(string) $v->model_id] ?? null;
            $brand = $model ? ($brandsById[(string) $model->brand_id] ?? null) : null;
            return [
                'variant' => $v,
                'model'   => $model,
                'brand'   => $brand,
            ];
        });

        return view('catalogue.models', [
            'tab'                 => 'available',
            'rows'                => $rows,
            'brands'              => $globalBrands,
            'globalBrands'        => $globalBrands,
            'brandFilter'         => $brandFilter,
            'totalModels'         => $models->count(),
            'activatedVariantIds' => $activatedVariantIds,
            'workspaceCount'      => $workspaceCount,
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

        return back()->with('status', "Added «{$globalVariant->name}» to your workspace.");
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
            return back()->withErrors(['variants' => 'Pick at least one variant first.']);
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
            return back()->with('status', "{$added} variant(s) added to your workspace.");
        }

        if ($added === 0 && ! empty($skipped)) {
            return back()->withErrors(['variants' => $this->formatSkippedMessage($skipped)]);
        }

        // Mixed result — partial success.
        return back()
            ->with('status', "{$added} variant(s) added.")
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

    public function createModel(Request $request)
    {
        $brands = CompanyBrand::where('is_active', true)->orderBy('name')->get();
        return view('catalogue.model-form', [
            'brands'      => $brands,
            'model'       => null,
            'preselected' => $request->query('brand'),
        ]);
    }

    public function storeModel(Request $request)
    {
        $data = $request->validate([
            'company_brand_id'    => 'required',
            'code'                => 'required|string|max:60',
            'name'                => 'required|string|max:200',
            'default_margin_pct'  => 'nullable|numeric|min:0|max:100',
        ]);

        // Ensure the brand belongs to the current tenant
        CompanyBrand::findOrFail($data['company_brand_id']);

        $model = CompanyBoatModel::create([
            'company_id'         => auth()->user()->company_id,
            'company_brand_id'   => $data['company_brand_id'],
            'global_model_id'    => null,
            'source'             => CompanyBoatModel::SOURCE_PRIVATE,
            'code'               => $data['code'],
            'name'               => $data['name'],
            'default_margin_pct' => $data['default_margin_pct'] ?? null,
            'is_archived'        => false,
        ]);

        return redirect()->route('catalogue.models.edit', $model->_id)
            ->with('status', "Model «{$model->name}» created — now add variants and options.");
    }

    public function editModel(string $modelId)
    {
        $model    = CompanyBoatModel::findOrFail($modelId);
        $brands   = CompanyBrand::where('is_active', true)->orderBy('name')->get();
        $variants = CompanyBoatVariant::where('company_model_id', $modelId)->where('is_archived', false)->get();
        $options  = CompanyOption::where('company_model_id', $modelId)->where('is_archived', false)->orderBy('position')->get();

        return view('catalogue.model-edit', compact('model', 'brands', 'variants', 'options'));
    }

    public function updateModel(string $modelId, Request $request)
    {
        $model = CompanyBoatModel::findOrFail($modelId);
        $data = $request->validate([
            'code'                => 'required|string|max:60',
            'name'                => 'required|string|max:200',
            'default_margin_pct'  => 'nullable|numeric|min:0|max:100',
        ]);
        $model->update($data);
        return back()->with('status', 'Model updated.');
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
        return redirect()->route('catalogue.models')->with('status', 'Model deleted.');
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
        CompanyBrand::findOrFail($data['company_brand_id']);
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
            ->with('status', "Variant «{$variant->name}» added to your workspace.");
    }

    public function storeVariant(string $modelId, Request $request)
    {
        $model = CompanyBoatModel::findOrFail($modelId);
        $data = $request->validate([
            'name'        => 'required|string|max:200',
            'base_price'  => 'required|numeric|min:0',
            'cost'        => 'nullable|numeric|min:0',
            'currency'    => 'nullable|in:EUR,USD',
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
            'included_equipment' => [],
            'is_active'          => true,
            'is_archived'        => false,
        ]);
        return back()->with('status', 'Variant added.');
    }

    public function updateVariant(string $variantId, Request $request)
    {
        $variant = CompanyBoatVariant::findOrFail($variantId);
        $data = $request->validate([
            'name'        => 'required|string|max:200',
            'base_price'  => 'required|numeric|min:0',
            'cost'        => 'nullable|numeric|min:0',
            'currency'    => 'nullable|in:EUR,USD',
        ]);
        $variant->update([
            'name'       => $data['name'],
            'base_price' => (float) $data['base_price'],
            'cost'       => (float) ($data['cost'] ?? $variant->cost),
            'currency'   => $data['currency'] ?? $variant->currency,
        ]);
        return back()->with('status', "Variant «{$variant->name}» updated.");
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
        return back()->with('status', 'Variant removed.');
    }

    /* ---------------------------------------------- OPTION EDITS */

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
        return back()->with('status', 'Option added.');
    }

    public function updateOption(string $optionId, Request $request)
    {
        $option = CompanyOption::findOrFail($optionId);
        $data = $request->validate([
            'category'  => 'required|string|max:100',
            'label'     => 'required|string|max:200',
            'price'     => 'required|numeric|min:0',
            'cost'      => 'nullable|numeric|min:0',
            'currency'  => 'nullable|in:EUR,USD',
            'position'  => 'nullable|integer',
        ]);
        $option->update([
            'category' => $data['category'],
            'label'    => $data['label'],
            'price'    => (float) $data['price'],
            'cost'     => (float) ($data['cost'] ?? $option->cost),
            'currency' => $data['currency'] ?? $option->currency,
            'position' => (int) ($data['position'] ?? $option->position),
        ]);
        return back()->with('status', 'Option updated.');
    }

    public function destroyOption(string $optionId)
    {
        $option = CompanyOption::findOrFail($optionId);
        if ($option->source === 'global') {
            $option->update(['is_archived' => true]);
        } else {
            $option->delete();
        }
        return back()->with('status', 'Option removed.');
    }

    /* ---------------------------------------------- UPDATES TAB */

    public function updates()
    {
        // Phase 2 — actual notification list. For now empty state but with
        // the new wiring already pointing here.
        return view('catalogue.updates');
    }
}
