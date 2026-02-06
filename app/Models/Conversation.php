<?php

namespace App\Models;

use App\Models\Concerns\BranchScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use BranchScoped, HasFactory;

    public const TYPE_DIRECT = 'direct';

    public const TYPE_GROUP = 'group';

    protected $fillable = [
        'branch_id',
        'type',
        'title',
        'direct_hash',
        'last_message_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Get the latest message in the conversation.
     */
    public function latestMessage()
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    /**
     * Check if this is a direct (1-on-1) conversation.
     */
    public function isDirect(): bool
    {
        return $this->type === self::TYPE_DIRECT;
    }

    /**
     * Check if this is a group conversation.
     */
    public function isGroup(): bool
    {
        return $this->type === self::TYPE_GROUP;
    }

    /**
     * Check if a user is a participant of this conversation.
     */
    public function hasParticipant(int $userId): bool
    {
        return $this->participants()->where('user_id', $userId)->exists();
    }

    /**
     * Get the display name for the conversation.
     * For direct conversations, shows the other user's name.
     * For group conversations, shows the title.
     */
    public function getDisplayNameFor(User $user): string
    {
        if ($this->isGroup()) {
            return $this->title ?? 'Group Chat';
        }

        // For direct conversations, get the other user's name
        $otherUser = $this->users()->where('users.id', '!=', $user->id)->first();

        return $otherUser?->name ?? 'Unknown User';
    }

    /**
     * Generate the direct hash for a pair of users.
     */
    public static function generateDirectHash(int $userId1, int $userId2, int $branchId): string
    {
        $minId = min($userId1, $userId2);
        $maxId = max($userId1, $userId2);

        return hash('sha256', "{$minId}:{$maxId}:{$branchId}");
    }

    /**
     * Get unread count for a specific user.
     */
    public function getUnreadCountFor(int $userId): int
    {
        $participant = $this->participants()->where('user_id', $userId)->first();

        if (! $participant) {
            return 0;
        }

        $query = $this->messages()->where('sender_id', '!=', $userId);

        if ($participant->last_read_at) {
            $query->where('created_at', '>', $participant->last_read_at);
        }

        return $query->count();
    }
}
