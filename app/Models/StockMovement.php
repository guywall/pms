<?php

namespace App\Models;

use App\Enums\MovementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockMovement extends Model
{
    protected $fillable = [
        'stock_item_id', 'type', 'qty', 'qty_on_hand_after', 'user_id',
        'order_id', 'purchase_order_id', 'reference', 'notes',
    ];

    protected function casts(): array
    {
        return ['type' => MovementType::class, 'qty' => 'integer', 'qty_on_hand_after' => 'integer'];
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
