<?php

namespace App\Models;

use App\Services\GuestSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class VisitorEntry extends Model
{
    public function scopeInProgress(Builder $query): Builder
    {
        return $query->whereIn('service_status', ['waiting', 'serving']);
    }

    protected $fillable = [
        'queue_no',
        'service_code',
        'queue_number',
        'full_name',
        'gender',
        'institution',
        'phone',
        'email',
        'occupation',
        'occupation_other',
        'purpose',
        'purpose_other',
        'dtsen_update',
        'created_at_is_utc',
        'service_status',
    ];

    public function getDisplayCreatedAtAttribute(): ?Carbon
    {
        $createdAt = $this->created_at?->copy();

        return $createdAt && $this->created_at_is_utc
            ? $createdAt->setTimezone('Asia/Jakarta')
            : $createdAt;
    }

    protected static function booted(): void
    {
        static::saved(function (self $entry): void {
            $id = $entry->getKey();

            DB::afterCommit(function () use ($id): void {
                $entry = self::find($id);
                if (! $entry) {
                    return;
                }

                try {
                    app(GuestSearch::class)->index($entry);
                } catch (Throwable $exception) {
                    Log::warning('Elasticsearch guest indexing failed.', [
                        'visitor_entry_id' => $id,
                        'exception' => $exception->getMessage(),
                    ]);
                }
            });
        });

        static::deleted(function (self $entry): void {
            $id = $entry->getKey();

            DB::afterCommit(function () use ($id): void {
                try {
                    app(GuestSearch::class)->delete($id);
                } catch (Throwable $exception) {
                    Log::warning('Elasticsearch guest removal failed.', [
                        'visitor_entry_id' => $id,
                        'exception' => $exception->getMessage(),
                    ]);
                }
            });
        });
    }

    protected $casts = [
        'dtsen_update' => 'boolean',
        'created_at_is_utc' => 'boolean',
    ];

    public function latestCall(): HasOne
    {
        return $this->hasOne(QueueCall::class)->latestOfMany('id');
    }
}
