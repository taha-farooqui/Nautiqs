<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Quote;
use App\Models\QuoteCounter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Spec §11 (lifecycle), §12 (Quote PDF), §13 (Order confirmation PDF),
 * §16.2 (Quotes page).
 */
class QuoteController extends Controller
{
    public function index(Request $request)
    {
        $query = Quote::query()->with('client')->orderBy('created_at', 'desc');

        $status = $request->query('status');
        if ($status && in_array($status, Quote::STATUSES, true)) {
            $query->where('status', $status);
        }

        $clientId = $request->query('client_id');
        if ($clientId) {
            $query->where('client_id', $clientId);
        }

        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('number', 'like', "%{$q}%")
                  ->orWhere('client_snapshot.first_name', 'like', "%{$q}%")
                  ->orWhere('client_snapshot.last_name',  'like', "%{$q}%")
                  ->orWhere('model_snapshot.name',        'like', "%{$q}%");
            });
        }

        $quotes = $query->paginate(20)->withQueryString();

        $counts = [
            'all'   => Quote::count(),
            'draft' => Quote::where('status', Quote::STATUS_DRAFT)->count(),
            'sent'  => Quote::where('status', Quote::STATUS_SENT)->count(),
            'won'   => Quote::where('status', Quote::STATUS_WON)->count(),
            'lost'  => Quote::where('status', Quote::STATUS_LOST)->count(),
        ];

        return view('quotes.index', compact('quotes', 'status', 'q', 'counts'));
    }

    public function create(Request $request)
    {
        return view('quotes.create', [
            'preselectedClientId' => $request->query('client_id'),
        ]);
    }

    public function show(string $id)
    {
        $quote = Quote::findOrFail($id);
        return view('quotes.show', ['quote' => $quote]);
    }

    public function edit(string $id)
    {
        $quote = Quote::findOrFail($id);
        if (! $quote->isEditable()) {
            return redirect()->route('quotes.show', $id)
                ->withErrors(['edit' => 'Only draft quotes can be edited.']);
        }
        return view('quotes.edit', ['quote' => $quote]);
    }

    public function destroy(string $id)
    {
        $quote = Quote::findOrFail($id);
        if ($quote->status !== Quote::STATUS_DRAFT) {
            return back()->withErrors(['delete' => 'Only draft quotes can be deleted.']);
        }
        $quote->delete();
        return redirect()->route('quotes.index')->with('status', 'Draft deleted.');
    }

    // §11.2 status transitions
    public function markSent(string $id)
    {
        $quote = Quote::findOrFail($id);
        $quote->update([
            'status'  => Quote::STATUS_SENT,
            'sent_at' => now(),
        ]);
        return back()->with('status', 'Quote marked as sent.');
    }

    public function markWon(string $id)
    {
        $quote = Quote::findOrFail($id);
        $quote->update([
            'status' => Quote::STATUS_WON,
            'won_at' => now(),
        ]);
        return back()->with('status', 'Quote marked as won — you can now generate the order confirmation.');
    }

    public function markLost(string $id)
    {
        $quote = Quote::findOrFail($id);
        $quote->update([
            'status'  => Quote::STATUS_LOST,
            'lost_at' => now(),
        ]);
        return back()->with('status', 'Quote marked as lost.');
    }

    // §11.3 Duplicate
    public function duplicate(string $id)
    {
        $source = Quote::findOrFail($id);

        $companyId = auth()->user()->company_id;
        $number    = QuoteCounter::nextReference($companyId, 'quote', (int) date('Y'));

        $copy = $source->replicate([
            'sent_at', 'won_at', 'lost_at',
            'order_confirmation_number', 'order_confirmation_at',
        ]);
        $copy->number          = $number;
        $copy->status          = Quote::STATUS_DRAFT;
        $copy->duplicated_from = $source->number;
        $copy->save();

        return redirect()->route('quotes.edit', $copy->_id)
            ->with('status', 'Quote duplicated — now a draft.');
    }

    // §12 Quote PDF
    public function pdf(string $id)
    {
        $quote = Quote::findOrFail($id);
        $company = $quote->company;

        $pdf = Pdf::loadView('pdf.quote', compact('quote', 'company'))
            ->setPaper('a4');

        return $pdf->download($quote->number . '.pdf');
    }

    // §13 Order confirmation PDF (bon de commande)
    public function orderConfirmation(string $id)
    {
        $quote = Quote::findOrFail($id);
        if ($quote->status !== Quote::STATUS_WON) {
            return back()->withErrors(['order' => 'The quote must be marked Won first.']);
        }

        if (empty($quote->order_confirmation_number)) {
            $companyId = auth()->user()->company_id;
            $bcNumber  = QuoteCounter::nextReference($companyId, 'order', (int) date('Y'));
            $quote->update([
                'order_confirmation_number' => $bcNumber,
                'order_confirmation_at'     => now(),
            ]);
            $quote->refresh();
        }

        $company = $quote->company;

        $pdf = Pdf::loadView('pdf.order-confirmation', compact('quote', 'company'))
            ->setPaper('a4');

        return $pdf->download($quote->order_confirmation_number . '.pdf');
    }
}
