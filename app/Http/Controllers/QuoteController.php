<?php

namespace App\Http\Controllers;

use App\Models\EmailLog;
use App\Models\Quote;
use App\Models\QuoteCounter;
use App\Services\EmailTemplateService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

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

        $brand = $request->query('brand');
        if ($brand) {
            $query->where('model_snapshot.brand', $brand);
        }

        $modelCode = $request->query('model');
        if ($modelCode) {
            $query->where('model_snapshot.code', $modelCode);
        }

        $month = $request->query('month'); // YYYY-MM
        if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
            $start = \Carbon\Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            $end   = $start->copy()->endOfMonth();
            $query->whereBetween('created_at', [$start, $end]);
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

        // Stats strip (top of page)
        $now           = \Carbon\Carbon::now();
        $startOfMonth  = $now->copy()->startOfMonth();
        $stats = [
            'this_month' => Quote::where('created_at', '>=', $startOfMonth)->count(),
            'awaiting'   => Quote::where('status', Quote::STATUS_SENT)->count(),
            'won_month'  => Quote::where('status', Quote::STATUS_WON)
                                ->where('won_at', '>=', $startOfMonth)
                                ->count(),
            'expiring'   => Quote::where('status', Quote::STATUS_SENT)
                                ->where('expires_at', '>=', $now)
                                ->where('expires_at', '<=', $now->copy()->addDays(3))
                                ->count(),
        ];

        // Filter dropdown options — derived from existing quote snapshots
        $allQuotesForFilters = Quote::get(['model_snapshot', 'created_at']);
        $brands = $allQuotesForFilters
            ->pluck('model_snapshot.brand')
            ->filter()
            ->unique()
            ->sort()
            ->values();
        $models = $allQuotesForFilters
            ->map(fn ($q) => [
                'code'  => $q->model_snapshot['code']  ?? null,
                'name'  => $q->model_snapshot['name']  ?? null,
                'brand' => $q->model_snapshot['brand'] ?? null,
            ])
            ->filter(fn ($m) => $m['code'])
            ->unique('code')
            ->sortBy('name')
            ->values();
        $months = $allQuotesForFilters
            ->map(fn ($q) => $q->created_at?->format('Y-m'))
            ->filter()
            ->unique()
            ->sortDesc()
            ->values();

        return view('quotes.index', compact(
            'quotes', 'status', 'q', 'counts', 'stats',
            'brands', 'models', 'months', 'brand', 'modelCode', 'month'
        ));
    }

    public function create(Request $request)
    {
        return view('quotes.create', [
            'preselectedClientId' => $request->query('client_id'),
        ]);
    }

    public function show(string $id, EmailTemplateService $templates)
    {
        $quote   = Quote::findOrFail($id);
        $company = $quote->company;

        // Guest quote = no linked client AND no email captured yet.
        $isGuest = empty($quote->client_id) && empty($quote->client_snapshot['email'] ?? null);

        // Decide which template the Send modal should pre-fill with. We
        // base "already sent" on the email log (actual sends), not just the
        // quote's status flag, so re-opening a quote that was marked Sent
        // without an actual email still shows the original quote template.
        //   - Won quote                      → order-confirmation template
        //   - quote-type email already sent  → follow-up template
        //   - otherwise                      → initial quote template
        $alreadySentQuoteEmail = EmailLog::where('quote_id', (string) $quote->_id)
            ->where('type', EmailLog::TYPE_QUOTE)
            ->where('status', EmailLog::STATUS_SENT)
            ->exists();

        $sendType = match (true) {
            $quote->status === Quote::STATUS_WON => EmailTemplateService::TYPE_ORDER_CONFIRMATION,
            $alreadySentQuoteEmail               => EmailTemplateService::TYPE_FOLLOW_UP,
            default                              => EmailTemplateService::TYPE_QUOTE,
        };

        $rendered = ['subject' => '', 'body' => ''];
        if ($company) {
            $template = $templates->getOrCreate($company, $sendType);
            $rendered = $templates->render($template, $company, $quote);
        }

        // Timeline data — first successful send per type. Drives the
        // lifecycle ribbon at the top of the quote page.
        $firstQuoteEmailAt = EmailLog::where('quote_id', (string) $quote->_id)
            ->where('type', EmailLog::TYPE_QUOTE)
            ->where('status', EmailLog::STATUS_SENT)
            ->orderBy('sent_at', 'asc')
            ->value('sent_at');

        $firstOrderEmailAt = EmailLog::where('quote_id', (string) $quote->_id)
            ->where('type', EmailLog::TYPE_ORDER_CONFIRMATION)
            ->where('status', EmailLog::STATUS_SENT)
            ->orderBy('sent_at', 'asc')
            ->value('sent_at');

        return view('quotes.show', [
            'quote'              => $quote,
            'emailSubject'       => $rendered['subject'],
            'emailBodyHtml'      => $rendered['body'],
            'isGuest'            => $isGuest,
            'sendType'           => $sendType,
            'firstQuoteEmailAt'  => $firstQuoteEmailAt,
            'firstOrderEmailAt'  => $firstOrderEmailAt,
        ]);
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
    public function pdf(string $id, Request $request)
    {
        $quote = Quote::findOrFail($id);
        $company = $quote->company;

        $pdf = Pdf::loadView('pdf.quote', compact('quote', 'company'))
            ->setPaper('a4');

        $filename = $quote->number . '.pdf';

        // ?inline=1 → stream into an <iframe> for the preview modal
        if ($request->boolean('inline')) {
            return $pdf->stream($filename);
        }

        return $pdf->download($filename);
    }

    // Send the quote PDF to the client via Postmark
    public function sendEmail(string $id, Request $request, EmailTemplateService $templates)
    {
        $quote   = Quote::findOrFail($id);
        $company = $quote->company;

        // For guest quotes the snapshot is empty until first send. Require
        // first/last/email here so the PDF and email both have valid recipient
        // info, and persist them onto the snapshot for next time.
        $isGuest = empty($quote->client_id) && empty($quote->client_snapshot['email'] ?? null);

        $rules = [
            'email'      => ['required', 'email'],
            'subject'    => ['nullable', 'string', 'max:300'],
            'message'    => ['nullable', 'string', 'max:50000'],
            'first_name' => [$isGuest ? 'required' : 'nullable', 'string', 'max:100'],
            'last_name'  => [$isGuest ? 'required' : 'nullable', 'string', 'max:100'],
        ];

        $request->validate($rules, [
            'email.required'      => 'Recipient email is required.',
            'first_name.required' => "Please enter the recipient's first name.",
            'last_name.required'  => "Please enter the recipient's last name.",
        ]);

        $to = $request->input('email');

        // Persist guest details into the quote's snapshot before rendering the
        // PDF so the recipient's name appears on the document.
        if ($isGuest) {
            $snapshot = $quote->client_snapshot ?? [];
            $snapshot['first_name'] = $request->input('first_name');
            $snapshot['last_name']  = $request->input('last_name');
            $snapshot['email']      = $to;
            $snapshot['is_guest']   = true;
            $quote->client_snapshot = $snapshot;
            $quote->save();
            $quote->refresh();
        }

        // Decide which template + PDF go out, based on where the quote sits
        // in its lifecycle and whether the dealer has already sent it once.
        $alreadySentQuoteEmail = EmailLog::where('quote_id', (string) $quote->_id)
            ->where('type', EmailLog::TYPE_QUOTE)
            ->where('status', EmailLog::STATUS_SENT)
            ->exists();

        $type = match (true) {
            $quote->status === Quote::STATUS_WON => EmailLog::TYPE_ORDER_CONFIRMATION,
            $alreadySentQuoteEmail               => EmailLog::TYPE_FOLLOW_UP,
            default                              => EmailLog::TYPE_QUOTE,
        };

        $template = $templates->getOrCreate($company, $type);
        $rendered = $templates->render($template, $company, $quote);

        // Caller can override either field on a per-send basis.
        $subject  = $request->filled('subject') ? $request->input('subject') : $rendered['subject'];
        $bodyHtml = $request->filled('message') ? $request->input('message') : $rendered['body'];

        // Order-confirmation emails attach the BC PDF; everything else
        // attaches the quote PDF.
        if ($type === EmailLog::TYPE_ORDER_CONFIRMATION && ! empty($quote->order_confirmation_number)) {
            $pdf = Pdf::loadView('pdf.order-confirmation', compact('quote', 'company'))->setPaper('a4');
            $attachmentFilename = $quote->order_confirmation_number . '.pdf';
        } else {
            $pdf = Pdf::loadView('pdf.quote', compact('quote', 'company'))->setPaper('a4');
            $attachmentFilename = $quote->number . '.pdf';
        }
        $pdfBytes = $pdf->output();

        $sendError = null;
        try {
            \Illuminate\Support\Facades\Mail::html($bodyHtml, function ($msg) use ($to, $subject, $pdfBytes, $attachmentFilename, $company) {
                $msg->to($to)
                    ->subject($subject)
                    ->attachData($pdfBytes, $attachmentFilename, ['mime' => 'application/pdf']);

                if ($company->salesperson_email) {
                    $msg->replyTo($company->salesperson_email, $company->salesperson_name);
                }
            });
        } catch (\Throwable $e) {
            $sendError = $e->getMessage();
        }

        // Audit row — written even on failure so the dealer can see what
        // happened and retry. Body is stored verbatim (post-substitution)
        // so "what did the client see?" is always answerable.
        $user = auth()->user();
        EmailLog::create([
            'company_id'          => $user->company_id,
            'quote_id'            => (string) $quote->_id,
            'quote_number'        => $quote->number,
            'type'                => $type,
            'to_email'            => $to,
            'to_name'             => trim(($request->input('first_name') ?? ($quote->client_snapshot['first_name'] ?? '')) . ' ' .
                                          ($request->input('last_name')  ?? ($quote->client_snapshot['last_name']  ?? ''))) ?: null,
            'reply_to_email'      => $company->salesperson_email,
            'subject'             => $subject,
            'body_html'           => $bodyHtml,
            'attachment_filename' => $attachmentFilename,
            'status'              => $sendError ? EmailLog::STATUS_FAILED : EmailLog::STATUS_SENT,
            'error_message'       => $sendError,
            'sent_by_user_id'     => (string) $user->_id,
            'sent_by_user_name'   => $user->name,
            'sent_at'             => now(),
        ]);

        if ($sendError) {
            return back()->withErrors(['email' => "Sending failed: {$sendError}"]);
        }

        // First successful send moves a draft into Sent state.
        if ($quote->status === Quote::STATUS_DRAFT) {
            $quote->update([
                'status'  => Quote::STATUS_SENT,
                'sent_at' => now(),
            ]);
        }

        $verb = match ($type) {
            EmailLog::TYPE_ORDER_CONFIRMATION => 'Order confirmation',
            EmailLog::TYPE_FOLLOW_UP          => 'Follow-up',
            default                           => 'Quote',
        };
        return back()->with('status', "{$verb} sent to {$to}.");
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
