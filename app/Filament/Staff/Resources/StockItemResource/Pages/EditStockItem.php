<?php

namespace App\Filament\Staff\Resources\StockItemResource\Pages;

use App\Filament\Staff\Resources\StockItemResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStockItem extends EditRecord
{
    protected static string $resource = StockItemResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
