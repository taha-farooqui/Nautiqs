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
            'title'   => __('Client added'),
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
            'title'   => __('Client updated'),
            'message' => __(':name was updated', ['name' => $client->full_name]),
            'icon'    => 'ri-edit-line',
            'color'   => 'primary',
            'link'    => route('clients.show', $client->_id),
        ]);
    }

    public function deleted(Client $client): void
    {
        $this->notifications->record([
            'type'    => 'client.deleted',
            'title'   => __('Client deleted'),
            'message' => __(':name was deleted', ['name' => $client->full_name]),
            'icon'    => 'ri-delete-bin-line',
            'color'   => 'gray',
            'link'    => null,
        ]);
    }
}
