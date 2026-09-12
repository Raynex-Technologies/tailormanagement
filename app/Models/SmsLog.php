<?php

namespace App\Models;

use App\Enums\SmsStatus;
use App\Models\Concerns\BranchScoped;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder as QueryBuilder;

class SmsLog extends Model
{
    use BranchScoped, HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_id',
        'provider',
        'template_code',
        'to',
        'message',
        'status',
        'skip_reason',
        'provider_message_id',
        'whatsapp_message_id',
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

    public function whatsappMessage(): BelongsTo
    {
        return $this->belongsTo(WhatsappMessage::class);
    }

    public function scopeResolvedFailedRetries(Builder $query): Builder
    {
        return $query
            ->where('status', SmsStatus::Failed->value)
            ->whereExists(function (QueryBuilder $subquery) {
                $subquery
                    ->selectRaw('1')
                    ->from('sms_logs as successful_retries')
                    ->where('successful_retries.status', SmsStatus::Sent->value)
                    ->whereColumn('successful_retries.created_at', '>', 'sms_logs.created_at')
                    ->whereColumn('successful_retries.provider', 'sms_logs.provider')
                    ->whereColumn('successful_retries.to', 'sms_logs.to')
                    ->whereColumn('successful_retries.message', 'sms_logs.message');

                $this->whereNullableColumnsMatch($subquery, 'successful_retries.branch_id', 'sms_logs.branch_id');
                $this->whereNullableColumnsMatch($subquery, 'successful_retries.template_code', 'sms_logs.template_code');
                $this->whereNullableColumnsMatch($subquery, 'successful_retries.reference_type', 'sms_logs.reference_type');
                $this->whereNullableColumnsMatch($subquery, 'successful_retries.reference_id', 'sms_logs.reference_id');
            });
    }

    public function scopeUnresolvedFailedRetries(Builder $query): Builder
    {
        return $query
            ->where('status', SmsStatus::Failed->value)
            ->whereNotExists(function (QueryBuilder $subquery) {
                $subquery
                    ->selectRaw('1')
                    ->from('sms_logs as successful_retries')
                    ->where('successful_retries.status', SmsStatus::Sent->value)
                    ->whereColumn('successful_retries.created_at', '>', 'sms_logs.created_at')
                    ->whereColumn('successful_retries.provider', 'sms_logs.provider')
                    ->whereColumn('successful_retries.to', 'sms_logs.to')
                    ->whereColumn('successful_retries.message', 'sms_logs.message');

                $this->whereNullableColumnsMatch($subquery, 'successful_retries.branch_id', 'sms_logs.branch_id');
                $this->whereNullableColumnsMatch($subquery, 'successful_retries.template_code', 'sms_logs.template_code');
                $this->whereNullableColumnsMatch($subquery, 'successful_retries.reference_type', 'sms_logs.reference_type');
                $this->whereNullableColumnsMatch($subquery, 'successful_retries.reference_id', 'sms_logs.reference_id');
            });
    }

    public function scopeWithoutResolvedFailedRetries(Builder $query): Builder
    {
        return $query->where(function (Builder $query) {
            $query
                ->where('status', '!=', SmsStatus::Failed->value)
                ->orWhere(fn (Builder $failedQuery) => $failedQuery->unresolvedFailedRetries());
        });
    }

    protected function whereNullableColumnsMatch(QueryBuilder $query, string $leftColumn, string $rightColumn): void
    {
        $query->where(function (QueryBuilder $query) use ($leftColumn, $rightColumn) {
            $query
                ->whereColumn($leftColumn, $rightColumn)
                ->orWhere(function (QueryBuilder $query) use ($leftColumn, $rightColumn) {
                    $query
                        ->whereNull($leftColumn)
                        ->whereNull($rightColumn);
                });
        });
    }
}
