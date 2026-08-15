<?php

namespace App\Models;

use App\Models\Concerns\BranchScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WhatsappMessage extends Model
{
    use BranchScoped;

    protected $fillable = ['branch_id', 'whatsapp_integration_id', 'whatsapp_contact_id', 'customer_id', 'external_message_id', 'direction', 'message_type', 'phone', 'body', 'status', 'failure_code', 'failure_reason', 'context_type', 'context_id', 'queued_at', 'submitted_at', 'accepted_at', 'sent_at', 'delivered_at', 'read_at', 'failed_at', 'meta_timestamp', 'safe_metadata'];

    protected function casts(): array
    {
        return ['queued_at' => 'datetime', 'submitted_at' => 'datetime', 'accepted_at' => 'datetime', 'sent_at' => 'datetime', 'delivered_at' => 'datetime', 'read_at' => 'datetime', 'failed_at' => 'datetime', 'meta_timestamp' => 'datetime', 'safe_metadata' => 'array'];
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(WhatsappIntegration::class, 'whatsapp_integration_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(WhatsappContact::class, 'whatsapp_contact_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function context(): MorphTo
    {
        return $this->morphTo();
    }

    public function smsLog(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(SmsLog::class);
    }
}
