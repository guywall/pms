<?php

namespace App\Models;

use App\Casts\Money;
use App\Enums\PurchaseOrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    protected $fillable = [
        'number', 'supplier_id', 'status', 'order_id', 'expected_date', 'notes', 'created_by', 'sent_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PurchaseOrderStatus::class,
            'expected_date' => 'date',
            'sent_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PurchaseOrder $po) {
            if (! $po->number) {
                $po->number = app(\App\Services\NumberGenerator::class)->nextPurchaseOrderNumber();
            }
        });
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    public function getIsFullyReceivedAttribute(): bool
    {
        return $this->lines->every(fn (PurchaseOrderLine $line) => $line->qty_received >= $line->qty_ordered);
    }
}
