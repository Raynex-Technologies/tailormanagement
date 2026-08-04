<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappWebhookEvent extends Model
{
    protected $fillable = ['whatsapp_integration_id', 'event_key', 'event_type', 'payload', 'accepted_at', 'processed_at', 'processing_error'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'accepted_at' => 'datetime', 'processed_at' => 'datetime'];
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(WhatsappIntegration::class, 'whatsapp_integration_id');
    }
}
