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
            'title'   => 'Quote created',
            'message' => $quote->number . ' for ' . $this->clientName($quote) . ' (' . $this->amount($quote) . ')',
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
                    'title'   => 'Quote sent',
                    'message' => $quote->number . ' was sent to ' . ($quote->client_snapshot['email'] ?? 'the client'),
                    'icon'    => 'ri-send-plane-line',
                    'color'   => 'primary',
                    'link'    => route('quotes.show', $quote->_id),
                ]);
                break;

            case Quote::STATUS_WON:
                $this->notifications->record([
                    'type'    => 'quote.won',
                    'title'   => 'Quote won',
                    'message' => $quote->number . ' (' . $this->amount($quote) . ') was marked as Won',
                    'icon'    => 'ri-trophy-line',
                    'color'   => 'emerald',
                    'link'    => route('quotes.show', $quote->_id),
                ]);
                break;

            case Quote::STATUS_LOST:
                $this->notifications->record([
                    'type'    => 'quote.lost',
                    'title'   => 'Quote lost',
                    'message' => $quote->number . ' for ' . $this->clientName($quote) . ' was marked as Lost',
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
            'title'   => 'Quote deleted',
            'message' => $quote->number . ' was deleted',
            'icon'    => 'ri-delete-bin-line',
            'color'   => 'gray',
            'link'    => null,
        ]);
    }

    private function clientName(Quote $quote): string
    {
        $first = $quote->client_snapshot['first_name'] ?? '';
        $last  = $quote->client_snapshot['last_name']  ?? '';
        return trim($first . ' ' . $last) ?: 'a client';
    }

    private function amount(Quote $quote): string
    {
        return '€' . number_format($quote->totals['total_ttc'] ?? 0, 0, ',', ' ');
    }
}
