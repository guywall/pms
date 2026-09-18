<?php

namespace App\Livewire;

use App\Models\Order;
use App\Models\StockItem;
use App\Services\StockService;
use Filament\Actions\Action;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Scan')]
class ScanPage extends Component
{
    public ?string $code = null;

    public string $mode = 'lookup';

    public $lookup = null;

    public string $lookupType = '';

    public int $qty = 1;

    public string $message = '';

    public function lookupCode(): void
    {
        $this->validate(['code' => 'required|string']);
        $code = trim($this->code);

        // Job QR: JOB-2026-00001 · Stock QR: SKU:GAR-TSHI-BLK-M
        if (str_starts_with($code, 'SKU:')) {
            $sku = substr($code, 4);
            $item = StockItem::where('sku', $sku)->first();
            if ($item) {
                $this->lookup = $item;
                $this->lookupType = 'stock';

                return;
            }
        }

        $order = Order::where('number', $code)->first();
        if ($order) {
            $this->lookup = $order->withoutRelations();
            $this->lookupType = 'order';

            return;
        }

        $this->message = "Nothing found for code: {$code}";
    }

    public function issueStock(): void
    {
        if ($this->lookupType !== 'stock') {
            return;
        }

        $allocation = $this->lookup->allocations()
            ->whereIn('status', ['reserved'])
            ->first();

        if ($allocation) {
            app(StockService::class)->issue($allocation, min($this->qty, $allocation->qty_allocated - $allocation->qty_issued), auth()->user());
            $this->message = 'Stock issued.';
        } else {
            $this->message = 'No open reservation for this item — nothing issued.';
        }
    }

    public function adjustStock(int $delta): void
    {
        if ($this->lookupType !== 'stock') {
            return;
        }

        app(StockService::class)->adjust($this->lookup, $delta, auth()->user(), 'Scan page adjustment');
        $this->message = "Stock adjusted by {$delta}.";
    }

    public function render()
    {
        return view('livewire.scan-page');
    }
}
