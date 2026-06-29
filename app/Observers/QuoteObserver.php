<?php

namespace App\Observers;

use App\Models\Quote;
use App\Services\NotificationService;

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
