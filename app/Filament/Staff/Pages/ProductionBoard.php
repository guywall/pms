<?php

namespace App\Filament\Staff\Pages;

use App\Enums\StageKey;
use App\Models\Order;
use App\Models\ProductionStage;
use App\Services\OrderStageService;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Notification;

class ProductionBoard extends Page
{
    protected string $view = 'filament.staff.pages.production-board';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-view-columns';

    protected static ?int $navigationSort = 0;

    public function getHeading(): string
    {
        return 'Production board';
    }

    public static function getNavigationLabel(): string
    {
        return 'Production board';
    }

    public function getStagesProperty()
    {
        return ProductionStage::orderBy('position')->get();
    }

    public function getOrdersByStageProperty()
    {
        return Order::query()->with('customer')->open()->get()->groupBy(fn ($o) => $o->stage->value);
    }

    public function moveOrder(int $orderId, string $toStage): void
    {
        $order = Order::findOrFail($orderId);

        try {
            app(OrderStageService::class)->moveTo($order, StageKey::from($toStage), auth()->user());
        } catch (\InvalidArgumentException $e) {
            \Filament\Notifications\Notification::make()
                ->title('Cannot move job')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
