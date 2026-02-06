<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;
use App\Support\BranchContext;

class ConversationPolicy
{
    /**
     * Determine whether the user can view any conversations.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('messages.use');
    }

    /**
     * Determine whether the user can view the conversation.
     * User must be a participant AND conversation must be in same branch.
     */
    public function view(User $user, Conversation $conversation): bool
    {
        if (! $user->can('messages.use')) {
            return false;
        }

        // Must be a participant
        if (! $conversation->hasParticipant($user->id)) {
            return false;
        }

        // Check branch access
        return $user->canAccessBranch($conversation->branch_id);
    }

    /**
     * Determine whether the user can create direct conversations.
     */
    public function createDirect(User $user): bool
    {
        return $user->can('messages.use');
    }

    /**
     * Determine whether the user can create group conversations.
     */
    public function createGroup(User $user): bool
    {
        return $user->can('messages.group.create');
    }

    /**
     * Determine whether the user can send messages in this conversation.
     * Must be a participant and same branch.
     */
    public function sendMessage(User $user, Conversation $conversation): bool
    {
        if (! $user->can('messages.use')) {
            return false;
        }

        // Must be a participant
        if (! $conversation->hasParticipant($user->id)) {
            return false;
        }

        // Check branch access
        return $user->canAccessBranch($conversation->branch_id);
    }

    /**
     * Determine whether the user can add participants to the conversation.
     * Only for group conversations and user must be a participant.
     */
    public function addParticipants(User $user, Conversation $conversation): bool
    {
        if (! $user->can('messages.group.create')) {
            return false;
        }

        // Only group conversations
        if (! $conversation->isGroup()) {
            return false;
        }

        // Must be a participant
        if (! $conversation->hasParticipant($user->id)) {
            return false;
        }

        // Check branch access
        return $user->canAccessBranch($conversation->branch_id);
    }

    /**
     * Determine whether the user can mark conversation as read.
     */
    public function markAsRead(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }
}
