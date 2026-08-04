<?php

namespace App\Models;

use App\Models\Concerns\BranchScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappContact extends Model
{
    use BranchScoped;

    protected $fillable = ['branch_id', 'whatsapp_integration_id', 'customer_id', 'phone', 'last_customer_message_at'];

    protected function casts(): array
    {
        return ['last_customer_message_at' => 'datetime'];
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(WhatsappIntegration::class, 'whatsapp_integration_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
