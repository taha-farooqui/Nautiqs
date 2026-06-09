<?php

namespace App\Http\Controllers;

use App\Models\Engine;
use App\Services\EngineImporter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EngineController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        // Engines are dealer-owned only — each company manages its own list
        // (added manually or imported). No platform/global library is mixed
        // in anymore.
        $query = Engine::query()
            ->where('is_archived', false)
            ->orderBy('brand')->orderBy('code');

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('brand', 'like', "%{$q}%")
                  ->orWhere('code', 'like', "%{$q}%")
                  ->orWhere('description', 'like', "%{$q}%");
            });
        }

        $engines = $query->paginate(50)->withQueryString()
            ->through(fn ($e) => $this->normalise($e, 'private'));

        return view('engines.index', compact('engines', 'q'));
    }

    /**
     * Flatten an Engine | GlobalEngine row into the shape the view uses.
     * `source` lets the view decide whether to show edit/delete buttons.
     */
    private function normalise($row, string $source): object
    {
        return (object) [
            'id'         => (string) $row->_id,
            'source'     => $source,
            'brand'      => $row->brand,
            'code'       => $row->code,
            'horsepower' => $row->horsepower,
            'fuel'       => $row->fuel,
            'price'      => (float) ($row->price ?? 0),
            'vat_rate'   => (float) ($row->vat_rate ?? 0),
            'ttc'        => $row->priceTtc(),
        ];
    }

    public function create()
    {
        return view('engines.form', ['engine' => null]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        Engine::create(array_merge($data, [
            'company_id'  => auth()->user()->company_id,
            'is_archived' => false,
        ]));
        return redirect()->route('engines.index')->with('status', __('Engine added.'));
    }

    public function edit(string $id)
    {
        $engine = Engine::findOrFail($id);
        return view('engines.form', compact('engine'));
    }

    public function update(string $id, Request $request)
    {
        $engine = Engine::findOrFail($id);
        $engine->update($this->validated($request));
        return redirect()->route('engines.index')->with('status', __('Engine updated.'));
    }

    public function destroy(string $id)
    {
        $engine = Engine::findOrFail($id);
        $engine->delete();
        return back()->with('status', __('Engine removed.'));
    }

    /**
     * Delete several engines at once from the list's bulk-select toolbar.
     * The TenantScope on Engine guarantees only the current company's rows
     * are touched, even if a foreign id is posted.
     */
    public function bulkDestroy(Request $request)
    {
        $data = $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'string',
        ]);

        $count = Engine::whereIn('_id', $data['ids'])->delete();

        return back()->with('status', __(':count engine(s) removed.', ['count' => $count]));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'brand'       => 'required|string|max:100',
            'code'        => 'required|string|max:120',
            'horsepower'  => 'nullable|numeric|min:0',
            'fuel'        => 'nullable|in:petrol,diesel,electric,unknown',
            'description' => 'nullable|string|max:500',
            'cost'        => 'nullable|numeric|min:0',
            'price'       => 'required|numeric|min:0',
            'vat_rate'    => 'nullable|numeric|min:0|max:100',
            'currency'    => 'nullable|in:EUR,USD',
        ]);
    }

    /**
     * Streamed CSV download of the import template. UTF-8 BOM included so
     * Excel opens accented brand names correctly. CSV is universal — any
     * dealer can open + edit it in Excel, Numbers, Google Sheets, or a
     * plain text editor.
     */
    public function template(): StreamedResponse
    {
        $filename = 'nautiqs-engines-template.csv';

        return response()->stream(function () {
            $out = fopen('php://output', 'w');
            // BOM so Excel detects UTF-8.
            fwrite($out, "\xef\xbb\xbf");
            fputcsv($out, ['Brand', 'Model', 'PA HT', 'PV HT', 'TVA']);
            fputcsv($out, ['Suzuki',  'DF200A TL/TX', 14800, 18500, 20]);
            fputcsv($out, ['Yamaha',  'F300 NCA',     23100, 28900, 20]);
            fputcsv($out, ['Mercury', 'Verado 350 XL', 27800, 34750, 20]);
            fclose($out);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'no-store',
        ]);
    }

    /**
     * Bulk-import engines from an uploaded CSV or XLSX. Delegates all
     * parsing / validation / upsert logic to EngineImporter and flashes
     * a structured summary back to the index page.
     */
    public function import(Request $request, EngineImporter $importer)
    {
        $request->validate([
            // No formal mimetype rule — Excel + LibreOffice mislabel the
            // mime on .csv files. We trust the extension + content-sniff
            // inside the importer instead.
            'file' => 'required|file|max:10240', // 10 MB hard cap
        ]);

        $companyId = (string) auth()->user()->company_id;
        $result    = $importer->import($request->file('file'), $companyId);

        if (! empty($result['errors']) && $result['created'] === 0 && $result['updated'] === 0) {
            // Pure-failure path — bubble back with errors so the dealer
            // can fix and retry.
            return back()
                ->with('import_result', $result)
                ->withErrors(['file' => __('Import failed. See details below.')]);
        }

        $msg = __(
            'Import done: :created created, :updated updated, :skipped skipped, :errors errors.',
            [
                'created' => $result['created'],
                'updated' => $result['updated'],
                'skipped' => $result['skipped'],
                'errors'  => count($result['errors']),
            ]
        );

        return redirect()->route('engines.index')
            ->with('status', $msg)
            ->with('import_result', $result);
    }
}
