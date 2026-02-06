<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\User;

class MessagePolicy
{
    /**
     * Determine whether the user can view the message.
     * User must be a participant of the conversation.
     */
    public function view(User $user, Message $message): bool
    {
        if (! $user->can('messages.use')) {
            return false;
        }

        return $message->conversation->hasParticipant($user->id);
    }

    /**
     * Determine whether the user can delete the message.
     * Only sender can delete their own messages.
     */
    public function delete(User $user, Message $message): bool
    {
        return $user->id === $message->sender_id;
    }
}
