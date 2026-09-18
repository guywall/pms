<?php

namespace App\Filament\Staff\Widgets;

use App\Filament\Staff\Resources\OrderResource;
use App\Models\Order;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class DueSoonOrders extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Order::query()->open()->orderBy('due_date')->limit(8))
            ->columns([
                TextColumn::make('number')->weight('bold'),
                TextColumn::make('customer.name'),
                TextColumn::make('stage')->badge(),
                TextColumn::make('due_date')->date(),
                TextColumn::make('shortfall_qty')->label('Shortfall')->color(fn ($state) => $state > 0 ? 'danger' : 'success'),
            ]);
    }
}
