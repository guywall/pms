<?php

namespace App\Filament\Staff\Pages;

use App\Filament\Staff\Widgets\DueSoonOrders;
use App\Filament\Staff\Widgets\StatsOverview;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public function getColumns(): array|int
    {
        return 2;
    }

    public function getWidgets(): array
    {
        return [
            StatsOverview::class,
            DueSoonOrders::class,
        ];
    }
}
