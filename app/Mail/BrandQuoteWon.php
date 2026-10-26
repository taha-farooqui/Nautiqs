<?php

namespace App\Mail;

use App\Models\BrandAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells a brand's managers that a dealer won a quote with its products.
 * $q is BrandQuotePresenter::detail() — the brand-safe view of the quote:
 * no client identity, no purchase prices or margins, no internal notes.
 */
class BrandQuoteWon extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public BrandAccount $account, public array $q) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __(':dealer won a sale: :boat', ['dealer' => $this->q['dealer'], 'boat' => $this->q['boat']]),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.brand-quote-won', with: [
            'account' => $this->account, 'q' => $this->q, 'url' => route('brand.quotes.show', $this->q['id']),
        ]);
    }
}
