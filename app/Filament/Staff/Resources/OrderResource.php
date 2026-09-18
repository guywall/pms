<?php

namespace App\Filament\Staff\Resources;

use App\Enums\StageKey;
use App\Filament\Staff\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Models\ProductionStage;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('reference')->maxLength(255)->label('Customer reference'),
            Select::make('customer_id')->relationship('customer', 'name')->searchable()->preload()->required(),
            Select::make('type')->options([
                'print' => 'Print',
                'embroidery' => 'Embroidery',
                'mixed' => 'Mixed',
            ])->required()->default('print'),
            DatePicker::make('due_date')->native(false)->required(),
            TextInput::make('quantity')->numeric()->minValue(1)->required(),
            TextInput::make('price')->numeric()->prefix('£')->helperText('Order total, inclusive of VAT if applicable'),
            TextInput::make('paid')->numeric()->prefix('£')->default(0),
            Textarea::make('notes')->columnSpanFull(),
            Textarea::make('customer_notes')->columnSpanFull()->label('Customer brief'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->searchable()->weight('bold')->sortable(),
                TextColumn::make('customer.name')->sortable()->searchable(),
                TextColumn::make('type')->badge(),
                TextColumn::make('stage')->badge()
                    ->formatStateUsing(fn ($state) => ProductionStage::where('key', $state->value)->value('name') ?? $state->value)
                    ->color(fn ($state) => match ($state) {
                        StageKey::Complete, StageKey::Invoiced => 'success',
                        StageKey::Artwork => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('due_date')->date()->sortable()
                    ->color(fn ($record) => $record->due_date->isPast() && ! in_array($record->stage, [StageKey::Complete, StageKey::Invoiced]) ? 'danger' : null),
                TextColumn::make('quantity')->numeric()->sortable(),
                TextColumn::make('shortfall_qty')->numeric()->label('Shortfall')
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),
                TextColumn::make('price')->money('GBP')->sortable(),
            ])
            ->filters([
                SelectFilter::make('stage')
                    ->options(collect(StageKey::cases())->mapWithKeys(fn ($s) => [$s->value => $s->value])),
                SelectFilter::make('customer')->relationship('customer', 'name'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->defaultSort('due_date')
            ->poll('30s');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'view' => Pages\ViewOrder::route('/{record}'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) Order::open()->count();
    }
}
