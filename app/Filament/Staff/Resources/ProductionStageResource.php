<?php

namespace App\Filament\Staff\Resources;

use App\Filament\Staff\Resources\ProductionStageResource\Pages;
use App\Models\ProductionStage;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;

class ProductionStageResource extends Resource
{
    protected static ?string $model = ProductionStage::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?int $navigationSort = 8;

    public static function getNavigationLabel(): string
    {
        return 'Pipeline stages';
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['super_admin', 'manager']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('key')->required()->unique(ignoreRecord: true)
                ->helperText('Machine key, e.g. printing. Used in code'),
            TextInput::make('name')->required(),
            TextInput::make('position')->numeric()->required(),
            Toggle::make('gate_requires_final_artwork')->label('Gate: needs approved artwork'),
            Toggle::make('gate_requires_stock')->label('Gate: needs full stock allocation'),
            Toggle::make('gate_requires_digitised_file')->label('Gate: needs digitised file'),
            Toggle::make('is_default_start')->label('Default start stage'),
            Toggle::make('is_terminal')->label('Terminal stage (order done)'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('position')->numeric()->sortable(),
                TextColumn::make('name')->weight('bold'),
                TextColumn::make('key')->copyable(),
                IconColumn::make('gate_requires_final_artwork')->boolean()->label('Artwork'),
                IconColumn::make('gate_requires_stock')->boolean()->label('Stock'),
                IconColumn::make('gate_requires_digitised_file')->boolean()->label('Digitised'),
            ])
            ->defaultSort('position')
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductionStages::route('/'),
            'create' => Pages\CreateProductionStage::route('/create'),
            'edit' => Pages\EditProductionStage::route('/{record}/edit'),
        ];
    }
}
