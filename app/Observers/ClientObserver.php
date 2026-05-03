<?php

namespace App\Observers;

use App\Models\Client;
use App\Services\NotificationService;

class ClientObserver
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function created(Client $client): void
    {
        $this->notifications->record([
            'type'    => 'client.created',
            'title'   => 'Client added',
            'message' => $client->full_name . ($client->email ? ' (' . $client->email . ')' : ''),
            'icon'    => 'ri-user-add-line',
            'color'   => 'primary',
            'link'    => route('clients.show', $client->_id),
        ]);
    }

    public function updated(Client $client): void
    {
        // Skip the notification if only metadata fields changed silently.
        if ($client->wasChanged(['updated_at']) && count($client->getChanges()) === 1) {
            return;
        }

        $this->notifications->record([
            'type'    => 'client.updated',
            'title'   => 'Client updated',
            'message' => $client->full_name . ' was updated',
            'icon'    => 'ri-edit-line',
            'color'   => 'primary',
            'link'    => route('clients.show', $client->_id),
        ]);
    }

    public function deleted(Client $client): void
    {
        $this->notifications->record([
            'type'    => 'client.deleted',
            'title'   => 'Client deleted',
            'message' => $client->full_name . ' was deleted',
            'icon'    => 'ri-delete-bin-line',
            'color'   => 'gray',
            'link'    => null,
        ]);
    }
}
