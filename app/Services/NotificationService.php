<?php

namespace App\Services;

use App\Models\Notification;

/**
 * Tiny service so observers don't have to talk to the model directly.
 * Handles the no-auth case (e.g. seeders, console commands) gracefully.
 */
class NotificationService
{
    /**
     * Record a notification for the currently authenticated user.
     * No-ops when there's no logged-in user (seeders, jobs, etc.).
     */
    public function record(array $data): ?Notification
    {
        $user = auth()->user();
        if (! $user) return null;

        return Notification::create(array_merge([
            'user_id'    => (string) $user->_id,
            'company_id' => $user->company_id,
            'icon'       => 'ri-notification-3-line',
            'color'      => 'primary',
            'link'       => null,
            'read_at'    => null,
        ], $data));
    }

    public function unreadCount(string $userId): int
    {
        return Notification::where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
    }

    public function recentForUser(string $userId, int $limit = 10)
    {
        return Notification::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function markAllRead(string $userId): int
    {
        return Notification::where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
