<div class="max-w-lg mx-auto py-8 px-4">
    <h1 class="text-2xl font-bold mb-4">Scan</h1>

    <form wire:submit="lookupCode" class="flex gap-2 mb-4">
        <input type="text" wire:model.live.debounce.300ms="code" placeholder="Scan QR / barcode or type code…"
               autofocus class="flex-1 rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900" />
        <button type="submit" class="px-4 py-2 rounded-lg bg-primary-500 text-white font-medium">Go</button>
    </form>

    @if ($message)
        <div class="rounded-lg bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 mb-4 text-sm">{{ $message }}</div>
    @endif

    @if ($lookup)
        @if ($lookupType === 'order')
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4 mb-4">
                <div class="font-bold">{{ $lookup->number }}</div>
                <div class="text-sm text-gray-500">{{ $lookup->stage->value }} · due {{ $lookup->due_date?->format('d M Y') }}</div>
                <a href="{{ route('filament.staff.resources.orders.view', ['record' => $lookup]) }}"
                   class="mt-2 inline-block px-3 py-1.5 rounded-lg bg-primary-500 text-white text-sm">Open job</a>
            </div>
        @else
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                <div class="font-bold">{{ $lookup->name }} <span class="text-gray-400 text-sm">{{ $lookup->sku }}</span></div>
                <div class="text-sm text-gray-500">
                    On hand: {{ $lookup->qty_on_hand }} {{ $lookup->unit }} · Reserved: {{ $lookup->qty_reserved }} · Available: {{ $lookup->qty_available }}
                </div>

                <div class="mt-3 flex items-center gap-2">
                    <input type="number" wire:model.live="qty" min="1" class="w-20 rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900" />
                    <button wire:click="issueStock" class="px-3 py-1.5 rounded-lg bg-primary-500 text-white text-sm">Issue</button>
                    <button wire:click="adjustStock(1)" class="px-3 py-1.5 rounded-lg bg-gray-200 dark:bg-gray-700 text-sm">+1</button>
                    <button wire:click="adjustStock(-1)" class="px-3 py-1.5 rounded-lg bg-gray-200 dark:bg-gray-700 text-sm">-1</button>
                </div>
            </div>
        @endif
    @endif
</div>
