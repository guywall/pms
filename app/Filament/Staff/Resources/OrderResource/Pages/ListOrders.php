<?php

namespace App\Filament\Staff\Resources\OrderResource\Pages;

use App\Filament\Staff\Pages\ProductionBoard;
use App\Filament\Staff\Resources\OrderResource;
use App\Services\ExportService;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('productionBoard')
                ->label('Production board')
                ->icon('heroicon-m-view-columns')
                ->color('gray')
                ->url(ProductionBoard::getUrl()),
            Action::make('exportXero')
                ->label('Export Xero CSV')
                ->icon('heroicon-m-arrow-down-tray')
                ->color('gray')
                ->action(function () use (&$stream) {
                    $csv = app(ExportService::class)->xeroInvoicesCsv(
                        OrderResource::getModel()::query()->with('customer', 'lines')->get()
                    );

                    return response()->streamDownload(
                        fn () => print ($csv),
                        'xero-invoices-'.now()->format('Y-m-d').'.csv',
                        ['Content-Type' => 'text/csv']
                    );
                }),
            Action::make('newOrder')
                ->label('New order')
                ->icon('heroicon-m-plus')
                ->url(static::getResource()::getUrl('create')),
        ];
    }
}
