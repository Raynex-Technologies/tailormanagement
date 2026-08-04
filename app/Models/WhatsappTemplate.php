<?php

namespace App\Models;

use App\Models\Concerns\BranchScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsappTemplate extends Model
{
    use BranchScoped;

    protected $fillable = ['branch_id', 'whatsapp_integration_id', 'meta_template_id', 'name', 'language', 'category', 'local_state', 'meta_status', 'meta_quality', 'components', 'variable_mappings', 'validation_result', 'validation_fingerprint', 'submission_fingerprint', 'synced_fingerprint', 'rejection_reason', 'meta_status_details', 'submitted_at', 'approved_at', 'rejected_at', 'last_synced_at', 'deleted_at_meta'];

    protected function casts(): array
    {
        return ['components' => 'array', 'variable_mappings' => 'array', 'validation_result' => 'array', 'meta_status_details' => 'array', 'submitted_at' => 'datetime', 'approved_at' => 'datetime', 'rejected_at' => 'datetime', 'last_synced_at' => 'datetime', 'deleted_at_meta' => 'datetime'];
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(WhatsappIntegration::class, 'whatsapp_integration_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(WhatsappTemplateStatusHistory::class);
    }

    public function isSendable(): bool
    {
        return strtoupper((string) $this->meta_status) === 'APPROVED' && $this->deleted_at_meta === null;
    }

    public function hasLocalDivergence(): bool
    {
        return $this->submission_fingerprint !== null && $this->submission_fingerprint !== $this->definitionFingerprint();
    }

    public function definitionFingerprint(): string
    {
        return hash('sha256', json_encode(['name' => $this->name, 'language' => $this->language, 'category' => $this->category, 'components' => $this->components, 'variable_mappings' => $this->variable_mappings], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
