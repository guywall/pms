<?php

namespace App\Services;

use App\Enums\StageKey;
use App\Models\Order;
use App\Models\OrderStageHistory;
use App\Models\ProductionStage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\UnauthorizedException;
use InvalidArgumentException;

class OrderStageService
{
    public function __construct(
        private StockService $stockService,
        private NotificationService $notifications,
    ) {}

    /**
     * Move an order to a new stage after validating all gates.
     */
    public function moveTo(Order $order, StageKey $to, User $user, ?string $note = null): void
    {
        DB::transaction(function () use ($order, $to, $user, $note) {
            $from = $order->stage;
            if ($from === $to) {
                throw new InvalidArgumentException('Order is already in that stage.');
            }

            $this->assertCanMove($order, $to);

            $order->stage = $to;
            $order->save();

            OrderStageHistory::create([
                'order_id' => $order->id,
                'from_stage' => $from,
                'to_stage' => $to,
                'user_id' => $user->id,
                'note' => $note,
            ]);

            activity('order')->performedOn($order)->causedBy($user)
                ->withProperties(['from' => $from->value, 'to' => $to->value])
                ->log("Stage moved {$from->value} → {$to->value}");

            $this->notifications->stageChanged($order, $from, $to, $user);
        });
    }

    /**
     * Gate checks: the rules that stop jobs jumping ahead of themselves.
     */
    public function assertCanMove(Order $order, StageKey $to): void
    {
        $stage = ProductionStage::where('key', $to->value)->first();

        if ($stage && $stage->gate_requires_final_artwork && ! $order->finalArtwork()->exists()) {
            throw new InvalidArgumentException('Gate: this stage requires final approved artwork. Approve an artwork version first.');
        }

        if ($stage && $stage->gate_requires_stock && $order->shortfall_qty > 0) {
            throw new InvalidArgumentException("Gate: this stage requires full stock allocation. Shortfall: {$order->shortfall_qty}.");
        }

        if ($stage && $stage->gate_requires_digitised_file) {
            $hasDigitised = $order->documents()->where('category', 'digitised')->exists()
                || $order->artworkVersions()->whereHas('media', fn ($q) => $q->where('custom_properties->digitised', true))->exists();

            if (! $hasDigitised) {
                throw new InvalidArgumentException('Gate: this stage requires a digitised embroidery file. Upload one on the order.');
            }
        }
    }

    /**
     * Can this move happen at all? (used to grey out kanban drag targets / buttons)
     */
    public function canMove(Order $order, StageKey $to): bool
    {
        try {
            $this->assertCanMove($order, $to);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }
}
