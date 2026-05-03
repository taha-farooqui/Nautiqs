<?php

namespace App\Services;

use App\Models\CompanyBoatModel;
use App\Models\CompanyBoatVariant;
use App\Models\CompanyBrand;
use App\Models\CompanyOption;
use App\Models\GlobalBoatModel;
use App\Models\GlobalBoatVariant;
use App\Models\GlobalBrand;
use App\Models\GlobalOption;

/**
 * Spec §6.1 — copy-on-activation for global brands, plus the apply/dismiss
 * machinery for catalogue update notifications. Keep all snapshot logic in
 * one place so the same code services manual activation, re-activation, and
 * "Apply update" from a notification.
 */
class CatalogueService
{
    /**
     * Activate a global brand for a company. Idempotent — re-running just
     * upserts the snapshot (so the dealer can "re-pull" if they ever want
     * to wipe their customisations, though the apply-update flow is the
     * normal path for that).
     *
     * Returns the CompanyBrand snapshot. By default every variant is
     * activated; the dealer can later toggle per-variant.
     */
    public function activateGlobalBrand(string $companyId, GlobalBrand $brand): CompanyBrand
    {
        $companyBrand = CompanyBrand::where('company_id', $companyId)
            ->where('global_brand_id', (string) $brand->_id)
            ->first();

        if (! $companyBrand) {
            $companyBrand = CompanyBrand::create([
                'company_id'      => $companyId,
                'global_brand_id' => (string) $brand->_id,
                'source'          => CompanyBrand::SOURCE_GLOBAL,
                'name'            => $brand->name,
                'logo_path'       => $brand->logo_path,
                'description'     => $brand->description,
                'is_active'       => true,
                'activated_at'    => now(),
            ]);
        } else {
            // Re-activating — flip back on but DO NOT overwrite snapshot
            // fields the dealer may have edited.
            $companyBrand->update([
                'is_active'    => true,
                'activated_at' => $companyBrand->activated_at ?? now(),
            ]);
        }

        // Pull all global models for the brand and snapshot any that the
        // company doesn't already have.
        $globalModels = GlobalBoatModel::where('brand_id', (string) $brand->_id)
            ->where('is_archived', false)
            ->get();

        foreach ($globalModels as $globalModel) {
            $this->snapshotGlobalModel($companyId, $companyBrand, $globalModel);
        }

        return $companyBrand;
    }

    /**
     * Deactivate a global brand. Soft — keeps the snapshot intact so the
     * dealer doesn't lose any customisations if they re-activate later.
     * Hides all variants from the quote builder via is_active=false on
     * the brand.
     */
    public function deactivateGlobalBrand(string $companyId, CompanyBrand $companyBrand): void
    {
        if ($companyBrand->source !== CompanyBrand::SOURCE_GLOBAL) {
            return;
        }
        $companyBrand->update(['is_active' => false]);
    }

    /**
     * Copy a global model and all its variants/options into the dealership
     * workspace. Skips entries that already exist (matched by global id).
     */
    public function snapshotGlobalModel(string $companyId, CompanyBrand $companyBrand, GlobalBoatModel $globalModel): CompanyBoatModel
    {
        $companyModel = CompanyBoatModel::where('company_id', $companyId)
            ->where('global_model_id', (string) $globalModel->_id)
            ->first();

        if (! $companyModel) {
            $companyModel = CompanyBoatModel::create([
                'company_id'         => $companyId,
                'company_brand_id'   => (string) $companyBrand->_id,
                'global_model_id'    => (string) $globalModel->_id,
                'source'             => CompanyBoatModel::SOURCE_GLOBAL,
                'code'               => $globalModel->code,
                'name'               => $globalModel->name,
                'default_margin_pct' => $globalModel->default_margin_pct,
                'is_archived'        => false,
            ]);
        }

        // Variants
        $globalVariants = GlobalBoatVariant::where('model_id', (string) $globalModel->_id)
            ->where('is_archived', false)
            ->get();

        foreach ($globalVariants as $gv) {
            $exists = CompanyBoatVariant::where('company_id', $companyId)
                ->where('global_variant_id', (string) $gv->_id)
                ->exists();

            if ($exists) continue;

            CompanyBoatVariant::create([
                'company_id'         => $companyId,
                'company_model_id'   => (string) $companyModel->_id,
                'global_variant_id'  => (string) $gv->_id,
                'source'             => 'global',
                'name'               => $gv->name,
                'base_price'         => (float) $gv->base_price,
                'cost'               => (float) $gv->cost,
                'currency'           => $gv->currency ?? 'EUR',
                'included_equipment' => $gv->included_equipment ?? [],
                'is_active'          => true,
                'is_archived'        => false,
            ]);
        }

        // Options
        $globalOptions = GlobalOption::where('model_id', (string) $globalModel->_id)
            ->where('is_archived', false)
            ->get();

        foreach ($globalOptions as $go) {
            $exists = CompanyOption::where('company_id', $companyId)
                ->where('global_option_id', (string) $go->_id)
                ->exists();

            if ($exists) continue;

            CompanyOption::create([
                'company_id'        => $companyId,
                'company_model_id'  => (string) $companyModel->_id,
                'global_option_id'  => (string) $go->_id,
                'source'            => 'global',
                'category'          => $go->category,
                'label'             => $go->label,
                'price'             => (float) $go->price,
                'cost'              => (float) $go->cost,
                'currency'          => $go->currency ?? 'EUR',
                'position'          => (int) ($go->position ?? 0),
                'is_archived'       => false,
            ]);
        }

        return $companyModel;
    }

    /**
     * Create a private brand owned by a single dealership.
     */
    public function createPrivateBrand(string $companyId, array $data): CompanyBrand
    {
        return CompanyBrand::create([
            'company_id'      => $companyId,
            'global_brand_id' => null,
            'source'          => CompanyBrand::SOURCE_PRIVATE,
            'name'            => $data['name'],
            'logo_path'       => $data['logo_path'] ?? null,
            'description'     => $data['description'] ?? null,
            'is_active'       => true,
            'activated_at'    => now(),
        ]);
    }
}
