<?php

namespace App\Services\Brand;

use App\Models\BrandAccess;
use App\Models\BrandAccount;
use App\Models\BrandEngine;
use App\Models\BrandEngineAccessory;
use App\Models\BrandModel;
use App\Models\BrandOption;
use App\Models\BrandVariant;
use App\Models\CompanyBoatModel;
use App\Models\CompanyBoatVariant;
use App\Models\CompanyBrand;
use App\Models\CompanyOption;
use App\Models\Engine;
use App\Models\EngineAccessory;
use App\Models\ManagedBrand;
use App\Models\Notification;
use App\Models\User;

/**
 * Copies a brand account's catalogue into each dealer it is connected to, and
 * keeps the copies in step.
 *
 * The brand owns: names, specs, equipment, public prices, what exists.
 * The dealer owns: their purchase price when they typed a special one
 * (cost_override), and everything on their quotes — quotes are snapshots and
 * are never touched here.
 *
 * Everything runs unscoped with an explicit company_id: this is called by
 * the brand (editing its catalogue) and by the dealer (accepting), and
 * neither one's global scope may decide what the other sees. Records are
 * loaded and saved one by one rather than bulk-updated by id — on this driver
 * a bulk match on string ids can silently match nothing.
 */
class BrandSync
{
    /**
     * Push the account's catalogue to every connected dealer.
     *
     * @return array<string, int>  company_id => number of records changed
     */
    public function syncAccount(BrandAccount $account, bool $notify = true): array
    {
        $out = [];
        $accesses = BrandAccess::where('brand_account_id', (string) $account->_id)
            ->where('status', BrandAccess::STATUS_ACTIVE)->get();

        foreach ($accesses as $access) {
            $changes = $this->syncAccess($access, $account);
            $out[(string) $access->company_id] = $changes;
            if ($notify && $changes > 0) {
                $this->notifyDealer((string) $access->company_id, 'brand.catalogue_updated',
                    __(':brand updated its catalogue', ['brand' => $account->name]),
                    __(':count changes in your catalogue. Quotes already made are unchanged.', ['count' => $changes]),
                    route('catalogue.models'));
            }
        }

        return $out;
    }

    /** Bring one connected dealer up to date. Returns the number of records changed. */
    public function syncAccess(BrandAccess $access, ?BrandAccount $account = null): int
    {
        $account ??= BrandAccount::find($access->brand_account_id);
        if (! $account || ! $access->company_id || ! $access->isActive()) {
            return 0;
        }

        $companyId = (string) $access->company_id;
        $level     = max(1, min(BrandAccount::LEVEL_COUNT, (int) ($access->level ?: 1)));
        $shared    = $access->brandIds();
        $pricing   = new BrandPricing($account);
        $changes   = 0;

        foreach ($this->brandsOf($account) as $brand) {
            $isShared = in_array((string) $brand->_id, $shared, true) && $brand->is_active !== false;
            if ($account->isEngine()) {
                $changes += $isShared
                    ? $this->syncEngines($account, $brand, $pricing, $level, $companyId)
                    : $this->detachEngineBrand($brand, $companyId);
            } else {
                $changes += $isShared
                    ? $this->syncBoats($account, $brand, $pricing, $level, $companyId)
                    : $this->detachBoatBrand($brand, $companyId);
            }
        }

        return $changes;
    }

    /** Hide every brand of the account from this dealer (revoked / disconnected). */
    public function detachAccess(BrandAccess $access): int
    {
        $account = BrandAccount::find($access->brand_account_id);
        if (! $account || ! $access->company_id) {
            return 0;
        }
        $changes = 0;
        foreach ($this->brandsOf($account) as $brand) {
            $changes += $account->isEngine()
                ? $this->detachEngineBrand($brand, (string) $access->company_id)
                : $this->detachBoatBrand($brand, (string) $access->company_id);
        }

        return $changes;
    }

    /* ---------------------------------------------------------------- boats */

    private function syncBoats(BrandAccount $account, ManagedBrand $brand, BrandPricing $pricing, int $level, string $companyId): int
    {
        $changes = 0;
        $brandId = (string) $brand->_id;

        $companyBrand = CompanyBrand::withoutGlobalScopes()->where('company_id', $companyId)
            ->where('managed_brand_id', $brandId)->first();
        if (! $companyBrand) {
            $companyBrand = CompanyBrand::create([
                'company_id' => $companyId, 'source' => CompanyBrand::SOURCE_BRAND, 'global_brand_id' => null,
                'brand_account_id' => (string) $account->_id, 'managed_brand_id' => $brandId,
                'name' => $brand->name, 'is_active' => true, 'activated_at' => now(),
            ]);
            $changes++;
        } else {
            $changes += $this->apply($companyBrand, ['name' => $brand->name, 'is_active' => true, 'brand_account_id' => (string) $account->_id]);
        }
        $companyBrandId = (string) $companyBrand->_id;

        $models   = BrandModel::withoutGlobalScopes()->where('managed_brand_id', $brandId)->get();
        $modelIds = $models->map(fn ($m) => (string) $m->_id)->all();
        $copies   = CompanyBoatModel::withoutGlobalScopes()->where('company_id', $companyId)
            ->where('company_brand_id', $companyBrandId)->get()
            ->keyBy(fn ($m) => (string) $m->brand_model_id);

        $copyIdByModel = [];
        foreach ($models as $m) {
            $fields = [
                'name' => $m->name, 'code' => $m->code, 'year' => $m->year, 'type' => $m->type, 'propulsion' => $m->propulsion,
                'length_total' => $m->length_total, 'beam' => $m->beam, 'draft_max' => $m->draft_max,
                'weight' => $m->weight, 'passengers' => $m->passengers, 'is_archived' => (bool) $m->is_archived,
            ];
            $copy = $copies[(string) $m->_id] ?? null;
            if (! $copy) {
                $copy = CompanyBoatModel::create($fields + [
                    'company_id' => $companyId, 'company_brand_id' => $companyBrandId,
                    'brand_model_id' => (string) $m->_id, 'source' => CompanyBrand::SOURCE_BRAND, 'is_active' => true,
                ]);
                $changes++;
            } else {
                $changes += $this->apply($copy, $fields);
            }
            $copyIdByModel[(string) $m->_id] = ['id' => (string) $copy->_id, 'archived' => (bool) $m->is_archived];
        }
        // A brand model that no longer exists: archive the copy.
        foreach ($copies as $brandModelId => $copy) {
            if ($brandModelId !== '' && ! isset($copyIdByModel[$brandModelId])) {
                $changes += $this->apply($copy, ['is_archived' => true]);
            }
        }

        // Versions
        $variants = BrandVariant::withoutGlobalScopes()->where('managed_brand_id', $brandId)->get();
        $vCopies  = CompanyBoatVariant::withoutGlobalScopes()->where('company_id', $companyId)
            ->whereNotNull('brand_variant_id')->get()->keyBy(fn ($v) => (string) $v->brand_variant_id);
        $seen = [];
        foreach ($variants as $v) {
            $parent = $copyIdByModel[(string) $v->brand_model_id] ?? null;
            if (! $parent) continue;
            $seen[(string) $v->_id] = true;
            $fields = [
                'company_model_id'   => $parent['id'],
                'name'               => $v->name,
                'base_price'         => (float) $v->price,
                'currency'           => $v->currency ?: 'EUR',
                'included_equipment' => is_array($v->included_equipment) ? $v->included_equipment : [],
                'is_archived'        => (bool) $v->is_archived || $parent['archived'],
            ];
            $copy = $vCopies[(string) $v->_id] ?? null;
            if (! $copy || ! $copy->cost_override) {
                $fields['cost'] = $pricing->purchasePrice($v, 'versions', $level, $brand);
            }
            if (! $copy) {
                CompanyBoatVariant::create($fields + [
                    'company_id' => $companyId, 'brand_variant_id' => (string) $v->_id,
                    'source' => CompanyBrand::SOURCE_BRAND, 'is_active' => true, 'cost_override' => false,
                ]);
                $changes++;
            } else {
                $changes += $this->apply($copy, $fields);
            }
        }
        foreach ($vCopies as $bvId => $copy) {
            if (! isset($seen[$bvId]) && in_array((string) $copy->company_model_id, array_column($copyIdByModel, 'id'), true)) {
                $changes += $this->apply($copy, ['is_archived' => true]);
            }
        }

        // Options
        $options = BrandOption::withoutGlobalScopes()->where('managed_brand_id', $brandId)->get();
        $oCopies = CompanyOption::withoutGlobalScopes()->where('company_id', $companyId)
            ->whereNotNull('brand_option_id')->get()->keyBy(fn ($o) => (string) $o->brand_option_id);
        $seen = [];
        foreach ($options as $o) {
            $parent = $copyIdByModel[(string) $o->brand_model_id] ?? null;
            if (! $parent) continue;
            $seen[(string) $o->_id] = true;
            $fields = [
                'company_model_id' => $parent['id'],
                'category'         => $o->category ?: 'Options',
                'label'            => $o->label,
                'description'      => $o->description ?: null,
                'price'            => (float) $o->price,
                'vat_rate'         => $o->vat_rate !== null ? (float) $o->vat_rate : null,
                'currency'         => $o->currency ?: 'EUR',
                'position'         => (int) ($o->position ?? 0),
                'is_archived'      => (bool) $o->is_archived || $parent['archived'],
            ];
            $copy = $oCopies[(string) $o->_id] ?? null;
            if (! $copy || ! $copy->cost_override) {
                $fields['cost'] = $pricing->purchasePrice($o, 'options', $level, $brand);
            }
            if (! $copy) {
                CompanyOption::create($fields + [
                    'company_id' => $companyId, 'brand_option_id' => (string) $o->_id,
                    'source' => CompanyBrand::SOURCE_BRAND, 'cost_override' => false,
                ]);
                $changes++;
            } else {
                $changes += $this->apply($copy, $fields);
            }
        }
        foreach ($oCopies as $boId => $copy) {
            if (! isset($seen[$boId]) && in_array((string) $copy->company_model_id, array_column($copyIdByModel, 'id'), true)) {
                $changes += $this->apply($copy, ['is_archived' => true]);
            }
        }

        return $changes;
    }

    private function detachBoatBrand(ManagedBrand $brand, string $companyId): int
    {
        $cb = CompanyBrand::withoutGlobalScopes()->where('company_id', $companyId)
            ->where('managed_brand_id', (string) $brand->_id)->first();

        return $cb ? $this->apply($cb, ['is_active' => false]) : 0;
    }

    /* -------------------------------------------------------------- engines */

    private function syncEngines(BrandAccount $account, ManagedBrand $brand, BrandPricing $pricing, int $level, string $companyId): int
    {
        $changes   = 0;
        $brandId   = (string) $brand->_id;
        $accountId = (string) $account->_id;

        // Kits and propellers first: the engines link to their dealer copies.
        $accs    = BrandEngineAccessory::withoutGlobalScopes()->where('managed_brand_id', $brandId)->get();
        $aCopies = EngineAccessory::withoutGlobalScopes()->where('company_id', $companyId)
            ->where('managed_brand_id', $brandId)->get()->keyBy(fn ($a) => (string) $a->brand_accessory_id);
        $dealerAccId = [];
        foreach ($accs as $a) {
            $fields = [
                'type' => $a->type, 'label' => $a->label, 'reference' => $a->reference,
                'price' => (float) $a->price, 'vat_rate' => $a->vat_rate !== null ? (float) $a->vat_rate : 20,
                'currency' => $a->currency ?: 'EUR', 'brand_account_id' => $accountId, 'managed_brand_id' => $brandId,
            ];
            $copy = $aCopies[(string) $a->_id] ?? null;
            if (! $copy || ! $copy->cost_override) {
                $fields['cost'] = $pricing->purchasePrice($a, BrandPricing::accessoryCategory($a->type), $level, $brand);
            }
            if (! $copy) {
                $copy = EngineAccessory::create($fields + ['company_id' => $companyId, 'brand_accessory_id' => (string) $a->_id, 'cost_override' => false]);
                $changes++;
            } else {
                $changes += $this->apply($copy, $fields);
            }
            $dealerAccId[(string) $a->_id] = (string) $copy->_id;
        }
        // A kit the brand deleted: remove the dealer copy (quotes keep their lines).
        $removed = [];
        foreach ($aCopies as $baId => $copy) {
            if (! isset($dealerAccId[$baId])) {
                $removed[] = (string) $copy->_id;
                $copy->delete();
                $changes++;
            }
        }
        $managedAccIds = array_merge(array_values($dealerAccId), $removed);

        $engines = BrandEngine::withoutGlobalScopes()->where('managed_brand_id', $brandId)->get();
        $eCopies = Engine::withoutGlobalScopes()->where('company_id', $companyId)
            ->where('managed_brand_id', $brandId)->get()->keyBy(fn ($e) => (string) $e->brand_engine_id);
        $seen = [];
        foreach ($engines as $e) {
            $seen[(string) $e->_id] = true;
            $copy = $eCopies[(string) $e->_id] ?? null;
            $linked = array_values(array_filter(array_map(fn ($id) => $dealerAccId[$id] ?? null, $e->accessoryIds())));
            // Kits the dealer linked themselves to this engine are kept.
            $own = $copy ? array_values(array_diff($copy->accessoryIds(), $managedAccIds)) : [];
            $fields = [
                'brand' => $brand->name, 'code' => $e->code, 'horsepower' => $e->horsepower, 'fuel' => $e->fuel,
                'description' => $e->description ?: null, 'price' => (float) $e->price,
                'vat_rate' => $e->vat_rate !== null ? (float) $e->vat_rate : 20, 'currency' => $e->currency ?: 'EUR',
                'is_archived' => (bool) $e->is_archived, 'brand_account_id' => $accountId, 'managed_brand_id' => $brandId,
                'accessory_ids' => array_values(array_unique(array_merge($linked, $own))),
            ];
            if (! $copy || ! $copy->cost_override) {
                $fields['cost'] = $pricing->purchasePrice($e, 'engines', $level, $brand);
            }
            if (! $copy) {
                Engine::create($fields + ['company_id' => $companyId, 'brand_engine_id' => (string) $e->_id, 'cost_override' => false]);
                $changes++;
            } else {
                $changes += $this->apply($copy, $fields);
            }
        }
        foreach ($eCopies as $beId => $copy) {
            if (! isset($seen[$beId])) {
                $changes += $this->apply($copy, ['is_archived' => true]);
            }
        }

        return $changes;
    }

    private function detachEngineBrand(ManagedBrand $brand, string $companyId): int
    {
        $changes = 0;
        $brandId = (string) $brand->_id;
        foreach (Engine::withoutGlobalScopes()->where('company_id', $companyId)->where('managed_brand_id', $brandId)->get() as $e) {
            $changes += $this->apply($e, ['is_archived' => true]);
        }
        $removed = [];
        foreach (EngineAccessory::withoutGlobalScopes()->where('company_id', $companyId)->where('managed_brand_id', $brandId)->get() as $a) {
            $removed[] = (string) $a->_id;
            $a->delete();
            $changes++;
        }
        if ($removed) {
            // Also off the dealer's own engines, so nothing suggests a kit that is gone.
            foreach (Engine::withoutGlobalScopes()->where('company_id', $companyId)->get() as $e) {
                $kept = array_values(array_diff($e->accessoryIds(), $removed));
                if (count($kept) !== count($e->accessoryIds())) {
                    $e->accessory_ids = $kept;
                    $e->save();
                }
            }
        }

        return $changes;
    }

    /* ------------------------------------------------- manual duplicates */

    /**
     * When a dealer accepts, archive the catalogue they had typed by hand for
     * the same brands (same name), so the builder doesn't offer both. Nothing
     * is deleted; quotes are untouched; restoreDuplicates() undoes it.
     */
    public function archiveManualDuplicates(BrandAccess $access): int
    {
        $account = BrandAccount::find($access->brand_account_id);
        if (! $account || ! $access->company_id) return 0;
        $names = $this->sharedBrandNames($account, $access);
        $count = 0;

        if ($account->isEngine()) {
            foreach (Engine::withoutGlobalScopes()->where('company_id', (string) $access->company_id)->whereNull('brand_engine_id')->where('is_archived', false)->get() as $e) {
                if (in_array(mb_strtolower(trim((string) $e->brand)), $names, true)) {
                    $e->update(['is_archived' => true, 'archived_by_brand_at' => now()]);
                    $count++;
                }
            }
        } else {
            foreach (CompanyBrand::withoutGlobalScopes()->where('company_id', (string) $access->company_id)->whereNull('managed_brand_id')->get() as $cb) {
                if ($cb->is_active !== false && in_array(mb_strtolower(trim((string) $cb->name)), $names, true)) {
                    $cb->update(['is_active' => false, 'archived_by_brand_at' => now()]);
                    $count++;
                }
            }
        }

        return $count;
    }

    /** What the dealer has by hand that matches the brands being shared. */
    public function manualDuplicates(BrandAccess $access): array
    {
        $account = BrandAccount::find($access->brand_account_id);
        if (! $account || ! $access->company_id) return [];
        $names = $this->sharedBrandNames($account, $access);
        $out = [];

        if ($account->isEngine()) {
            $engines = Engine::withoutGlobalScopes()->where('company_id', (string) $access->company_id)->whereNull('brand_engine_id')->where('is_archived', false)->get();
            foreach ($engines->groupBy(fn ($e) => mb_strtolower(trim((string) $e->brand))) as $key => $group) {
                if (in_array($key, $names, true)) $out[] = ['name' => $group->first()->brand, 'count' => $group->count()];
            }
        } else {
            foreach (CompanyBrand::withoutGlobalScopes()->where('company_id', (string) $access->company_id)->whereNull('managed_brand_id')->get() as $cb) {
                if ($cb->is_active !== false && in_array(mb_strtolower(trim((string) $cb->name)), $names, true)) {
                    $n = CompanyBoatModel::withoutGlobalScopes()->where('company_id', (string) $access->company_id)
                        ->where('company_brand_id', (string) $cb->_id)->where('is_archived', false)->count();
                    if ($n > 0) $out[] = ['name' => $cb->name, 'count' => $n];
                }
            }
        }

        return $out;
    }

    private function sharedBrandNames(BrandAccount $account, BrandAccess $access): array
    {
        return ManagedBrand::withoutGlobalScopes()->where('brand_account_id', (string) $account->_id)->get()
            ->filter(fn ($b) => in_array((string) $b->_id, $access->brandIds(), true))
            ->map(fn ($b) => mb_strtolower(trim((string) $b->name)))->values()->all();
    }

    /* -------------------------------------------------------------- helpers */

    private function brandsOf(BrandAccount $account)
    {
        return ManagedBrand::withoutGlobalScopes()->where('brand_account_id', (string) $account->_id)->get();
    }

    /** Fill and save only when something actually changed. 1 = changed. */
    private function apply($model, array $fields): int
    {
        foreach ($fields as $k => $v) {
            $cur = $model->getAttribute($k);
            if (is_float($v) || is_int($v)) {
                if ($cur === null || abs((float) $cur - (float) $v) > 0.004) {
                    $model->setAttribute($k, $v);
                }
            } elseif ($cur != $v || ($v === null) !== ($cur === null)) {
                $model->setAttribute($k, $v);
            }
        }
        if ($model->isDirty()) {
            $model->save();
            return 1;
        }

        return 0;
    }

    /** One notification per user of the dealership. */
    public function notifyDealer(string $companyId, string $type, string $title, string $message, ?string $link = null): void
    {
        foreach (User::where('company_id', $companyId)->where('is_active', '!=', false)->get(['_id']) as $u) {
            Notification::create([
                'user_id' => (string) $u->_id, 'company_id' => $companyId, 'type' => $type,
                'title' => $title, 'message' => $message, 'icon' => 'ri-building-4-line',
                'color' => 'primary', 'link' => $link, 'read_at' => null,
            ]);
        }
    }
}
