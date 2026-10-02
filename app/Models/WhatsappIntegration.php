<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappIntegration extends Model
{
    protected $fillable = ['twilio_account_sid', 'twilio_auth_token', 'twilio_from', 'branch_id', 'webhook_key', 'enabled', 'waba_id', 'phone_number_id', 'meta_app_id', 'access_token', 'app_secret', 'webhook_verify_token', 'connection_status', 'webhook_status', 'webhook_checked_at', 'webhook_error_message', 'display_phone_number', 'verified_name', 'last_checked_at', 'last_connected_at', 'last_error_code', 'last_error_message'];

    protected $hidden = ['twilio_auth_token', 'access_token', 'app_secret', 'webhook_verify_token'];

    protected function casts(): array
    {
        return ['twilio_auth_token' => 'encrypted', 'enabled' => 'boolean', 'access_token' => 'encrypted', 'app_secret' => 'encrypted', 'webhook_verify_token' => 'encrypted', 'last_checked_at' => 'datetime', 'last_connected_at' => 'datetime', 'webhook_checked_at' => 'datetime'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public static function forBranch(int $branchId): self
    {
        return self::firstOrCreate(['branch_id' => $branchId], ['webhook_key' => (string) str()->uuid(), 'enabled' => false, 'connection_status' => 'not_configured']);
    }

    public function twilioConfigured(): bool
    {
        return filled($this->twilio_account_sid) && filled($this->twilio_auth_token) && filled($this->twilio_from);
    }

    public function isConfigured(): bool
    {
        if (config('twilio.active')) {
            return $this->twilioConfigured();
        }

        return filled($this->waba_id) && filled($this->phone_number_id) && filled($this->access_token);
    }

    public function healthStatus(): string
    {
        if (! $this->enabled) {
            return 'disabled';
        } if (! $this->isConfigured()) {
            return 'not_configured';
        }

        return $this->connection_status ?: 'unverified';
    }
}
