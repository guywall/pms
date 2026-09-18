<?php

namespace App\Filament\Staff\Resources\StockItemResource\Pages;

use App\Filament\Staff\Resources\StockItemResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListStockItems extends ListRecords
{
    protected static string $resource = StockItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('newItem')->label('New stock item')->url(static::getResource()::getUrl('create')),
        ];
    }
}
