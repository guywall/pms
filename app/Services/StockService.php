<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Models\Order;
use App\Models\OrderStockAllocation;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StockService
{
    /**
     * Reserve stock for an order. Returns allocations created or topped up.
     * Reserves from on-hand stock first (FIFO by id); any remaining quantity is
     * recorded as a backorder allocation linked to the given purchase order (or none).
     */
    public function reserveForOrder(Order $order, StockItem $item, int $qty, ?User $user = null, ?int $purchaseOrderId = null): OrderStockAllocation
    {
        if ($qty <= 0) {
            throw new InvalidArgumentException('Quantity must be positive.');
        }

        return DB::transaction(function () use ($order, $item, $qty, $user, $purchaseOrderId) {
            $item = StockItem::whereKey($item->id)->lockForUpdate()->firstOrFail();

            $available = $item->qty_available;
            $fromStock = min($available, $qty);
            $backorder = $qty - $fromStock;

            // Top up an existing open allocation for the same order+item if there is one
            $allocation = OrderStockAllocation::query()
                ->where('order_id', $order->id)
                ->where('stock_item_id', $item->id)
                ->whereIn('status', [OrderStockAllocation::STATUS_RESERVED, OrderStockAllocation::STATUS_PARTIALLY_RECEIVED])
                ->lockForUpdate()
                ->first();

            if (! $allocation) {
                $allocation = new OrderStockAllocation([
                    'order_id' => $order->id,
                    'stock_item_id' => $item->id,
                    'qty_allocated' => 0,
                    'qty_issued' => 0,
                    'status' => OrderStockAllocation::STATUS_RESERVED,
                    'purchase_order_id' => $purchaseOrderId,
                ]);
            }

            $allocation->qty_allocated += $qty;
            if ($backorder > 0 && $purchaseOrderId) {
                $allocation->status = OrderStockAllocation::STATUS_PARTIALLY_RECEIVED;
                $allocation->purchase_order_id = $purchaseOrderId;
            }
            $allocation->save();

            if ($fromStock > 0) {
                $item->qty_reserved += $fromStock;
                $item->save();
            }

            activity('stock')->performedOn($order)
                ->withProperties(['stock_item' => $item->sku, 'qty' => $qty, 'backorder' => $backorder])
                ->log("Reserved {$qty} × {$item->name} for {$order->number}".($backorder > 0 ? " ({$backorder} on backorder)" : ''));

            return $allocation;
        });
    }

    /**
     * Issue reserved stock to production: moves quantity from reserved to consumed.
     */
    public function issue(OrderStockAllocation $allocation, int $qty, User $user): void
    {
        DB::transaction(function () use ($allocation, $qty, $user) {
            if ($qty <= 0 || $qty > $allocation->qty_allocated - $allocation->qty_issued) {
                throw new InvalidArgumentException('Invalid issue quantity.');
            }

            $item = StockItem::whereKey($allocation->stock_item_id)->lockForUpdate()->firstOrFail();

            $item->qty_reserved = max(0, $item->qty_reserved - $qty);
            $item->qty_on_hand = max(0, $item->qty_on_hand - $qty);
            $item->save();

            $allocation->qty_issued += $qty;
            if ($allocation->qty_issued >= $allocation->qty_allocated) {
                $allocation->status = OrderStockAllocation::STATUS_ISSUED;
            }
            $allocation->save();

            $this->recordMovement($item, MovementType::Issue, -$qty, $user, $allocation->order_id, "Issued to {$allocation->order->number}");
        });
    }

    /**
     * Release a reservation back to available stock (order cancelled / changed).
     */
    public function release(OrderStockAllocation $allocation, User $user): void
    {
        DB::transaction(function () use ($allocation, $user) {
            $item = StockItem::whereKey($allocation->stock_item_id)->lockForUpdate()->firstOrFail();

            $outstanding = $allocation->qty_allocated - $allocation->qty_issued;

            if ($outstanding > 0 && $allocation->status !== OrderStockAllocation::STATUS_PARTIALLY_RECEIVED) {
                $item->qty_reserved = max(0, $item->qty_reserved - $outstanding);
                $item->save();
            }

            $allocation->status = OrderStockAllocation::STATUS_RELEASED;
            $allocation->save();

            activity('stock')->performedOn($allocation->order)
                ->withProperties(['stock_item' => $item->sku, 'qty' => $outstanding])
                ->log("Released reservation of {$outstanding} × {$item->name}");
        });
    }

    /**
     * Manual stock adjustment (stocktake correction, breakages...).
     */
    public function adjust(StockItem $item, int $delta, User $user, ?string $notes = null): void
    {
        DB::transaction(function () use ($item, $delta, $user, $notes) {
            $item = StockItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            $item->qty_on_hand = max(0, $item->qty_on_hand + $delta);
            $item->save();

            $this->recordMovement($item, MovementType::Adjust, $delta, $user, null, $notes ?? 'Manual adjustment');
        });
    }

    /**
     * Receive stock against a purchase order line (goods-in). Returns qty received here.
     */
    public function receive(PurchaseOrderLine $line, int $qty, User $user): int
    {
        if ($qty <= 0) {
            throw new InvalidArgumentException('Quantity must be positive.');
        }

        return DB::transaction(function () use ($line, $qty, $user) {
            $remaining = $line->qty_ordered - $line->qty_received;
            if ($qty > $remaining) {
                throw new InvalidArgumentException("Cannot receive more than ordered ({$remaining} remaining).");
            }

            $item = StockItem::whereKey($line->stock_item_id)->lockForUpdate()->firstOrFail();
            $item->qty_on_hand += $qty;
            $item->save();

            $line->qty_received += $qty;
            $line->save();

            $this->recordMovement($item, MovementType::Receipt, $qty, $user, null, $line->purchaseOrder->number, $line->purchaseOrder);

            return $qty;
        });
    }

    private function recordMovement(StockItem $item, MovementType $type, int $qty, User $user, ?int $orderId = null, ?string $notes = null, $po = null): void
    {
        StockMovement::create([
            'stock_item_id' => $item->id,
            'type' => $type,
            'qty' => $qty,
            'qty_on_hand_after' => $item->qty_on_hand,
            'user_id' => $user?->id,
            'order_id' => $orderId,
            'purchase_order_id' => $po?->id,
            'notes' => $notes,
        ]);
    }
}
