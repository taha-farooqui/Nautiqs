<?php

namespace App\Mail;

use App\Models\UserInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Invite email sent when an admin adds a new teammate. Brevo HTTPS API
 * is the active transport (configured in AppServiceProvider), so we
 * keep this lean: text-only header, single CTA, no inline images
 * (Brevo's API rejects cid attachments).
 */
class TeamInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public UserInvitation $invite)
    {
    }

    public function envelope(): Envelope
    {
        $companyName = optional(\App\Models\Company::find($this->invite->company_id))->name
            ?? config('app.name', 'Nautiqs');

        return new Envelope(
            subject: __("You've been invited to join :company on Nautiqs", ['company' => $companyName]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.team-invitation',
            with: [
                'invite'      => $this->invite,
                'acceptUrl'   => route('invitations.accept', $this->invite->token),
                'companyName' => optional(\App\Models\Company::find($this->invite->company_id))->name
                    ?? config('app.name', 'Nautiqs'),
            ],
        );
    }
}
