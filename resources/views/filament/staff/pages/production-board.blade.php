<x-filament-panels::page>
    <div class="flex gap-4 overflow-x-auto pb-4">
        @foreach ($this->stages as $stage)
            @php
                $orders = $this->ordersByStage->get($stage->key, collect());
            @endphp
            <div class="w-72 flex-shrink-0 rounded-xl bg-gray-100 dark:bg-gray-900 p-3">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-semibold text-sm">{{ $stage->name }}</h3>
                    <span class="text-xs rounded-full bg-gray-300 dark:bg-gray-700 px-2 py-0.5">{{ $orders->count() }}</span>
                </div>

                <div class="space-y-2">
                    @forelse ($orders as $order)
                        <div class="rounded-lg bg-white dark:bg-gray-800 shadow p-3 text-sm">
                            <div class="font-medium">{{ $order->number }}</div>
                            <div class="text-xs text-gray-500">{{ $order->customer->name }}</div>
                            <div class="text-xs mt-1">
                                Due {{ $order->due_date?->format('d M') }}
                                @if ($order->shortfall_qty > 0)
                                    <span class="text-danger-500 font-medium"> · short {{ $order->shortfall_qty }}</span>
                                @endif
                            </div>
                            <div class="mt-2 flex flex-wrap gap-1">
                                @foreach ($this->stages->where('position', '>', $stage->position)->take(2) as $next)
                                    @php
                                        $can = app(\App\Services\OrderStageService::class)->canMove($order, \App\Enums\StageKey::from($next->key));
                                    @endphp
                                    <button type="button"
                                        wire:click="moveOrder({{ $order->id }}, '{{ $next->key }}')"
                                        @if (! $can) disabled title="Blocked by a stage gate (artwork / stock / digitised file)" @endif
                                        class="text-xs px-2 py-1 rounded bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 disabled:opacity-40 disabled:cursor-not-allowed">
                                        → {{ \Illuminate\Support\Str::limit($next->name, 12) }}
                                    </button>
                                @endforeach
                                <a href="{{ \App\Filament\Staff\Resources\OrderResource::getUrl('view', ['record' => $order]) }}"
                                   class="text-xs px-2 py-1 rounded bg-primary-500/10 text-primary-600 hover:bg-primary-500/20">Open</a>
                            </div>
                        </div>
                    @empty
                        <div class="text-xs text-gray-400 italic">No jobs</div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
