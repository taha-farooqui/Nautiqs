<?php

namespace App\Observers;

use App\Mail\BrandQuoteWon;
use App\Models\BrandAccount;
use App\Models\Quote;
use App\Models\User;
use App\Services\Brand\BrandQuotePresenter;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class QuoteObserver
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function created(Quote $quote): void
    {
        $this->notifications->record([
            'type'    => 'quote.created',
            'title'   => __('Quote created'),
            'message' => __(':number for :client (:amount)', ['number' => $quote->number, 'client' => $this->clientName($quote), 'amount' => $this->amount($quote)]),
            'icon'    => 'ri-file-add-line',
            'color'   => 'primary',
            'link'    => route('quotes.show', $quote->_id),
        ]);
    }

    public function updated(Quote $quote): void
    {
        // Only notify on meaningful status transitions, not every save (the
        // builder fires updates constantly while the user is editing).
        $original = $quote->getOriginal();
        $oldStatus = $original['status'] ?? null;
        $newStatus = $quote->status;

        if ($oldStatus === $newStatus) {
            return;
        }

        switch ($newStatus) {
            case Quote::STATUS_SENT:
                $this->notifications->record([
                    'type'    => 'quote.sent',
                    'title'   => __('Quote sent'),
                    'message' => __(':number was sent to :email', ['number' => $quote->number, 'email' => $quote->client_snapshot['email'] ?? __('the client')]),
                    'icon'    => 'ri-send-plane-line',
                    'color'   => 'primary',
                    'link'    => route('quotes.show', $quote->_id),
                ]);
                break;

            case Quote::STATUS_WON:
                $this->notifications->record([
                    'type'    => 'quote.won',
                    'title'   => __('Quote won'),
                    'message' => __(':number (:amount) was marked as Won', ['number' => $quote->number, 'amount' => $this->amount($quote)]),
                    'icon'    => 'ri-trophy-line',
                    'color'   => 'emerald',
                    'link'    => route('quotes.show', $quote->_id),
                ]);
                $this->emailBrands($quote);
                break;

            case Quote::STATUS_LOST:
                $this->notifications->record([
                    'type'    => 'quote.lost',
                    'title'   => __('Quote lost'),
                    'message' => __(':number for :client was marked as Lost', ['number' => $quote->number, 'client' => $this->clientName($quote)]),
                    'icon'    => 'ri-close-circle-line',
                    'color'   => 'red',
                    'link'    => route('quotes.show', $quote->_id),
                ]);
                break;
        }
    }

    public function deleted(Quote $quote): void
    {
        $this->notifications->record([
            'type'    => 'quote.deleted',
            'title'   => __('Quote deleted'),
            'message' => __(':number was deleted', ['number' => $quote->number]),
            'icon'    => 'ri-delete-bin-line',
            'color'   => 'gray',
            'link'    => null,
        ]);
    }

    /**
     * A won quote with partner-brand products: email each brand's managers the
     * brand-safe detail (BrandQuotePresenter — no client identity, no margins).
     * Never allowed to break marking the quote as won.
     */
    private function emailBrands(Quote $quote): void
    {
        foreach ((array) ($quote->brand_account_ids ?? []) as $accountId) {
            try {
                $account = BrandAccount::find($accountId);
                if (! $account || ! $account->is_active) continue;

                $managers = User::withoutGlobalScopes()->where('role', User::ROLE_BRAND_MANAGER)
                    ->where('brand_account_id', (string) $accountId)->where('is_active', '!=', false)->get();
                if ($managers->isEmpty()) continue;

                $detail = (new BrandQuotePresenter($account))->detail($quote);
                foreach ($managers as $m) {
                    Mail::to($m->email)->send(new BrandQuoteWon($account, $detail));
                }
            } catch (\Throwable $e) {
                Log::warning('Brand quote-won email failed', ['quote_id' => (string) $quote->_id, 'brand_account_id' => (string) $accountId, 'error' => $e->getMessage()]);
            }
        }
    }

    private function clientName(Quote $quote): string
    {
        $first = $quote->client_snapshot['first_name'] ?? '';
        $last  = $quote->client_snapshot['last_name']  ?? '';
        return trim($first . ' ' . $last) ?: __('a client');
    }

    private function amount(Quote $quote): string
    {
        return number_format($quote->totals['total_ttc'] ?? 0, 0, ',', ' ') . ' €';
    }
}
