<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'branch_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    // ============================================
    // Branch & Role Helpers
    // ============================================

    /**
     * Get the branch this user belongs to.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Check if user is a global admin (admin or superadmin).
     * Global admins can view/manage all branches.
     */
    public function isGlobalAdmin(): bool
    {
        return $this->hasAnyRole(['admin', 'superadmin']);
    }

    /**
     * Check if user is a branch manager.
     * Branch managers have admin-like powers within their branch only.
     */
    public function isBranchManager(): bool
    {
        return $this->hasRole('branch_manager');
    }

    /**
     * Check if user has branch-level admin powers (branch_manager or global admin).
     */
    public function hasBranchAdminPowers(): bool
    {
        return $this->isGlobalAdmin() || $this->isBranchManager();
    }

    /**
     * Check if user requires a branch assignment.
     * Returns false for global admins who can operate without a branch.
     */
    public function requiresBranch(): bool
    {
        return ! $this->isGlobalAdmin();
    }

    /**
     * Check if user can access a specific branch's data.
     */
    public function canAccessBranch(int $branchId): bool
    {
        // Global admins can access any branch
        if ($this->isGlobalAdmin()) {
            return true;
        }

        // Others can only access their own branch
        return $this->branch_id === $branchId;
    }

    /**
     * Check if user can manage users for a specific branch.
     */
    public function canManageUsersForBranch(?int $branchId): bool
    {
        // Global admins can manage users for any branch
        if ($this->isGlobalAdmin()) {
            return true;
        }

        // Branch managers can only manage users in their branch
        if ($this->isBranchManager()) {
            return $branchId !== null && $this->branch_id === $branchId;
        }

        return false;
    }

    // ============================================
    // Orders
    // ============================================

    /**
     * Orders assigned to this user (as tailor).
     */
    public function assignedOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'assigned_tailor_id');
    }

    /**
     * Orders created by this user.
     */
    public function createdOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'created_by');
    }

    /**
     * Order watchers for this user.
     */
    public function orderWatches(): HasMany
    {
        return $this->hasMany(OrderWatcher::class);
    }

    /**
     * Order comments by this user.
     */
    public function orderComments(): HasMany
    {
        return $this->hasMany(OrderComment::class);
    }

    /**
     * Payments received by this user.
     */
    public function receivedPayments(): HasMany
    {
        return $this->hasMany(OrderPayment::class, 'received_by');
    }

    // ============================================
    // Inventory
    // ============================================

    /**
     * Inventory transactions created by this user.
     */
    public function inventoryTransactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class, 'created_by');
    }

    /**
     * Stock requests made by this user.
     */
    public function stockRequests(): HasMany
    {
        return $this->hasMany(OrderStockRequest::class, 'requested_by');
    }

    /**
     * Stock requests handled by this user.
     */
    public function handledStockRequests(): HasMany
    {
        return $this->hasMany(OrderStockRequest::class, 'handled_by');
    }

    // ============================================
    // Procurement
    // ============================================

    /**
     * Purchase requests made by this user.
     */
    public function purchaseRequests(): HasMany
    {
        return $this->hasMany(PurchaseRequest::class, 'requested_by');
    }

    /**
     * Purchase requests reviewed by this user.
     */
    public function reviewedPurchaseRequests(): HasMany
    {
        return $this->hasMany(PurchaseRequest::class, 'reviewed_by');
    }

    /**
     * Purchase orders created by this user.
     */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'created_by');
    }

    /**
     * Goods receipts received by this user.
     */
    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class, 'received_by');
    }

    // ============================================
    // Finance
    // ============================================

    /**
     * Capital allocations where user is the accountant.
     */
    public function capitalAllocations(): HasMany
    {
        return $this->hasMany(CapitalAllocation::class, 'accountant_id');
    }

    /**
     * Capital transactions created by this user.
     */
    public function capitalTransactions(): HasMany
    {
        return $this->hasMany(CapitalTransaction::class, 'created_by');
    }

    /**
     * Expenses created by this user.
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'created_by');
    }

    // ============================================
    // Messaging
    // ============================================

    /**
     * Conversations created by this user.
     */
    public function createdConversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'created_by');
    }

    /**
     * Conversations the user participates in.
     */
    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_participants')
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    /**
     * Messages sent by this user.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    // ============================================
    // Other
    // ============================================

    /**
     * Todos belonging to this user.
     */
    public function todos(): HasMany
    {
        return $this->hasMany(Todo::class);
    }

    /**
     * SMS logs created by this user.
     */
    public function smsLogs(): HasMany
    {
        return $this->hasMany(SmsLog::class, 'created_by');
    }

    /**
     * Delivery notes delivered by this user.
     */
    public function deliveryNotes(): HasMany
    {
        return $this->hasMany(DeliveryNote::class, 'delivered_by');
    }

    // ============================================
    // Scopes
    // ============================================

    /**
     * Scope to filter users by branch.
     */
    public function scopeForBranch($query, int $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    /**
     * Scope to get only users with a branch assignment.
     */
    public function scopeWithBranch($query)
    {
        return $query->whereNotNull('branch_id');
    }

    /**
     * Scope to get only global users (no branch).
     */
    public function scopeGlobal($query)
    {
        return $query->whereNull('branch_id');
    }
}
