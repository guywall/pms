<?php

namespace App\Models;

use App\Casts\Money;
use App\Enums\StockType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockItem extends Model
{
    protected $fillable = [
        'sku', 'name', 'type', 'category', 'unit', 'qty_on_hand', 'qty_reserved',
        'reorder_level', 'reorder_qty', 'supplier_id', 'cost', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => StockType::class,
            'qty_on_hand' => 'integer',
            'qty_reserved' => 'integer',
            'reorder_level' => 'integer',
            'reorder_qty' => 'integer',
            'cost' => Money::class,
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(OrderStockAllocation::class);
    }

    public function getQtyAvailableAttribute(): int
    {
        return $this->qty_on_hand - $this->qty_reserved;
    }

    public function getIsLowStockAttribute(): bool
    {
        return $this->reorder_level > 0 && $this->qty_on_hand <= $this->reorder_level;
    }

    public function scopeLowStock($query)
    {
        return $query->where('reorder_level', '>', 0)->whereColumn('qty_on_hand', '<=', 'reorder_level');
    }
}
