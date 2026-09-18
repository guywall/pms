<?php

namespace App\Filament\Staff\Resources;

use App\Filament\Staff\Resources\CustomerResource\Pages;
use App\Models\Customer;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required(),
            TextInput::make('contact_name'),
            TextInput::make('email')->email(),
            TextInput::make('phone'),
            Textarea::make('address')->columnSpanFull(),
            Textarea::make('notes')->columnSpanFull(),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->weight('bold'),
                TextColumn::make('contact_name'),
                TextColumn::make('email'),
                TextColumn::make('phone'),
                TextColumn::make('orders_count')->counts('orders')->label('Jobs'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('inviteUser')
                    ->label('Add portal user')
                    ->icon('heroicon-m-user-plus')
                    ->schema([
                        TextInput::make('name')->required(),
                        TextInput::make('email')->email()->required(),
                    ])
                    ->action(function (array $data, Customer $record) {
                        $user = User::create([
                            'name' => $data['name'],
                            'email' => $data['email'],
                            'password' => \Illuminate\Support\Str::password(12),
                            'customer_id' => $record->id,
                            'is_staff' => false,
                        ]);
                        $user->assignRole('customer');

                        \Filament\Notifications\Notification::make()
                            ->title('Portal user created')
                            ->body("Share the portal link and use 'Forgot password' to set a password for {$user->email}.")
                            ->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'view' => Pages\ViewCustomer::route('/{record}'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}
