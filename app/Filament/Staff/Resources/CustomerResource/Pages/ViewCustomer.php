<?php

namespace App\Filament\Staff\Resources\CustomerResource\Pages;

use App\Filament\Staff\Resources\CustomerResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            \Filament\Infolists\Components\TextEntry::make('name')->weight('bold'),
            \Filament\Infolists\Components\TextEntry::make('contact_name'),
            \Filament\Infolists\Components\TextEntry::make('email'),
            \Filament\Infolists\Components\TextEntry::make('phone'),
            \Filament\Infolists\Components\TextEntry::make('address'),
            \Filament\Infolists\Components\TextEntry::make('users.email')->label('Portal users')->badge(),
        ])->columns(2);
    }

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
