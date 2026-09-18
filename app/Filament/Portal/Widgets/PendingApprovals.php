<?php

namespace App\Filament\Portal\Widgets;

use App\Enums\ArtworkStatus;
use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PendingApprovals extends BaseWidget
{
    protected function getStats(): array
    {
        $customerId = auth()->user()?->customer_id;

        $pending = Order::where('customer_id', $customerId)
            ->whereHas('artworkVersions', fn ($q) => $q->where('status', ArtworkStatus::AwaitingCustomer))
            ->count();

        return [
            Stat::make('Artwork awaiting your approval', $pending)
                ->description($pending > 0 ? 'Open an order to approve' : 'All caught up')
                ->color($pending > 0 ? 'warning' : 'success')
                ->icon('heroicon-m-photo'),
        ];
    }
}
