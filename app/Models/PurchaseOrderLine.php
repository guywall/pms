<?php

namespace App\Models;

use App\Casts\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderLine extends Model
{
    protected $fillable = [
        'purchase_order_id', 'stock_item_id', 'description', 'qty_ordered', 'qty_received', 'unit_cost',
    ];

    protected function casts(): array
    {
        return ['qty_ordered' => 'integer', 'qty_received' => 'integer', 'unit_cost' => Money::class];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }
}
