<?php

namespace App\Mail;

use App\Models\BrandAccess;
use App\Models\BrandAccount;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A brand invites a dealer to receive its catalogue. Two calls to action:
 * review in the app when the email already has a dealer account, otherwise
 * request one — the invitation is waiting inside once it exists.
 */
class BrandDealerInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public BrandAccess $access, public BrandAccount $account, public array $brandNames) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __(':brand shares its catalogue with you on Nautiqs', ['brand' => $this->account->name]),
        );
    }

    public function content(): Content
    {
        $hasAccount = User::where('email', $this->access->email)->exists();

        return new Content(
            view: 'emails.brand-dealer-invitation',
            with: [
                'access'     => $this->access,
                'account'    => $this->account,
                'brandNames' => $this->brandNames,
                'levelName'  => $this->account->levelName($this->access->level),
                'hasAccount' => $hasAccount,
                'ctaUrl'     => $hasAccount ? route('partner-brands.show', $this->access->_id) : route('account-request.create'),
            ],
        );
    }
}
