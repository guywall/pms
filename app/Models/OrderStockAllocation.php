<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderStockAllocation extends Model
{
    protected $fillable = [
        'order_id', 'stock_item_id', 'order_line_id', 'qty_allocated', 'qty_issued', 'status', 'purchase_order_id',
    ];

    protected function casts(): array
    {
        return ['qty_allocated' => 'integer', 'qty_issued' => 'integer'];
    }

    public const STATUS_RESERVED = 'reserved';
    public const STATUS_ISSUED = 'issued';
    public const STATUS_RELEASED = 'released';
    public const STATUS_PARTIALLY_RECEIVED = 'partially_received';

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }

    public function orderLine(): BelongsTo
    {
        return $this->belongsTo(OrderLine::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}
