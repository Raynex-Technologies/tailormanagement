<?php

namespace App\Notifications;

use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class NewMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Message $message
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $conversation = $this->message->conversation;
        $sender = $this->message->sender;
        $snippet = Str::limit($this->message->body, 50);

        // Determine conversation display name
        $conversationName = $conversation->isGroup()
            ? $conversation->title
            : $sender->name;

        return [
            'title' => 'New Message',
            'body' => $conversation->isGroup()
                ? "{$sender->name} in {$conversationName}: {$snippet}"
                : "{$sender->name}: {$snippet}",
            'link' => route('messages.show', $conversation),
            'icon' => 'chat-bubble-left-right',
            'color' => 'blue',
            'conversation_id' => $conversation->id,
            'message_id' => $this->message->id,
            'sender_id' => $sender->id,
            'sender_name' => $sender->name,
        ];
    }
}
