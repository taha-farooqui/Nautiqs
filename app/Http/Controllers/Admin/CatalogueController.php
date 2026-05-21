<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GlobalBoatModel;
use App\Models\GlobalBoatVariant;
use App\Models\GlobalBrand;
use App\Models\GlobalEngine;
use App\Models\GlobalEquipment;
use App\Models\GlobalOption;
use App\Services\AuditLogger;
use App\Services\GlobalEngineImporter;
use Illuminate\Http\Request;

/**
 * Spec §4.1 — global-catalogue CRUD for the platform owner. Five entities
 * grouped into one controller so the routes/views share helpers and the
 * audit-log call sites stay consistent.
 *
 * Hierarchy: Brand → Model → Variant. Options also hang off Model.
 * Equipment is a flat library shared by all variants.
 */
class CatalogueController extends Controller
{
    /* ============================================================ BRANDS */

    public function brandsIndex(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $query = GlobalBrand::orderBy('display_order')->orderBy('name');
        if ($q !== '') {
            $query->whereRaw(['name' => ['$regex' => preg_quote($q, '/'), '$options' => 'i']]);
        }
        $brands = $query->paginate(40)->withQueryString();

        // Cheap aggregate: model count per brand.
        $brandIds = collect($brands->items())->pluck('_id')->map(fn ($i) => (string) $i);
        $modelCounts = GlobalBoatModel::whereIn('brand_id', $brandIds->all())
            ->where('is_archived', '!=', true)
            ->get(['brand_id'])
            ->groupBy(fn ($m) => (string) $m->brand_id)
            ->map->count();
        foreach ($brands as $b) {
            $b->_models_count = $modelCounts->get((string) $b->_id, 0);
        }

        return view('admin.catalogue.brands.index', compact('brands', 'q'));
    }

    public function brandsCreate()
    {
        return view('admin.catalogue.brands.form', ['brand' => new GlobalBrand()]);
    }

    public function brandsStore(Request $request)
    {
        $data = $this->validateBrand($request);
        $brand = GlobalBrand::create($data + ['is_active' => true]);
        AuditLogger::record('brand.create', target: $brand, after: $data);
        return redirect()->route('admin.brands.index')->with('status', __('Brand created.'));
    }

    public function brandsEdit(string $id)
    {
        $brand = GlobalBrand::where('_id', $id)->firstOrFail();
        return view('admin.catalogue.brands.form', compact('brand'));
    }

    public function brandsUpdate(Request $request, string $id)
    {
        $brand = GlobalBrand::where('_id', $id)->firstOrFail();
        $data  = $this->validateBrand($request);
        $before = $brand->only(array_keys($data));
        $brand->update($data);
        AuditLogger::record('brand.update', target: $brand, before: $before, after: $data);
        return redirect()->route('admin.brands.index')->with('status', __('Brand updated.'));
    }

    public function brandsToggle(string $id)
    {
        $brand = GlobalBrand::where('_id', $id)->firstOrFail();
        $before = ['is_active' => $brand->is_active];
        $brand->update(['is_active' => ! $brand->is_active]);
        AuditLogger::record($brand->is_active ? 'brand.activate' : 'brand.deactivate',
            target: $brand, before: $before, after: ['is_active' => $brand->is_active]);
        return back()->with('status', $brand->is_active ? __('Brand activated.') : __('Brand deactivated.'));
    }

    private function validateBrand(Request $request): array
    {
        return $request->validate([
            'name'        => 'required|string|max:120',
            'description' => 'nullable|string|max:2000',
        ]);
    }

    /* ============================================================ MODELS */

    public function modelsIndex(Request $request)
    {
        $q       = trim((string) $request->query('q', ''));
        $brandId = $request->query('brand', '');
        $status  = $request->query('status', 'active'); // active | archived

        $query = GlobalBoatModel::orderBy('name');
        if ($status === 'archived') {
            $query->where('is_archived', true);
        } else {
            $query->where('is_archived', '!=', true);
        }
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $regex = ['$regex' => preg_quote($q, '/'), '$options' => 'i'];
                $w->whereRaw(['name' => $regex])->orWhereRaw(['code' => $regex]);
            });
        }
        if ($brandId !== '') {
            $query->where('brand_id', $brandId);
        }
        $models = $query->paginate(40)->withQueryString();

        $brandIds = collect($models->items())->pluck('brand_id')->unique()->filter()->values();
        $brands   = GlobalBrand::whereIn('_id', $brandIds->all())->get(['name'])->keyBy('_id');
        $modelIds = collect($models->items())->pluck('_id')->map(fn ($i) => (string) $i);
        $variantCounts = GlobalBoatVariant::whereIn('model_id', $modelIds->all())
            ->where('is_archived', '!=', true)
            ->get(['model_id'])->groupBy(fn ($v) => (string) $v->model_id)->map->count();
        $optionCounts = GlobalOption::whereIn('model_id', $modelIds->all())
            ->where('is_archived', '!=', true)
            ->get(['model_id'])->groupBy(fn ($o) => (string) $o->model_id)->map->count();
        foreach ($models as $m) {
            $m->_brand_name = $brands->get((string) $m->brand_id)?->name ?? '—';
            $m->_variants_count = $variantCounts->get((string) $m->_id, 0);
            $m->_options_count  = $optionCounts->get((string) $m->_id, 0);
        }

        $allBrands = GlobalBrand::where('is_active', true)->orderBy('name')->get(['name']);

        $tabCounts = [
            'active'   => GlobalBoatModel::where('is_archived', '!=', true)->count(),
            'archived' => GlobalBoatModel::where('is_archived', true)->count(),
        ];

        return view('admin.catalogue.models.index', compact('models', 'q', 'brandId', 'status', 'allBrands', 'tabCounts'));
    }

    public function modelsCreate()
    {
        return view('admin.catalogue.models.form', [
            'model'  => new GlobalBoatModel(),
            'brands' => GlobalBrand::where('is_active', true)->orderBy('name')->get(['name']),
        ]);
    }

    public function modelsStore(Request $request)
    {
        $data  = $this->validateModel($request);
        $model = GlobalBoatModel::create($data + ['is_archived' => false]);
        AuditLogger::record('model.create', target: $model, after: $data);
        return redirect()->route('admin.models.index')->with('status', __('Model created.'));
    }

    public function modelsEdit(string $id)
    {
        $model = GlobalBoatModel::where('_id', $id)->firstOrFail();
        return view('admin.catalogue.models.form', [
            'model'  => $model,
            'brands' => GlobalBrand::where('is_active', true)->orderBy('name')->get(['name']),
        ]);
    }

    public function modelsUpdate(Request $request, string $id)
    {
        $model  = GlobalBoatModel::where('_id', $id)->firstOrFail();
        $data   = $this->validateModel($request);
        $before = $model->only(array_keys($data));
        $model->update($data);
        AuditLogger::record('model.update', target: $model, before: $before, after: $data);
        return redirect()->route('admin.models.index')->with('status', __('Model updated.'));
    }

    public function modelsArchive(string $id)
    {
        $model  = GlobalBoatModel::where('_id', $id)->firstOrFail();
        $before = ['is_archived' => $model->is_archived];
        $model->update(['is_archived' => ! $model->is_archived]);
        AuditLogger::record($model->is_archived ? 'model.archive' : 'model.unarchive',
            target: $model, before: $before, after: ['is_archived' => $model->is_archived]);
        return back()->with('status', $model->is_archived ? __('Model archived.') : __('Model restored.'));
    }

    private function validateModel(Request $request): array
    {
        return $request->validate([
            'brand_id'           => 'required|string',
            'code'               => 'required|string|max:60',
            'name'               => 'required|string|max:160',
            'default_margin_pct' => 'nullable|numeric|min:0|max:100',
        ]);
    }

    /* ========================================================== VARIANTS */

    public function variantsIndex(Request $request)
    {
        $modelId = $request->query('model', '');
        $status  = $request->query('status', 'active');
        $query = GlobalBoatVariant::orderBy('name');
        if ($status === 'archived') {
            $query->where('is_archived', true);
        } else {
            $query->where('is_archived', '!=', true);
        }
        if ($modelId !== '') {
            $query->where('model_id', $modelId);
        }
        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $query->whereRaw(['name' => ['$regex' => preg_quote($q, '/'), '$options' => 'i']]);
        }
        $variants = $query->paginate(40)->withQueryString();

        $modelIds = collect($variants->items())->pluck('model_id')->unique()->filter()->values();
        $models   = GlobalBoatModel::whereIn('_id', $modelIds->all())->get(['name', 'code'])->keyBy('_id');
        foreach ($variants as $v) {
            $m = $models->get((string) $v->model_id);
            $v->_model_name = $m?->name ?? '—';
            $v->_model_code = $m?->code ?? '';
        }

        $allModels = GlobalBoatModel::where('is_archived', '!=', true)
            ->orderBy('name')->get(['name', 'code'])->take(500);

        $tabCounts = [
            'active'   => GlobalBoatVariant::where('is_archived', '!=', true)->count(),
            'archived' => GlobalBoatVariant::where('is_archived', true)->count(),
        ];

        return view('admin.catalogue.variants.index', compact('variants', 'q', 'modelId', 'status', 'allModels', 'tabCounts'));
    }

    public function variantsCreate(Request $request)
    {
        $variant = new GlobalBoatVariant(['currency' => 'EUR']);
        if ($preselect = $request->query('model')) {
            $variant->model_id = $preselect;
        }
        return view('admin.catalogue.variants.form', [
            'variant' => $variant,
            'models'  => GlobalBoatModel::where('is_archived', '!=', true)->orderBy('name')->get(['name', 'code']),
        ]);
    }

    public function variantsStore(Request $request)
    {
        $data    = $this->validateVariant($request);
        $variant = GlobalBoatVariant::create($data + ['is_archived' => false]);
        AuditLogger::record('variant.create', target: $variant, after: $data);
        return redirect()->route('admin.variants.index')->with('status', __('Variant created.'));
    }

    public function variantsEdit(string $id)
    {
        $variant = GlobalBoatVariant::where('_id', $id)->firstOrFail();
        return view('admin.catalogue.variants.form', [
            'variant' => $variant,
            'models'  => GlobalBoatModel::where('is_archived', '!=', true)->orderBy('name')->get(['name', 'code']),
        ]);
    }

    public function variantsUpdate(Request $request, string $id)
    {
        $variant = GlobalBoatVariant::where('_id', $id)->firstOrFail();
        $data    = $this->validateVariant($request);
        $before  = $variant->only(array_keys($data));
        $variant->update($data);
        AuditLogger::record('variant.update', target: $variant, before: $before, after: $data);
        return redirect()->route('admin.variants.index')->with('status', __('Variant updated.'));
    }

    public function variantsArchive(string $id)
    {
        $variant = GlobalBoatVariant::where('_id', $id)->firstOrFail();
        $before  = ['is_archived' => $variant->is_archived];
        $variant->update(['is_archived' => ! $variant->is_archived]);
        AuditLogger::record($variant->is_archived ? 'variant.archive' : 'variant.unarchive',
            target: $variant, before: $before, after: ['is_archived' => $variant->is_archived]);
        return back()->with('status', $variant->is_archived ? __('Variant archived.') : __('Variant restored.'));
    }

    private function validateVariant(Request $request): array
    {
        return $request->validate([
            'model_id'   => 'required|string',
            'name'       => 'required|string|max:160',
            'base_price' => 'required|numeric|min:0|max:10000000',
            'cost'       => 'nullable|numeric|min:0|max:10000000',
            'currency'   => 'required|in:EUR,USD',
        ]);
    }

    /* ========================================================= EQUIPMENT */

    public function equipmentIndex(Request $request)
    {
        $q        = trim((string) $request->query('q', ''));
        $category = $request->query('category', '');

        $query = GlobalEquipment::orderBy('category')->orderBy('label');
        if ($q !== '') {
            $query->whereRaw(['label' => ['$regex' => preg_quote($q, '/'), '$options' => 'i']]);
        }
        if ($category !== '') {
            $query->where('category', $category);
        }
        $items = $query->paginate(50)->withQueryString();

        return view('admin.catalogue.equipment.index', [
            'items'      => $items,
            'q'          => $q,
            'category'   => $category,
            'categories' => GlobalEquipment::CATEGORIES,
        ]);
    }

    public function equipmentCreate()
    {
        return view('admin.catalogue.equipment.form', [
            'item'       => new GlobalEquipment(),
            'categories' => GlobalEquipment::CATEGORIES,
        ]);
    }

    public function equipmentStore(Request $request)
    {
        $data = $this->validateEquipment($request);
        $item = GlobalEquipment::create($data + ['is_active' => true]);
        AuditLogger::record('equipment.create', target: $item, after: $data);
        return redirect()->route('admin.equipment.index')->with('status', __('Equipment added.'));
    }

    public function equipmentEdit(string $id)
    {
        $item = GlobalEquipment::where('_id', $id)->firstOrFail();
        return view('admin.catalogue.equipment.form', [
            'item'       => $item,
            'categories' => GlobalEquipment::CATEGORIES,
        ]);
    }

    public function equipmentUpdate(Request $request, string $id)
    {
        $item   = GlobalEquipment::where('_id', $id)->firstOrFail();
        $data   = $this->validateEquipment($request);
        $before = $item->only(array_keys($data));
        $item->update($data);
        AuditLogger::record('equipment.update', target: $item, before: $before, after: $data);
        return redirect()->route('admin.equipment.index')->with('status', __('Equipment updated.'));
    }

    public function equipmentDestroy(string $id)
    {
        $item = GlobalEquipment::where('_id', $id)->firstOrFail();
        AuditLogger::record('equipment.delete', target: $item, before: $item->toArray());
        $item->delete();
        return back()->with('status', __('Equipment deleted.'));
    }

    private function validateEquipment(Request $request): array
    {
        return $request->validate([
            'category' => 'required|string|in:' . implode(',', array_keys(GlobalEquipment::CATEGORIES)),
            'label'    => 'required|string|max:255',
        ]);
    }

    /* =========================================================== OPTIONS */

    public function optionsIndex(Request $request)
    {
        $modelId  = $request->query('model', '');
        $category = trim((string) $request->query('category', ''));
        $q        = trim((string) $request->query('q', ''));
        $status   = $request->query('status', 'active');

        $query = GlobalOption::orderBy('category')->orderBy('position')->orderBy('label');
        if ($status === 'archived') {
            $query->where('is_archived', true);
        } else {
            $query->where('is_archived', '!=', true);
        }
        if ($modelId !== '') $query->where('model_id', $modelId);
        if ($category !== '') $query->where('category', $category);
        if ($q !== '') {
            $query->whereRaw(['label' => ['$regex' => preg_quote($q, '/'), '$options' => 'i']]);
        }
        $options = $query->paginate(50)->withQueryString();

        $modelIds = collect($options->items())->pluck('model_id')->unique()->filter()->values();
        $models   = GlobalBoatModel::whereIn('_id', $modelIds->all())->get(['name'])->keyBy('_id');
        foreach ($options as $o) {
            $o->_model_name = $models->get((string) $o->model_id)?->name ?? '—';
        }

        $allModels = GlobalBoatModel::where('is_archived', '!=', true)->orderBy('name')->get(['name'])->take(500);
        $allCategories = GlobalOption::distinct()->get(['category'])->pluck('category')->filter()->sort()->values();

        $tabCounts = [
            'active'   => GlobalOption::where('is_archived', '!=', true)->count(),
            'archived' => GlobalOption::where('is_archived', true)->count(),
        ];

        return view('admin.catalogue.options.index', compact('options', 'q', 'modelId', 'category', 'status', 'allModels', 'allCategories', 'tabCounts'));
    }

    public function optionsCreate(Request $request)
    {
        $option = new GlobalOption(['currency' => 'EUR', 'position' => 0]);
        if ($preselect = $request->query('model')) {
            $option->model_id = $preselect;
        }
        return view('admin.catalogue.options.form', [
            'option' => $option,
            'models' => GlobalBoatModel::where('is_archived', '!=', true)->orderBy('name')->get(['name']),
        ]);
    }

    public function optionsStore(Request $request)
    {
        $data   = $this->validateOption($request);
        $option = GlobalOption::create($data + ['is_archived' => false]);
        AuditLogger::record('option.create', target: $option, after: $data);
        return redirect()->route('admin.options.index')->with('status', __('Option created.'));
    }

    public function optionsEdit(string $id)
    {
        $option = GlobalOption::where('_id', $id)->firstOrFail();
        return view('admin.catalogue.options.form', [
            'option' => $option,
            'models' => GlobalBoatModel::where('is_archived', '!=', true)->orderBy('name')->get(['name']),
        ]);
    }

    public function optionsUpdate(Request $request, string $id)
    {
        $option = GlobalOption::where('_id', $id)->firstOrFail();
        $data   = $this->validateOption($request);
        $before = $option->only(array_keys($data));
        $option->update($data);
        AuditLogger::record('option.update', target: $option, before: $before, after: $data);
        return redirect()->route('admin.options.index')->with('status', __('Option updated.'));
    }

    public function optionsArchive(string $id)
    {
        $option = GlobalOption::where('_id', $id)->firstOrFail();
        $before = ['is_archived' => $option->is_archived];
        $option->update(['is_archived' => ! $option->is_archived]);
        AuditLogger::record($option->is_archived ? 'option.archive' : 'option.unarchive',
            target: $option, before: $before, after: ['is_archived' => $option->is_archived]);
        return back()->with('status', $option->is_archived ? __('Option archived.') : __('Option restored.'));
    }

    private function validateOption(Request $request): array
    {
        return $request->validate([
            'model_id' => 'required|string',
            'category' => 'required|string|max:80',
            'label'    => 'required|string|max:255',
            'price'    => 'required|numeric|min:0|max:1000000',
            'cost'     => 'nullable|numeric|min:0|max:1000000',
            'currency' => 'required|in:EUR,USD',
            'position' => 'nullable|integer|min:0|max:9999',
        ]);
    }

    /* =========================================================== ENGINES */

    public function enginesIndex(Request $request)
    {
        $q      = trim((string) $request->query('q', ''));
        $status = $request->query('status', 'active');

        $query = GlobalEngine::orderBy('brand')->orderBy('code');
        if ($status === 'archived') {
            $query->where('is_archived', true);
        } else {
            $query->where('is_archived', '!=', true);
        }
        if ($q !== '') {
            $regex = ['$regex' => preg_quote($q, '/'), '$options' => 'i'];
            $query->where(function ($w) use ($regex) {
                $w->whereRaw(['brand' => $regex])
                  ->orWhereRaw(['code' => $regex])
                  ->orWhereRaw(['description' => $regex]);
            });
        }
        $engines = $query->paginate(40)->withQueryString();

        $tabCounts = [
            'active'   => GlobalEngine::where('is_archived', '!=', true)->count(),
            'archived' => GlobalEngine::where('is_archived', true)->count(),
        ];

        return view('admin.catalogue.engines.index', compact('engines', 'q', 'status', 'tabCounts'));
    }

    public function enginesCreate()
    {
        return view('admin.catalogue.engines.form', ['engine' => new GlobalEngine(['currency' => 'EUR', 'vat_rate' => 20])]);
    }

    public function enginesStore(Request $request)
    {
        $data   = $this->validateEngine($request);
        $engine = GlobalEngine::create($data + ['is_active' => true, 'is_archived' => false]);
        AuditLogger::record('engine.create', target: $engine, after: $data);
        return redirect()->route('admin.engines.index')->with('status', __('Engine created.'));
    }

    public function enginesEdit(string $id)
    {
        $engine = GlobalEngine::where('_id', $id)->firstOrFail();
        return view('admin.catalogue.engines.form', compact('engine'));
    }

    public function enginesUpdate(Request $request, string $id)
    {
        $engine = GlobalEngine::where('_id', $id)->firstOrFail();
        $data   = $this->validateEngine($request);
        $before = $engine->only(array_keys($data));
        $engine->update($data);
        AuditLogger::record('engine.update', target: $engine, before: $before, after: $data);
        return redirect()->route('admin.engines.index')->with('status', __('Engine updated.'));
    }

    public function enginesArchive(string $id)
    {
        $engine = GlobalEngine::where('_id', $id)->firstOrFail();
        $before = ['is_archived' => $engine->is_archived];
        $engine->update(['is_archived' => ! $engine->is_archived]);
        AuditLogger::record($engine->is_archived ? 'engine.archive' : 'engine.unarchive',
            target: $engine, before: $before, after: ['is_archived' => $engine->is_archived]);
        return back()->with('status', $engine->is_archived ? __('Engine archived.') : __('Engine restored.'));
    }

    private function validateEngine(Request $request): array
    {
        return $request->validate([
            'brand'      => 'required|string|max:80',
            'code'       => 'required|string|max:120',
            'horsepower' => 'nullable|numeric|min:0|max:5000',
            'fuel'       => 'nullable|in:petrol,diesel,electric,unknown',
            'description'=> 'nullable|string|max:1000',
            'cost'       => 'nullable|numeric|min:0|max:1000000',
            'price'      => 'required|numeric|min:0|max:1000000',
            'vat_rate'   => 'nullable|numeric|min:0|max:100',
            'currency'   => 'required|in:EUR,USD',
        ]);
    }

    public function enginesTemplate()
    {
        $filename = 'nautiqs-global-engines-template.csv';
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xef\xbb\xbf");
            fputcsv($out, ['Brand', 'Model', 'PA HT', 'PV HT', 'TVA']);
            fputcsv($out, ['Suzuki',  'DF200A TL/TX', 14800, 18500, 20]);
            fputcsv($out, ['Yamaha',  'F300 NCA',     23100, 28900, 20]);
            fputcsv($out, ['Mercury', 'Verado 350 XL', 27800, 34750, 20]);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function enginesImport(Request $request, GlobalEngineImporter $importer)
    {
        $request->validate([
            'file' => 'required|file|max:10240|mimes:csv,txt,xlsx,xlsm',
        ], [], ['file' => __('File')]);

        $result = $importer->import($request->file('file'));

        AuditLogger::record('engine.bulk-import', targetLabel: 'Global engines bulk import',
            after: ['created' => $result['created'], 'updated' => $result['updated'], 'errors' => count($result['errors'])]);

        $msg = __(':created created, :updated updated, :skipped skipped.', [
            'created' => $result['created'],
            'updated' => $result['updated'],
            'skipped' => $result['skipped'],
        ]);
        return redirect()->route('admin.engines.index')
            ->with('status', $msg)
            ->with('import_result', $result);
    }
}
