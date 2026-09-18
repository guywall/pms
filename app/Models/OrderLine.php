<?php

namespace App\Models;

use App\Enums\LineType;
use App\Models\Concerns\BelongsToCustomerTeam;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderLine extends Model
{
    use BelongsToCustomerTeam;

    protected $fillable = [
        'order_id', 'stock_item_id', 'description', 'type',
        'quantity', 'price', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => LineType::class,
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(OrderStockAllocation::class);
    }
}
