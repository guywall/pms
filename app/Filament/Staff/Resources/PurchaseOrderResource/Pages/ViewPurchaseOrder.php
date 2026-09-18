<?php

namespace App\Filament\Staff\Resources\PurchaseOrderResource\Pages;

use App\Filament\Staff\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrderLine;
use App\Services\ProcurementService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewPurchaseOrder extends ViewRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                TextEntry::make('number')->weight('bold'),
                TextEntry::make('supplier.name'),
                TextEntry::make('status')->badge(),
                TextEntry::make('order.number')->label('Job'),
                TextEntry::make('expected_date')->date(),
                TextEntry::make('notes'),
            ])->columns(3),
            Section::make('Lines')->schema([
                RepeatableEntry::make('lines')
                    ->schema([
                        TextEntry::make('stockItem.name')->label('Item'),
                        TextEntry::make('qty_ordered')->numeric(),
                        TextEntry::make('qty_received')->numeric(),
                        TextEntry::make('unit_cost')->money('GBP'),
                    ])
                    ->columns(4),
            ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('receive')
                ->label('Receive goods')
                ->icon('heroicon-m-truck')
                ->schema(function () {
                    $lines = $this->getRecord()->lines()->with('stockItem')->get();

                    return $lines->map(fn (PurchaseOrderLine $line) => TextInput::make('line_'.$line->id)
                        ->label($line->stockItem->name." — ordered {$line->qty_ordered}, received {$line->qty_received}")
                        ->numeric()
                        ->default(0)
                        ->minValue(0))->all();
                })
                ->action(function (array $data) {
                    $service = app(ProcurementService::class);
                    $received = 0;

                    foreach ($data as $key => $qty) {
                        $lineId = (int) str_replace('line_', '', $key);
                        $line = PurchaseOrderLine::find($lineId);
                        $qty = (int) $qty;

                        if ($line && $qty > 0) {
                            $service->receiveLine($line, $qty, auth()->user());
                            $received += $qty;
                        }
                    }

                    $service->maybeClose($this->getRecord());

                    Notification::make()
                        ->title("Received {$received} units into stock")
                        ->success()
                        ->send();
                }),
        ];
    }
}
