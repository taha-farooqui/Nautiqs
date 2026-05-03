<?php

namespace App\Observers;

use App\Models\EmailTemplate;
use App\Services\NotificationService;

class EmailTemplateObserver
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function updated(EmailTemplate $template): void
    {
        // Don't fire on the initial lazy-create from defaults (the freshly
        // created row "updates" once before the user touches it).
        if (! $template->wasChanged(['subject', 'body'])) {
            return;
        }

        $this->notifications->record([
            'type'    => 'email_template.updated',
            'title'   => 'Email template saved',
            'message' => 'Your email template was updated',
            'icon'    => 'ri-mail-settings-line',
            'color'   => 'primary',
            'link'    => route('email-templates.edit', $template->type ?? 'quote'),
        ]);
    }
}
