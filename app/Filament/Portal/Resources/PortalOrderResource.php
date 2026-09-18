<?php

namespace App\Filament\Portal\Resources;

use App\Filament\Portal\Resources\PortalOrderResource\Pages;
use App\Models\Order;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PortalOrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'My orders';

    protected static ?string $slug = 'orders';

    public static function canAccess(): bool
    {
        return auth()->check() && ! auth()->user()->is_staff;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canView($record): bool
    {
        return auth()->check() && $record->customer_id === auth()->user()->customer_id;
    }

    public static function modifyQueryUsing(Builder $query): Builder
    {
        return $query->where('customer_id', auth()->user()->customer_id);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->weight('bold'),
                TextColumn::make('due_date')->date()->label('Due'),
                TextColumn::make('quantity'),
                TextColumn::make('stage')->badge()
                    ->formatStateUsing(fn ($state) => \App\Models\ProductionStage::where('key', $state->value)->value('name') ?? ''),
            ])
            ->recordUrl(fn ($record) => self::getUrl('view', ['record' => $record]));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPortalOrders::route('/'),
            'view' => Pages\ViewPortalOrder::route('/{record}'),
        ];
    }
}
