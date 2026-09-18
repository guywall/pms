<?php

namespace App\Filament\Staff\Resources\StockItemResource\Pages;

use App\Filament\Staff\Resources\StockItemResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewStockItem extends ViewRecord
{
    protected static string $resource = StockItemResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                TextEntry::make('sku'),
                TextEntry::make('name')->weight('bold'),
                TextEntry::make('type')->badge(),
                TextEntry::make('category'),
                TextEntry::make('unit'),
                TextEntry::make('qty_on_hand')->numeric(),
                TextEntry::make('qty_reserved')->numeric(),
                TextEntry::make('qty_available')->numeric(),
                TextEntry::make('reorder_level')->numeric(),
                TextEntry::make('supplier.name'),
            ])->columns(3),
            Section::make('Movement ledger')
                ->schema([
                    RepeatableEntry::make('movements')
                        ->schema([
                            TextEntry::make('created_at')->dateTime()->since(),
                            TextEntry::make('type')->badge(),
                            TextEntry::make('qty')->numeric(),
                            TextEntry::make('qty_on_hand_after')->label('On hand after')->numeric(),
                            TextEntry::make('user.name'),
                            TextEntry::make('notes'),
                        ])
                        ->columns(6),
                ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('label')
                ->label('Print label')
                ->icon('heroicon-m-qr-code')
                ->url(fn () => route('stock-items.label', ['stockItem' => $this->getRecord()]), shouldOpenInNewTab: true),
        ];
    }
}
