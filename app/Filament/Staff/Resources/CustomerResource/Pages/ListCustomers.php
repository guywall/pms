<?php

namespace App\Filament\Staff\Resources\CustomerResource\Pages;

use App\Filament\Staff\Resources\CustomerResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('new')->label('New customer')->url(static::getResource()::getUrl('create')),
        ];
    }
}
