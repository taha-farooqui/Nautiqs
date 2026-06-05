<?php

namespace App\Mail;

use App\Models\Company;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Welcome email sent when the superadmin provisions a new dealer and
 * checks "Send account setup email" on /admin/dealers/create. Carries a
 * single-use account-setup link (a password-reset token) — NEVER a
 * readable password — so the dealer chooses their own password on first
 * use. Same visual style as TeamInvitation so the recipient gets a
 * consistent Nautiqs-branded mail.
 */
class DealerWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Company $company,
        public User $user,
        public string $setupUrl,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Welcome to Nautiqs — your :company account is ready', [
                'company' => $this->company->name,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.dealer-welcome',
            with: [
                'company'     => $this->company,
                'user'        => $this->user,
                'setupUrl'    => $this->setupUrl,
                'companyName' => $this->company->name,
            ],
        );
    }
}
