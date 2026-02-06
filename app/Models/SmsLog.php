<?php

namespace App\Models;

use App\Enums\SmsStatus;
use App\Models\Concerns\BranchScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SmsLog extends Model
{
    use BranchScoped, HasFactory;

    protected $fillable = [
        'branch_id',
        'provider',
        'to',
        'message',
        'status',
        'provider_message_id',
        'provider_response',
        'reference_type',
        'reference_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => SmsStatus::class,
        ];
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
