<?php

namespace App\Filament\Staff\Resources;

use App\Filament\Staff\Resources\PurchaseOrderResource\Pages;
use App\Models\PurchaseOrder;
use App\Services\ProcurementService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PurchaseOrderResource extends Resource
{
    protected static ?string $model = PurchaseOrder::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('supplier_id')->relationship('supplier', 'name')->required()->searchable()->preload(),
            Select::make('status')->options([
                'draft' => 'Draft',
                'sent' => 'Sent to supplier',
                'closed' => 'Closed',
            ])->default('draft')->required(),
            Select::make('order_id')->relationship('order', 'number')->searchable()->label('Related job (optional)'),
            DatePicker::make('expected_date')->native(false),
            Textarea::make('notes')->columnSpanFull(),
            Repeater::make('lines')
                ->relationship()
                ->schema([
                    Select::make('stock_item_id')->relationship('stockItem', 'name')->searchable()->required(),
                    TextInput::make('qty_ordered')->numeric()->required(),
                    TextInput::make('unit_cost')->numeric()->prefix('£'),
                ])
                ->columns(3)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->weight('bold')->searchable(),
                TextColumn::make('supplier.name')->searchable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('order.number')->label('Job'),
                TextColumn::make('expected_date')->date()->label('Expected'),
                TextColumn::make('lines_count')->counts('lines')->label('Lines'),
            ])
            ->filters([
                SelectFilter::make('status')->options(['draft' => 'Draft', 'sent' => 'Sent', 'closed' => 'Closed']),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('markSent')
                    ->icon('heroicon-m-paper-airplane')
                    ->requiresConfirmation()
                    ->visible(fn (PurchaseOrder $record) => $record->status->value === 'draft')
                    ->action(function (PurchaseOrder $record) {
                        $record->update(['status' => \App\Enums\PurchaseOrderStatus::Sent, 'sent_at' => now()]);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchaseOrders::route('/'),
            'create' => Pages\CreatePurchaseOrder::route('/create'),
            'view' => Pages\ViewPurchaseOrder::route('/{record}'),
            'edit' => Pages\EditPurchaseOrder::route('/{record}/edit'),
        ];
    }
}
