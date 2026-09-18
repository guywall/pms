<?php

namespace App\Filament\Staff\Widgets;

use App\Enums\StageKey;
use App\Models\Order;
use App\Models\StockItem;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $blocked = Order::open()->get()->filter(fn ($o) => $o->shortfall_qty > 0 || ! $o->finalArtwork()->exists());

        return [
            Stat::make('Open jobs', Order::open()->count())
                ->description('Jobs not yet complete')
                ->icon('heroicon-m-clipboard-document-list')
                ->color('primary'),
            Stat::make('Awaiting artwork approval', Order::open()->where('stage', StageKey::Artwork->value)->count())
                ->description('In the artwork stage')
                ->icon('heroicon-m-photo')
                ->color('warning'),
            Stat::make('Blocked jobs', $blocked->count())
                ->description('Shortfall stock or no final artwork')
                ->icon('heroicon-m-exclamation-triangle')
                ->color($blocked->count() > 0 ? 'danger' : 'success'),
            Stat::make('Low stock items', StockItem::lowStock()->count())
                ->description('At or below reorder level')
                ->icon('heroicon-m-archive-box-arrow-down')
                ->color(StockItem::lowStock()->count() > 0 ? 'danger' : 'success'),
        ];
    }
}
