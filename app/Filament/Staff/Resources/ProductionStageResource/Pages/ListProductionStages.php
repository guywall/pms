<?php

namespace App\Filament\Staff\Resources\ProductionStageResource\Pages;

use App\Filament\Staff\Resources\ProductionStageResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListProductionStages extends ListRecords
{
    protected static string $resource = ProductionStageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('new')->url(static::getResource()::getUrl('create')),
        ];
    }
}
