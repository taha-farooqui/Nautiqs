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
 * checks "Send credentials by email" on /admin/dealers/create. Carries
 * the plaintext password by necessity — the dealer needs it to log in
 * once. Same visual style as TeamInvitation so the recipient gets a
 * consistent Nautiqs-branded mail.
 */
class DealerWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Company $company,
        public User $user,
        public string $password,
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
                'password'    => $this->password,
                'loginUrl'    => route('login'),
                'companyName' => $this->company->name,
            ],
        );
    }
}
