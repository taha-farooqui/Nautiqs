<?php

namespace App\Http\Controllers\Brand;

use App\Http\Controllers\Controller;
use App\Services\Brand\BrandSync;
use App\Services\Brand\BrandTransfer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Excel round trip of the brand's catalogue. Import is one step — it only
 * creates and updates (see BrandTransfer), then publishes to the dealers.
 */
class TransferController extends Controller
{
    private const XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    public function __construct(private BrandTransfer $transfer) {}

    public function export()
    {
        $account = $this->account();
        $name = 'nautiqs-' . Str::slug($account->name) . '-' . now()->format('Y-m-d') . '.xlsx';

        return response()->download($this->transfer->export($account), $name, ['Content-Type' => self::XLSX])->deleteFileAfterSend(true);
    }

    public function template()
    {
        $account = $this->account();

        return response()->download($this->transfer->export($account, true), 'nautiqs-modele-' . ($account->isEngine() ? 'moteurs' : 'bateaux') . '.xlsx', ['Content-Type' => self::XLSX])
            ->deleteFileAfterSend(true);
    }

    public function form()
    {
        return view('brand.transfer', ['account' => $this->account()]);
    }

    public function import(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'max:10240', 'mimes:xlsx']], [], ['file' => __('file')]);
        $account = $this->account();

        $result = $this->transfer->import($account, $request->file('file')->getRealPath());
        if ($result['created'] || $result['updated']) {
            app(BrandSync::class)->syncAccount($account);
        }

        $msg = __(':c created · :u updated — published to your dealers.', ['c' => $result['created'], 'u' => $result['updated']]);
        if (! $result['created'] && ! $result['updated']) $msg = __('Nothing was imported.');

        return redirect()->route('brand.transfer.form')->with('status', $msg)->with('import_errors', $result['errors']);
    }

    private function account()
    {
        return auth()->user()->brandAccount;
    }
}
