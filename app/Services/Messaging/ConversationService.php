<?php

namespace App\Services\Messaging;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewMessageNotification;
use App\Support\BranchContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class ConversationService
{
    /**
     * Get or create a direct (1-on-1) conversation between two users.
     */
    public function getOrCreateDirect(User $actor, int $otherUserId): Conversation
    {
        $branchId = BranchContext::id();

        // Ensure the other user exists and is in the same branch
        $otherUser = User::where('id', $otherUserId)
            ->where('branch_id', $branchId)
            ->first();

        if (! $otherUser) {
            throw ValidationException::withMessages([
                'user_id' => ['The selected user does not exist or is not in your branch.'],
            ]);
        }

        // Cannot start conversation with yourself
        if ($actor->id === $otherUserId) {
            throw ValidationException::withMessages([
                'user_id' => ['You cannot start a conversation with yourself.'],
            ]);
        }

        // Generate the direct hash
        $directHash = Conversation::generateDirectHash($actor->id, $otherUserId, $branchId);

        // Try to find existing conversation
        $conversation = Conversation::where('direct_hash', $directHash)->first();

        if ($conversation) {
            return $conversation;
        }

        // Create new direct conversation
        return DB::transaction(function () use ($actor, $otherUserId, $branchId, $directHash) {
            $conversation = Conversation::create([
                'branch_id' => $branchId,
                'type' => Conversation::TYPE_DIRECT,
                'title' => null,
                'direct_hash' => $directHash,
                'created_by' => $actor->id,
            ]);

            // Add both participants
            ConversationParticipant::create([
                'conversation_id' => $conversation->id,
                'user_id' => $actor->id,
            ]);

            ConversationParticipant::create([
                'conversation_id' => $conversation->id,
                'user_id' => $otherUserId,
            ]);

            return $conversation;
        });
    }

    /**
     * Create a group conversation.
     */
    public function createGroup(User $actor, string $title, array $userIds): Conversation
    {
        $branchId = BranchContext::id();

        // Validate title
        $title = trim($title);
        if (empty($title) || strlen($title) > 100) {
            throw ValidationException::withMessages([
                'title' => ['Group title is required and must be 100 characters or less.'],
            ]);
        }

        // Validate at least one other user
        if (empty($userIds)) {
            throw ValidationException::withMessages([
                'user_ids' => ['Please select at least one participant.'],
            ]);
        }

        // Validate all users are in the same branch
        $validUserIds = User::whereIn('id', $userIds)
            ->where('branch_id', $branchId)
            ->pluck('id')
            ->toArray();

        $invalidUserIds = array_diff($userIds, $validUserIds);
        if (! empty($invalidUserIds)) {
            throw ValidationException::withMessages([
                'user_ids' => ['Some selected users are not in your branch.'],
            ]);
        }

        return DB::transaction(function () use ($actor, $title, $validUserIds, $branchId) {
            $conversation = Conversation::create([
                'branch_id' => $branchId,
                'type' => Conversation::TYPE_GROUP,
                'title' => $title,
                'direct_hash' => null,
                'created_by' => $actor->id,
            ]);

            // Add the creator as a participant
            ConversationParticipant::create([
                'conversation_id' => $conversation->id,
                'user_id' => $actor->id,
            ]);

            // Add other participants
            foreach ($validUserIds as $userId) {
                if ($userId !== $actor->id) {
                    ConversationParticipant::create([
                        'conversation_id' => $conversation->id,
                        'user_id' => $userId,
                    ]);
                }
            }

            return $conversation;
        });
    }

    /**
     * Add participants to a group conversation.
     */
    public function addParticipants(Conversation $conversation, array $userIds, User $actor): void
    {
        if (! $conversation->isGroup()) {
            throw ValidationException::withMessages([
                'conversation' => ['Cannot add participants to a direct conversation.'],
            ]);
        }

        $branchId = $conversation->branch_id;

        // Validate all users are in the same branch
        $validUserIds = User::whereIn('id', $userIds)
            ->where('branch_id', $branchId)
            ->pluck('id')
            ->toArray();

        $invalidUserIds = array_diff($userIds, $validUserIds);
        if (! empty($invalidUserIds)) {
            throw ValidationException::withMessages([
                'user_ids' => ['Some selected users are not in this branch.'],
            ]);
        }

        // Get existing participant IDs
        $existingParticipantIds = $conversation->participants()
            ->pluck('user_id')
            ->toArray();

        // Filter out users already in the conversation
        $newUserIds = array_diff($validUserIds, $existingParticipantIds);

        if (empty($newUserIds)) {
            return;
        }

        DB::transaction(function () use ($conversation, $newUserIds) {
            foreach ($newUserIds as $userId) {
                ConversationParticipant::create([
                    'conversation_id' => $conversation->id,
                    'user_id' => $userId,
                ]);
            }
        });
    }

    /**
     * Send a message in a conversation.
     */
    public function sendMessage(Conversation $conversation, User $actor, string $body): Message
    {
        // Validate body
        $body = trim($body);
        if (empty($body)) {
            throw ValidationException::withMessages([
                'body' => ['Message cannot be empty.'],
            ]);
        }

        if (strlen($body) > 2000) {
            throw ValidationException::withMessages([
                'body' => ['Message cannot exceed 2000 characters.'],
            ]);
        }

        // Verify user is a participant
        if (! $conversation->hasParticipant($actor->id)) {
            throw ValidationException::withMessages([
                'conversation' => ['You are not a participant of this conversation.'],
            ]);
        }

        return DB::transaction(function () use ($conversation, $actor, $body) {
            // Create the message
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $actor->id,
                'body' => $body,
            ]);

            // Update conversation's last_message_at
            $conversation->update(['last_message_at' => now()]);

            // Update sender's last_read_at (they've read their own message)
            $conversation->participants()
                ->where('user_id', $actor->id)
                ->update(['last_read_at' => now()]);

            // Notify other participants
            $this->notifyRecipients($conversation, $message, $actor);

            return $message;
        });
    }

    /**
     * Mark a conversation as read for a user.
     */
    public function markAsRead(Conversation $conversation, User $user): void
    {
        $conversation->participants()
            ->where('user_id', $user->id)
            ->update(['last_read_at' => now()]);
    }

    /**
     * Get the unread conversations count for a user.
     */
    public function getUnreadConversationsCount(User $user): int
    {
        $branchId = BranchContext::id();

        return ConversationParticipant::where('user_id', $user->id)
            ->whereHas('conversation', function ($query) use ($branchId) {
                $query->where('branch_id', $branchId)
                    ->whereNotNull('last_message_at');
            })
            ->where(function ($query) {
                $query->whereNull('last_read_at')
                    ->orWhereRaw('last_read_at < (SELECT last_message_at FROM conversations WHERE conversations.id = conversation_participants.conversation_id)');
            })
            ->count();
    }

    /**
     * Get conversations for a user with unread counts.
     */
    public function getConversationsForUser(User $user, ?string $search = null, int $perPage = 15)
    {
        $branchId = BranchContext::id();

        $query = Conversation::where('branch_id', $branchId)
            ->whereHas('participants', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->with(['latestMessage.sender', 'users'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at');

        if ($search) {
            $query->where(function ($q) use ($search) {
                // Search in group titles
                $q->where('title', 'like', "%{$search}%")
                    // Search in participant names (for direct conversations)
                    ->orWhereHas('users', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Notify recipients of a new message.
     */
    protected function notifyRecipients(Conversation $conversation, Message $message, User $sender): void
    {
        // Get all participants except the sender
        $recipients = $conversation->users()
            ->where('users.id', '!=', $sender->id)
            ->get();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new NewMessageNotification($message));
        }
    }
}
