<?php

namespace App\Http\Middleware;

use App\Models\CompanyBoatModel;
use App\Models\CompanyBoatVariant;
use App\Models\CompanyOption;
use App\Models\Engine;
use App\Models\EngineAccessory;
use Closure;
use Illuminate\Http\Request;

/**
 * Items a partner brand publishes to the dealer (brand_*_id set by BrandSync)
 * belong to the brand: the dealer can quote them and set a private special
 * price, nothing else. This sits in front of the dealer's own catalogue,
 * engine and kit routes so none of the many edit paths there has to know:
 *
 *   - an edit page for a managed item goes to the read-only managed view
 *   - any other write to a managed item is refused
 *   - bulk deletes quietly drop managed ids
 *
 * Read-only routes (lists, exports, templates) pass through.
 */
class ProtectManagedCatalogue
{
    private const EDIT_PAGES = ['catalogue.models.edit', 'engines.edit', 'engine-accessories.edit'];

    public function handle(Request $request, Closure $next)
    {
        $name = (string) $request->route()?->getName();
        // The managed views themselves (special price) are the one way in.
        if (! preg_match('/^(catalogue|engines|engine-accessories)\./', $name) || str_contains($name, '.managed')) {
            return $next($request);
        }

        if (in_array($name, ['engines.bulk-destroy', 'engine-accessories.bulk-destroy', 'catalogue.options.bulk-destroy'], true)) {
            $this->dropManagedIds($request, $name);
        }

        $managed = $this->managedTarget($request);
        if (! $managed) {
            return $next($request);
        }

        if (in_array($name, self::EDIT_PAGES, true)) {
            return redirect()->route(...$managed);
        }
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        $msg = __('This item is managed by its brand. You can set your own special price; everything else is updated by the brand.');
        if ($request->expectsJson()) {
            return response()->json(['message' => $msg], 403);
        }

        return redirect()->route(...$managed)->withErrors(['managed' => $msg]);
    }

    /** [route, params] of the managed view when the route targets a brand item. */
    private function managedTarget(Request $request): ?array
    {
        $p = $request->route()->parameters();

        if (! empty($p['modelId'])) {
            $m = CompanyBoatModel::find($p['modelId']);
            return $m && $m->brand_model_id ? ['catalogue.managed.boat', $m->_id] : null;
        }
        if (! empty($p['variantId']) || ! empty($p['companyVariantId'])) {
            $v = CompanyBoatVariant::find($p['variantId'] ?? $p['companyVariantId']);
            // Hiding a version from the builder is the dealer's call; editing it is not.
            if ($v && $v->brand_variant_id && $request->route()->getName() !== 'catalogue.variants.toggle') {
                return ['catalogue.managed.boat', $v->company_model_id];
            }
            return null;
        }
        if (! empty($p['optionId'])) {
            $o = CompanyOption::find($p['optionId']);
            return $o && $o->brand_option_id ? ['catalogue.managed.boat', $o->company_model_id] : null;
        }
        if (! empty($p['engine'])) {
            $e = Engine::find($p['engine']);
            return $e && $e->brand_engine_id ? ['engines.managed', $e->_id] : null;
        }
        if (! empty($p['id']) && str_starts_with((string) $request->route()->getName(), 'engine-accessories.')) {
            $a = EngineAccessory::find($p['id']);
            return $a && $a->brand_accessory_id ? ['engine-accessories.managed', $a->_id] : null;
        }

        return null;
    }

    private function dropManagedIds(Request $request, string $name): void
    {
        $ids = array_map('strval', (array) $request->input('ids', []));
        if (! $ids) return;

        $q = match ($name) {
            'engines.bulk-destroy'            => Engine::whereNotNull('brand_engine_id'),
            'engine-accessories.bulk-destroy' => EngineAccessory::whereNotNull('brand_accessory_id'),
            default                           => CompanyOption::whereNotNull('brand_option_id'),
        };
        $managed = $q->get(['_id'])->map(fn ($r) => (string) $r->_id)->all();
        $request->merge(['ids' => array_values(array_diff($ids, $managed))]);
    }
}
