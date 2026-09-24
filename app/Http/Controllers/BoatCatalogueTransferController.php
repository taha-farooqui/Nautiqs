<?php

namespace App\Http\Controllers;

use App\Models\CompanyBoatModel;
use App\Models\CompanyBrand;
use App\Services\BoatCatalogueExporter;
use App\Services\BoatCatalogueImporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Export and import of the whole catalogue — boats, versions, included
 * equipment and options — as one workbook.
 *
 * Its own controller rather than more methods on CatalogueController, which is
 * already over 1 500 lines.
 *
 * Import is deliberately two requests. The dealer uploads, sees exactly what
 * the file would create and change, and only then confirms. The parsed plan
 * waits in the cache under a random token for fifteen minutes; the token is
 * namespaced by company so a plan can never be confirmed by another tenant.
 */
class BoatCatalogueTransferController extends Controller
{
    private const HOLD_MINUTES = 15;

    public function __construct(
        private BoatCatalogueExporter $exporter,
        private BoatCatalogueImporter $importer,
    ) {}

    /* ============================================================= EXPORT */

    public function export(Request $request)
    {
        $companyId = (string) auth()->user()->company_id;

        $brandId = $request->query('brand') ?: null;
        $modelId = $request->query('model') ?: null;
        $label   = 'catalogue';

        // Scope checks here, not in the exporter: a filter that points at
        // another tenant's record must 404, not quietly export everything.
        if ($modelId) {
            $boat = CompanyBoatModel::withoutGlobalScopes()->where('company_id', $companyId)
                ->where('_id', $modelId)->firstOrFail();
            $label = $boat->name;
        } elseif ($brandId) {
            $brand = CompanyBrand::withoutGlobalScopes()->where('company_id', $companyId)
                ->where('_id', $brandId)->firstOrFail();
            $label = $brand->name;
        }

        $path = $this->exporter->export($companyId, $brandId, $modelId);
        $name = 'nautiqs-' . Str::slug($label) . '-' . now()->format('Y-m-d') . '.xlsx';

        return response()->download($path, $name, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function template()
    {
        return response()->download($this->exporter->template(), 'nautiqs-modele-catalogue.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /* ============================================================= IMPORT */

    public function form()
    {
        return view('catalogue.import.upload');
    }

    public function preview(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:xlsx,xls,csv,txt'],
        ], [], ['file' => __('file')]);

        $companyId = (string) auth()->user()->company_id;
        $parsed    = $this->importer->parse($request->file('file'), $companyId);

        $token = Str::random(32);
        Cache::put($this->key($companyId, $token), $parsed, now()->addMinutes(self::HOLD_MINUTES));

        return view('catalogue.import.preview', [
            'token'    => $token,
            'parsed'   => $parsed,
            'filename' => $request->file('file')->getClientOriginalName(),
            'expires'  => self::HOLD_MINUTES,
        ]);
    }

    public function confirm(Request $request)
    {
        $request->validate(['token' => ['required', 'string']]);

        $companyId = (string) auth()->user()->company_id;
        $key       = $this->key($companyId, $request->input('token'));
        $parsed    = Cache::get($key);

        if (! $parsed) {
            return redirect()->route('catalogue.transfer.form')
                ->withErrors(['file' => __('That import expired. Upload the file again.')]);
        }

        // One-shot: pull it before writing, so a double submit cannot apply the
        // same plan twice and create every new boat a second time.
        Cache::forget($key);

        $result = $this->importer->commit($parsed, $companyId);

        $c = $result['created']; $u = $result['updated'];
        $parts = [];
        if ($c['brands'])   $parts[] = trans_choice('{1}:count brand created|[2,*]:count brands created', $c['brands'], ['count' => $c['brands']]);
        if ($c['models'])   $parts[] = trans_choice('{1}:count boat created|[2,*]:count boats created', $c['models'], ['count' => $c['models']]);
        if ($c['versions']) $parts[] = trans_choice('{1}:count version created|[2,*]:count versions created', $c['versions'], ['count' => $c['versions']]);
        if ($c['options'])  $parts[] = trans_choice('{1}:count option created|[2,*]:count options created', $c['options'], ['count' => $c['options']]);
        if ($u['models'])   $parts[] = trans_choice('{1}:count boat updated|[2,*]:count boats updated', $u['models'], ['count' => $u['models']]);
        if ($u['versions']) $parts[] = trans_choice('{1}:count version updated|[2,*]:count versions updated', $u['versions'], ['count' => $u['versions']]);
        if ($u['options'])  $parts[] = trans_choice('{1}:count option updated|[2,*]:count options updated', $u['options'], ['count' => $u['options']]);

        return redirect()->route('catalogue.models')
            ->with('status', $parts ? implode(' · ', $parts) : __('Nothing to change — the catalogue already matched the file.'));
    }

    private function key(string $companyId, string $token): string
    {
        return 'catalogue-import:' . $companyId . ':' . $token;
    }
}
