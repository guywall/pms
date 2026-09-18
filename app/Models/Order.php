<?php

namespace App\Models;

use App\Casts\Money;
use App\Enums\ArtworkStatus;
use App\Enums\JobType;
use App\Enums\StageKey;
use App\Models\Concerns\BelongsToCustomerTeam;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Order extends Model
{
    use BelongsToCustomerTeam, LogsActivity;

    protected $fillable = [
        'number', 'customer_id', 'type', 'stage', 'reference', 'due_date',
        'quantity', 'price', 'paid', 'notes', 'customer_notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => JobType::class,
            'stage' => StageKey::class,
            'due_date' => 'date',
            'price' => Money::class,
            'paid' => Money::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            if (! $order->uuid) {
                $order->uuid = (string) \Illuminate\Support\Str::uuid();
            }
            if (! $order->number) {
                $order->number = app(\App\Services\NumberGenerator::class)->nextOrderNumber();
            }
            if (! $order->stage) {
                $order->stage = StageKey::NewOrder;
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable();
    }

    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(OrderStockAllocation::class);
    }

    public function artworkVersions(): HasMany
    {
        return $this->hasMany(ArtworkVersion::class)->orderByDesc('version_number');
    }

    public function finalArtwork()
    {
        return $this->hasOne(ArtworkVersion::class)->where('status', ArtworkStatus::Approved)->where('is_final', true);
    }

    public function stageHistory(): HasMany
    {
        return $this->hasMany(OrderStageHistory::class)->orderBy('created_at');
    }

    public function currentStageRecord(): BelongsTo
    {
        return $this->belongsTo(ProductionStage::class, 'stage', 'key');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(OrderDocument::class);
    }

    public function getTotalAllocatedAttribute(): int
    {
        return (int) $this->allocations()->sum('qty_allocated');
    }

    public function getShortfallQtyAttribute(): int
    {
        return max(0, $this->quantity - $this->total_allocated);
    }

    public function scopeOpen($query): Builder
    {
        return $query->whereNotIn('stage', [StageKey::Complete, StageKey::Invoiced]);
    }
}
