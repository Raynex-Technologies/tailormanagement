<?php

namespace App\Livewire\Notifications;

use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

class NotificationBell extends Component
{
    public bool $isOpen = false;

    /**
     * Get unread notifications count for the current user.
     */
    #[Computed]
    public function unreadCount(): int
    {
        return auth()->user()?->unreadNotifications()->count() ?? 0;
    }

    /**
     * Get latest 8 notifications for the current user.
     */
    #[Computed]
    public function notifications(): Collection
    {
        return auth()->user()?->notifications()->latest()->take(8)->get() ?? collect();
    }

    /**
     * Toggle the notification dropdown.
     */
    public function toggle(): void
    {
        $this->isOpen = ! $this->isOpen;
    }

    /**
     * Close the notification dropdown.
     */
    public function close(): void
    {
        $this->isOpen = false;
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(string $notificationId): void
    {
        $notification = auth()->user()?->notifications()->find($notificationId);

        if ($notification) {
            $notification->markAsRead();
        }

        unset($this->unreadCount, $this->notifications);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(): void
    {
        auth()->user()?->unreadNotifications->markAsRead();
        unset($this->unreadCount, $this->notifications);
    }

    public function render()
    {
        return view('livewire.notifications.notification-bell');
    }
}
