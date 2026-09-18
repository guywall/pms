<?php

namespace App\Filament\Staff\Resources;

use App\Filament\Staff\Resources\StockItemResource\Pages;
use App\Models\StockItem;
use App\Services\StockService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StockItemResource extends Resource
{
    protected static ?string $model = StockItem::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('sku')->required()->unique(ignoreRecord: true),
            TextInput::make('name')->required(),
            Select::make('type')->options([
                'garment' => 'Garment',
                'consumable' => 'Consumable',
            ])->required(),
            TextInput::make('category'),
            TextInput::make('unit')->default('each')->required(),
            TextInput::make('qty_on_hand')->numeric()->default(0),
            TextInput::make('qty_reserved')->numeric()->default(0)->helperText('Managed automatically'),
            TextInput::make('reorder_level')->numeric()->default(0),
            TextInput::make('reorder_qty')->numeric()->default(0),
            Select::make('supplier_id')->relationship('supplier', 'name')->searchable()->preload(),
            TextInput::make('cost')->numeric()->prefix('£'),
            TextInput::make('notes'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sku')->searchable(),
                TextColumn::make('name')->searchable()->weight('bold'),
                TextColumn::make('type')->badge(),
                TextColumn::make('category'),
                TextColumn::make('qty_on_hand')->numeric()->label('On hand'),
                TextColumn::make('qty_reserved')->numeric()->label('Reserved'),
                TextColumn::make('qty_available')->numeric()->label('Available'),
                TextColumn::make('reorder_level')->numeric()->label('Reorder at'),
                TextColumn::make('supplier.name')->label('Supplier'),
            ])
            ->filters([
                SelectFilter::make('type')->options(['garment' => 'Garment', 'consumable' => 'Consumable']),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('adjust')
                    ->icon('heroicon-m-adjustments-vertical')
                    ->schema([
                        TextInput::make('delta')->numeric()->required()
                            ->helperText('Positive or negative adjustment (stocktake, breakage...)'),
                        TextInput::make('notes'),
                    ])
                    ->action(function (array $data, StockItem $record) {
                        app(StockService::class)->adjust($record, (int) $data['delta'], auth()->user(), $data['notes'] ?? null);
                    }),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->with('supplier'));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockItems::route('/'),
            'create' => Pages\CreateStockItem::route('/create'),
            'view' => Pages\ViewStockItem::route('/{record}'),
            'edit' => Pages\EditStockItem::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) StockItem::lowStock()->count();
    }
}
