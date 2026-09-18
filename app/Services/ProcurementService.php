<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\StockItem;
use App\Models\User;

class ProcurementService
{
    public function __construct(
        private StockService $stockService,
        private NotificationService $notifications,
    ) {}

    /**
     * Raise a purchase order covering the stock shortfall for an order.
     */
    public function raiseForOrderShortfall(Order $order, int $supplierId, User $user, ?string $notes = null): PurchaseOrder
    {
        $shortfall = $order->shortfall_qty;

        if ($shortfall <= 0) {
            throw new \InvalidArgumentException('No shortfall on this order.');
        }

        $item = StockItem::where('type', \App\Enums\StockType::Garment->value)->orderBy('id')->first();

        if (! $item) {
            throw new \InvalidArgumentException('No garment stock items configured.');
        }

        $po = PurchaseOrder::create([
            'supplier_id' => $supplierId,
            'status' => \App\Enums\PurchaseOrderStatus::Draft,
            'order_id' => $order->id,
            'notes' => $notes,
            'created_by' => $user->id,
        ]);

        $po->lines()->create([
            'stock_item_id' => $item->id,
            'description' => "Shortfall stock for {$order->number}",
            'qty_ordered' => $shortfall,
            'qty_received' => 0,
            'unit_cost' => $item->cost,
        ]);

        $this->notifications->notifyStaff('storekeeper', "Purchase order {$po->number} raised", "PO raised to cover {$shortfall} units for {$order->number}.", "/admin/purchase-orders/{$po->id}");

        return $po;
    }

    public function receiveLine(PurchaseOrderLine $line, int $qty, User $user): void
    {
        $this->stockService->receive($line, $qty, $user);
    }

    public function maybeClose(PurchaseOrder $po): void
    {
        if ($po->is_fully_received && $po->status === \App\Enums\PurchaseOrderStatus::Sent) {
            $po->update(['status' => \App\Enums\PurchaseOrderStatus::Closed, 'closed_at' => now()]);
            $this->notifications->purchaseOrderReceived($po);
        }
    }
}
