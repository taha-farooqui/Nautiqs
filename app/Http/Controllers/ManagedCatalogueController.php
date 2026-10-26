<?php

namespace App\Http\Controllers;

use App\Models\BrandAccess;
use App\Models\BrandAccount;
use App\Models\BrandEngine;
use App\Models\BrandEngineAccessory;
use App\Models\BrandOption;
use App\Models\BrandVariant;
use App\Models\CompanyBoatModel;
use App\Models\CompanyBoatVariant;
use App\Models\CompanyBrand;
use App\Models\CompanyOption;
use App\Models\Engine;
use App\Models\EngineAccessory;
use App\Models\ManagedBrand;
use App\Services\Brand\BrandPricing;
use Illuminate\Http\Request;

/**
 * The dealer's view of items a partner brand manages. Everything is
 * read-only except the dealer's own special price (cost_override): an exact
 * purchase price that the brand's updates no longer overwrite. Clearing it
 * goes back to the price of the dealer's level.
 */
class ManagedCatalogueController extends Controller
{
    /* ---------------------------------------------------------------- boats */

    public function boat(string $modelId)
    {
        $model = CompanyBoatModel::where('_id', $modelId)->whereNotNull('brand_model_id')->firstOrFail();
        $ctx = $this->context($model->brand?->brand_account_id, $model->brand?->managed_brand_id);

        $versions = CompanyBoatVariant::where('company_model_id', (string) $model->_id)->where('is_archived', '!=', true)->get()
            ->map(fn ($v) => (object) ['id' => (string) $v->_id, 'name' => $v->name, 'category' => null, 'public' => (float) $v->base_price, 'cost' => $v->cost,
                'override' => (bool) $v->cost_override, 'level' => $this->levelPrice($ctx, BrandVariant::class, $v->brand_variant_id, 'versions')]);
        $options = CompanyOption::where('company_model_id', (string) $model->_id)->where('is_archived', '!=', true)->orderBy('position')->get()
            ->map(fn ($o) => (object) ['id' => (string) $o->_id, 'name' => $o->label, 'category' => $o->category, 'public' => (float) $o->price, 'cost' => $o->cost,
                'override' => (bool) $o->cost_override, 'level' => $this->levelPrice($ctx, BrandOption::class, $o->brand_option_id, 'options')]);

        return view('catalogue.managed', [
            'title' => trim(($model->brand?->name ?? '') . ' ' . $model->name), 'model' => $model,
            'account' => $ctx['account'], 'levelName' => $ctx['levelName'],
            'groups' => [__('Versions') => $versions, __('Options') => $options],
            'action' => route('catalogue.managed.boat.save', $model->_id), 'back' => route('catalogue.models'),
        ]);
    }

    public function saveBoat(string $modelId, Request $request)
    {
        $model = CompanyBoatModel::where('_id', $modelId)->whereNotNull('brand_model_id')->firstOrFail();
        $ctx = $this->context($model->brand?->brand_account_id, $model->brand?->managed_brand_id);
        $posted = (array) $request->input('special', []);

        foreach (CompanyBoatVariant::where('company_model_id', (string) $model->_id)->whereNotNull('brand_variant_id')->get() as $v) {
            if (array_key_exists((string) $v->_id, $posted)) {
                $this->setSpecial($v, $posted[(string) $v->_id], fn () => $this->levelPrice($ctx, BrandVariant::class, $v->brand_variant_id, 'versions'));
            }
        }
        foreach (CompanyOption::where('company_model_id', (string) $model->_id)->whereNotNull('brand_option_id')->get() as $o) {
            if (array_key_exists((string) $o->_id, $posted)) {
                $this->setSpecial($o, $posted[(string) $o->_id], fn () => $this->levelPrice($ctx, BrandOption::class, $o->brand_option_id, 'options'));
            }
        }

        return back()->with('status', __('Special prices saved.'));
    }

    /* --------------------------------------------------- engines and kits */

    public function engine(string $id)
    {
        $e = Engine::where('_id', $id)->whereNotNull('brand_engine_id')->firstOrFail();
        $ctx = $this->context($e->brand_account_id, $e->managed_brand_id);

        return view('catalogue.managed', [
            'title' => trim($e->brand . ' ' . $e->code), 'model' => null,
            'account' => $ctx['account'], 'levelName' => $ctx['levelName'],
            'groups' => [__('Engine') => collect([(object) ['id' => (string) $e->_id, 'name' => trim($e->brand . ' ' . $e->code), 'category' => null,
                'public' => (float) $e->price, 'cost' => $e->cost, 'override' => (bool) $e->cost_override,
                'level' => $this->levelPrice($ctx, BrandEngine::class, $e->brand_engine_id, 'engines')]])],
            'action' => route('engines.managed.save', $e->_id), 'back' => route('engines.index'),
        ]);
    }

    public function saveEngine(string $id, Request $request)
    {
        $e = Engine::where('_id', $id)->whereNotNull('brand_engine_id')->firstOrFail();
        $ctx = $this->context($e->brand_account_id, $e->managed_brand_id);
        $this->setSpecial($e, $request->input('special.' . $id), fn () => $this->levelPrice($ctx, BrandEngine::class, $e->brand_engine_id, 'engines'));

        return back()->with('status', __('Special price saved.'));
    }

    public function kit(string $id)
    {
        $a = EngineAccessory::where('_id', $id)->whereNotNull('brand_accessory_id')->firstOrFail();
        $ctx = $this->context($a->brand_account_id, $a->managed_brand_id);

        return view('catalogue.managed', [
            'title' => $a->label, 'model' => null,
            'account' => $ctx['account'], 'levelName' => $ctx['levelName'],
            'groups' => [($a->type === 'propeller' ? __('Propeller') : __('Kit')) => collect([(object) ['id' => (string) $a->_id, 'name' => $a->label, 'category' => null,
                'public' => (float) $a->price, 'cost' => $a->cost, 'override' => (bool) $a->cost_override,
                'level' => $this->levelPrice($ctx, BrandEngineAccessory::class, $a->brand_accessory_id, BrandPricing::accessoryCategory($a->type))]])],
            'action' => route('engine-accessories.managed.save', $a->_id), 'back' => route('engine-accessories.index'),
        ]);
    }

    public function saveKit(string $id, Request $request)
    {
        $a = EngineAccessory::where('_id', $id)->whereNotNull('brand_accessory_id')->firstOrFail();
        $ctx = $this->context($a->brand_account_id, $a->managed_brand_id);
        $this->setSpecial($a, $request->input('special.' . $id),
            fn () => $this->levelPrice($ctx, BrandEngineAccessory::class, $a->brand_accessory_id, BrandPricing::accessoryCategory($a->type)));

        return back()->with('status', __('Special price saved.'));
    }

    /* ------------------------------------------------------------ helpers */

    /** Empty → back to the level price; a number → the dealer's own price. */
    private function setSpecial($item, $value, \Closure $levelPrice): void
    {
        $value = is_string($value) ? str_replace([' ', "\u{00A0}", ','], ['', '', '.'], trim($value)) : $value;
        if ($value === null || $value === '') {
            if ($item->cost_override) {
                $item->update(['cost_override' => false, 'cost' => $levelPrice()]);
            }
            return;
        }
        if (! is_numeric($value) || (float) $value < 0) return;
        $value = round((float) $value, 2);
        if (! $item->cost_override || (float) $item->cost !== $value) {
            $item->update(['cost_override' => true, 'cost' => $value]);
        }
    }

    /** The brand account, the dealer's level and the managed brand behind an item. */
    private function context(?string $accountId, ?string $managedBrandId): array
    {
        $account = $accountId ? BrandAccount::find($accountId) : null;
        $access = $account ? BrandAccess::where('company_id', (string) auth()->user()->company_id)
            ->where('brand_account_id', (string) $account->_id)->where('status', BrandAccess::STATUS_ACTIVE)->first() : null;
        $level = (int) ($access->level ?? 1);

        return [
            'account' => $account, 'level' => $level,
            'levelName' => $account ? $account->levelName($level) : null,
            'pricing' => $account ? new BrandPricing($account) : null,
            'brand' => $managedBrandId ? ManagedBrand::withoutGlobalScopes()->find($managedBrandId) : null,
        ];
    }

    private function levelPrice(array $ctx, string $class, ?string $brandItemId, string $category): ?float
    {
        if (! $ctx['pricing'] || ! $brandItemId) return null;
        $item = $class::withoutGlobalScopes()->find($brandItemId);

        return $item ? $ctx['pricing']->purchasePrice($item, $category, $ctx['level'], $ctx['brand']) : null;
    }
}
