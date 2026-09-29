<?php

namespace App\Http\Controllers;

use App\Models\Engine;
use App\Models\EngineAccessory;
use App\Services\EngineImporter;
use App\Services\Xlsx;
use Illuminate\Http\Request;

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
            'accessories' => count($row->accessoryIds()),
        ];
    }

    public function create()
    {
        return view('engines.form', ['engine' => null, 'accessories' => $this->accessoryChoices()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['code'] = $this->resolveCode($data);
        if (empty($data['horsepower'])) {
            $data['horsepower'] = Engine::horsepowerFromCode($data['code']);
        }
        Engine::create(array_merge($data, [
            'company_id'    => auth()->user()->company_id,
            'is_archived'   => false,
            'accessory_ids' => $this->postedAccessoryIds($request) ?? [],
        ]));
        return redirect()->route('engines.index')->with('status', __('Engine added.'));
    }

    public function edit(string $id)
    {
        $engine = Engine::findOrFail($id);
        return view('engines.form', ['engine' => $engine, 'accessories' => $this->accessoryChoices()]);
    }

    private function accessoryChoices()
    {
        return EngineAccessory::orderBy('type')->orderBy('label')->get();
    }

    /**
     * The accessory ids posted by the form, kept only if they are this
     * dealer's (the tenant scope does the filtering). Null when the form had
     * no accessory section, so an engine saved before any accessory existed
     * does not have its list touched.
     */
    private function postedAccessoryIds(Request $request): ?array
    {
        if (! $request->boolean('accessory_ids_present')) {
            return null;
        }

        $posted = array_map('strval', (array) $request->input('accessory_ids', []));
        $valid  = EngineAccessory::get(['_id'])->map(fn ($a) => (string) $a->_id)->all();

        return array_values(array_intersect($posted, $valid));
    }

    /**
     * The Code / SKU field was removed from the form. Keep a stable, human
     * code for the dedup key + quote-builder label: derive it from the
     * horsepower ("200HP") when present, else a generic fallback. Any code
     * the user (or an import) does provide is respected.
     */
    private function resolveCode(array $data): string
    {
        if (filled($data['code'] ?? null)) {
            return $data['code'];
        }
        $hp = (int) ($data['horsepower'] ?? 0);
        return $hp > 0 ? $hp . 'HP' : 'STD';
    }

    public function update(string $id, Request $request)
    {
        $engine = Engine::findOrFail($id);
        $data = $this->validated($request);
        if (($ids = $this->postedAccessoryIds($request)) !== null) {
            $data['accessory_ids'] = $ids;
        }
        $engine->update($data);
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
            'code'        => 'nullable|string|max:120',
            'horsepower'  => 'nullable|numeric|min:0',
            'fuel'        => 'nullable|in:petrol,diesel,electric,unknown',
            'description' => 'nullable|string',
            'cost'        => 'nullable|numeric|min:0',
            'price'       => 'required|numeric|min:0',
            'vat_rate'    => 'nullable|numeric|min:0|max:100',
            'currency'    => 'nullable|in:EUR,USD',
        ]);
    }

    /**
     * The layout Suzuki's own price export uses, so the file a dealer gets
     * from the manufacturer imports as it is. CV and DESCRIPTION are extras
     * the import also reads; TOTAL is written for the dealer to check a row
     * at a glance and ignored on the way back in.
     */
    private const SHEET_HEADERS = [
        'MARQUE', 'MODELE', 'CV', 'MOTEUR PA HT', 'MOTEUR PV HT',
        'KIT PRE-RIGGING', 'KIT PA HT', 'KIT PV HT',
        'HELICE', 'HELICE PA HT', 'HELICE PV HT',
        'TOTAL PA HT', 'TOTAL PV HT', 'TVA', 'DESCRIPTION',
    ];
    private const SHEET_WIDTHS = [12, 22, 6, 13, 13, 60, 11, 11, 60, 13, 13, 12, 12, 6, 40];

    /**
     * One row per engine with its kit and propeller beside it — exactly what
     * the import reads. An engine linked to several kits or propellers exports
     * the first of each; re-importing never removes a link, so the others are
     * kept.
     */
    public function export()
    {
        $engines = Engine::where('is_archived', false)->get()
            ->sortBy(fn ($e) => [(string) $e->brand, (float) ($e->horsepower ?? 0), (string) $e->code])
            ->values();

        $byId = EngineAccessory::all()->keyBy(fn ($a) => (string) $a->_id);

        $rows = [];
        foreach ($engines as $e) {
            $linked = collect($e->accessoryIds())->map(fn ($id) => $byId[$id] ?? null)->filter();
            $kit  = $linked->firstWhere('type', EngineAccessory::TYPE_KIT);
            $prop = $linked->firstWhere('type', EngineAccessory::TYPE_PROPELLER);

            $money = fn ($v) => round((float) ($v ?? 0), 2);
            $cost  = $money($e->cost) + $money($kit?->cost) + $money($prop?->cost);
            $price = $money($e->price) + $money($kit?->price) + $money($prop?->price);

            $rows[] = [
                (string) $e->brand,
                (string) $e->code,
                $e->horsepower ? (float) $e->horsepower : '',
                $money($e->cost),
                $money($e->price),
                $kit ? $kit->label : '',
                $kit ? $money($kit->cost) : '',
                $kit ? $money($kit->price) : '',
                $prop ? $prop->label : '',
                $prop ? $money($prop->cost) : '',
                $prop ? $money($prop->price) : '',
                round($cost, 2),
                round($price, 2),
                $e->vat_rate !== null ? (float) $e->vat_rate : 20,
                (string) ($e->description ?? ''),
            ];
        }

        $path = Xlsx::write(['Moteurs' => [
            'headers' => self::SHEET_HEADERS,
            'rows'    => $rows,
            'widths'  => self::SHEET_WIDTHS,
        ]], 'nautiqs-engines-');

        return response()->download($path, 'nautiqs-moteurs-' . now()->format('Y-m-d') . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * The same sheet with three sample rows: a small engine whose price
     * already covers everything, and one of each kit type with a propeller.
     */
    public function template()
    {
        $inc = 'Inclus / non applicable';
        $path = Xlsx::write(['Moteurs' => [
            'headers' => self::SHEET_HEADERS,
            'widths'  => self::SHEET_WIDTHS,
            'rows'    => [
                ['Suzuki', 'DF 15A S/L', 15, 2465, 3625, $inc, 0, 0,
                 'Incluse dans le tarif moteur', 0, 0, 2465, 3625, 20, ''],
                ['Suzuki', 'DF140B TL/TX', 140, 10086.67, 14833.33,
                 'Kit pré-rigging MECA - boîtier pupitre simple Keyless + faisceau 6,50 m + écran MF 4 pouces', 932.75, 1332.5,
                 'Hélice ALU 3X14X21 RR - 58100-90JC0-019', 186.08, 265.83, 11205.5, 16431.66, 20, ''],
                ['Suzuki', 'DF200AP L/X', 200, 13543.33, 19916.67,
                 'Kit pré-rigging SPC - boîtier pupitre simple + faisceau 6,50 m + écran MF 4 pouces', 1749.42, 2499.17,
                 'Hélice WATERGRIP 3X15.1/2X17 CR - 58800-96J00-000', 658.58, 940.83, 15951.33, 23356.67, 20, ''],
            ],
        ]], 'nautiqs-engines-template-');

        return response()->download($path, 'nautiqs-moteurs-modele.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
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
        if ($result['accessories_created'] || $result['accessories_updated'] || $result['links_added']) {
            $msg .= ' ' . __('Accessories: :created created, :updated updated, :links linked to engines.', [
                'created' => $result['accessories_created'],
                'updated' => $result['accessories_updated'],
                'links'   => $result['links_added'],
            ]);
        }

        return redirect()->route('engines.index')
            ->with('status', $msg)
            ->with('import_result', $result);
    }
}
