<?php

namespace App\Http\Controllers;

use App\Models\Accessory;
use App\Services\AccessoryImporter;
use App\Services\Xlsx;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The dealer's accessories: third-party add-ons (GPS, audio, fishing gear)
 * that any quote can take. See App\Models\Accessory.
 */
class AccessoryController extends Controller
{
    /** The import reads these; the export and template write them. */
    private const SHEET_HEADERS = ['NOM', 'PRIX ACHAT HT', 'PRIX VENTE HT', 'TVA', 'DEVISE'];
    private const SHEET_WIDTHS  = [48, 16, 16, 8, 10];

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $accessories = Accessory::orderBy('label')->get();
        $total = $accessories->count();

        if ($q !== '') {
            $needle = mb_strtolower($q);
            $accessories = $accessories->filter(fn ($a) => str_contains(mb_strtolower((string) $a->label), $needle))->values();
        }

        return view('accessories.index', compact('accessories', 'total', 'q'));
    }

    public function create()
    {
        return view('accessories.form', ['accessory' => null]);
    }

    public function store(Request $request)
    {
        Accessory::create($this->validated($request) + ['company_id' => auth()->user()->company_id]);

        return redirect()->route('accessories.index')->with('status', __('Accessory added.'));
    }

    public function edit(string $id)
    {
        return view('accessories.form', ['accessory' => Accessory::findOrFail($id)]);
    }

    public function update(string $id, Request $request)
    {
        Accessory::findOrFail($id)->fill($this->validated($request))->save();

        return redirect()->route('accessories.index')->with('status', __('Accessory updated.'));
    }

    public function destroy(string $id)
    {
        Accessory::findOrFail($id)->delete();

        return back()->with('status', __('Accessory removed.'));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'label'    => 'required|string|max:500',
            'cost'     => 'nullable|numeric',
            'price'    => 'required|numeric',
            'vat_rate' => 'required|numeric|min:0|max:100',
            'currency' => ['required', Rule::in(Accessory::CURRENCIES)],
        ]);

        return [
            'label'    => trim($data['label']),
            'cost'     => isset($data['cost']) && $data['cost'] !== '' ? round((float) $data['cost'], 2) : null,
            'price'    => round((float) $data['price'], 2),
            'vat_rate' => (float) $data['vat_rate'],
            'currency' => $data['currency'],
        ];
    }

    /* ------------------------------------------------- Import / export */

    public function export()
    {
        $rows = Accessory::orderBy('label')->get()->map(fn ($a) => [
            (string) $a->label,
            $a->cost !== null ? round((float) $a->cost, 2) : '',
            round((float) $a->price, 2),
            $a->vat_rate !== null ? (float) $a->vat_rate : 20,
            $a->currency ?: 'EUR',
        ])->all();

        return $this->download($rows, 'nautiqs-accessoires-' . now()->format('Y-m-d') . '.xlsx');
    }

    public function template()
    {
        return $this->download([
            ['Système audio Bose', 80, 100, 20, 'EUR'],
            ['GPS Garmin ECHOMAP UHD2 92sv', 1150, 1490, 20, 'EUR'],
            ['Kit pêche Penn (canne + moulinet)', 210, 299, 20, 'USD'],
        ], 'nautiqs-accessoires-modele.xlsx');
    }

    private function download(array $rows, string $filename)
    {
        $path = Xlsx::write(['Accessoires' => [
            'headers' => self::SHEET_HEADERS,
            'rows'    => $rows,
            'widths'  => self::SHEET_WIDTHS,
        ]], 'nautiqs-accessories-');

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function import(Request $request, AccessoryImporter $importer)
    {
        $request->validate(['file' => 'required|file|max:10240']);

        $company = auth()->user()->company;
        $result  = $importer->import(
            $request->file('file'),
            (string) auth()->user()->company_id,
            (float) ($company->default_vat_rate ?? 20)
        );

        if ($result['errors'] && ! $result['created'] && ! $result['updated']) {
            return back()
                ->with('import_result', $result)
                ->withErrors(['file' => __('Import failed. See details below.')]);
        }

        return redirect()->route('accessories.index')
            ->with('status', __('Import done: :created created, :updated updated, :skipped skipped, :errors errors.', [
                'created' => $result['created'],
                'updated' => $result['updated'],
                'skipped' => $result['skipped'],
                'errors'  => count($result['errors']),
            ]))
            ->with('import_result', $result);
    }
}
