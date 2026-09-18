<?php

namespace App\Filament\Staff\Resources\OrderResource\Pages;

use App\Enums\ArtworkStatus;
use App\Filament\Staff\Resources\OrderResource;
use App\Models\ArtworkVersion;
use App\Services\ArtworkService;
use App\Services\ExportService;
use App\Services\ProcurementService;
use App\Services\StockService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Order')->tabs([
                self::detailsTab(),
                self::artworkTab(),
                self::stockTab(),
                self::historyTab(),
            ])->columnSpanFull(),
        ]);
    }

    private static function detailsTab(): Tab
    {
        return Tab::make('Details')
            ->schema([
                TextEntry::make('number')->label('Job number')->weight('bold')->copyable(),
                TextEntry::make('customer.name')->label('Customer'),
                TextEntry::make('type')->badge(),
                TextEntry::make('due_date')->label('Due')->date(),
                TextEntry::make('quantity')->numeric(),
                TextEntry::make('price')->money('GBP'),
                TextEntry::make('paid')->money('GBP'),
                TextEntry::make('shortfall_qty')->label('Stock shortfall')->numeric()
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),
                TextEntry::make('notes')->label('Internal notes'),
                TextEntry::make('customer_notes')->label('Customer brief'),
            ])->columns(3);
    }

    private static function artworkTab(): Tab
    {
        return Tab::make('Artwork')
            ->schema([
                RepeatableEntry::make('artworkVersions')
                    ->label('Versions')
                    ->schema([
                        TextEntry::make('version_number')->label('Version')->badge(),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('notes')->columnSpan(2),
                        ImageEntry::make('media.0.file_path')->label('Preview'),
                    ])
                    ->columns(4),
                TextEntry::make('digitised')
                    ->label('Digitised embroidery file')
                    ->state(fn ($record) => $record->documents()->where('category', 'digitised')->exists() ? 'Uploaded' : 'Not uploaded')
                    ->badge()
                    ->color(fn ($state) => $state === 'Uploaded' ? 'success' : 'warning'),
            ]);
    }

    private static function stockTab(): Tab
    {
        return Tab::make('Stock')
            ->schema([
                RepeatableEntry::make('allocations')
                    ->label('Allocations')
                    ->schema([
                        TextEntry::make('stockItem.name')->label('Item'),
                        TextEntry::make('qty_allocated')->numeric(),
                        TextEntry::make('qty_issued')->numeric(),
                        TextEntry::make('status')->badge(),
                    ])
                    ->columns(4),
                TextEntry::make('alloc_total')
                    ->label('Total allocated')
                    ->state(fn ($record) => $record->total_allocated.' / '.$record->quantity)
                    ->badge(),
            ]);
    }

    private static function historyTab(): Tab
    {
        return Tab::make('History')
            ->schema([
                RepeatableEntry::make('stageHistory')
                    ->label('Stage history')
                    ->schema([
                        TextEntry::make('created_at')->label('When')->dateTime()->since(),
                        TextEntry::make('from_stage')->label('From')->badge(),
                        TextEntry::make('to_stage')->label('To')->badge(),
                        TextEntry::make('user.name')->label('By'),
                        TextEntry::make('note'),
                    ])
                    ->columns(5),
                TextEntry::make('audit')
                    ->label('Full audit trail')
                    ->state(fn ($record) => new HtmlString(
                        $record->activities()->latest()->take(30)->get()
                            ->map(fn ($a) => '<li>'.e($a->created_at->format('d/m H:i')).' — '.e($a->description).'</li>')
                            ->implode('') ?: '<li>No activity yet</li>'
                    )),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            self::advanceStageAction(),
            self::uploadArtworkAction(),
            self::requestApprovalAction(),
            self::addStockAction(),
            self::raisePoAction(),
            self::uploadDigitisedAction(),
        ];
    }

    private function advanceStageAction(): Action
    {
        return Action::make('advanceStage')
            ->label('Advance stage')
            ->icon('heroicon-m-arrow-right-circle')
            ->color('primary')
            ->schema([
                Select::make('to_stage')
                    ->label('Move to')
                    ->options(fn () => collect(\App\Enums\StageKey::cases())
                        ->mapWithKeys(fn ($s) => [$s->value => $s->value])
                        ->forget([$this->getRecord()->stage->value]))
                    ->required(),
                Textarea::make('note')->label('Note (optional)'),
            ])
            ->action(function (array $data) {
                $record = $this->getRecord();
                $to = \App\Enums\StageKey::from($data['to_stage']);
                app(OrderStageService::class)->moveTo($record, $to, auth()->user(), $data['note'] ?? null);
            });
    }

    private function uploadArtworkAction(): Action
    {
        return Action::make('uploadArtwork')
            ->label('Upload artwork')
            ->icon('heroicon-m-photo')
            ->color('gray')
            ->schema([
                FileUpload::make('artwork_file')
                    ->label('Artwork file (image or PDF)')
                    ->disk('local')->directory('artwork')->maxSize(51200)->required(),
                Textarea::make('notes')->label('Version notes'),
            ])
            ->action(function (array $data) {
                $record = $this->getRecord();
                $order = $record;

                $versionNumber = ((int) $order->artworkVersions()->max('version_number')) + 1;
                $version = $order->artworkVersions()->create([
                    'version_number' => $versionNumber,
                    'status' => ArtworkStatus::Draft,
                    'is_final' => false,
                    'created_by' => auth()->id(),
                    'notes' => $data['notes'] ?? null,
                ]);

                if (! empty($data['artwork_file'])) {
                    $version->addMediaFromDisk($data['artwork_file'], 'local')->toMediaCollection('artwork');
                }
            });
    }

    private function requestApprovalAction(): Action
    {
        return Action::make('requestApproval')
            ->label('Send latest to customer')
            ->icon('heroicon-m-paper-airplane')
            ->color('warning')
            ->requiresConfirmation()
            ->action(function () {
                $record = $this->getRecord();
                $version = $record->artworkVersions()->latest('version_number')->first();
                if (! $version) {
                    \Filament\Notifications\Notification::make()
                        ->title('Upload artwork first')
                        ->danger()
                        ->send();
                    return;
                }
                app(ArtworkService::class)->submitForCustomerApproval($version, auth()->user());
            });
    }

    private function addStockAction(): Action
    {
        return Action::make('addStock')
            ->label('Allocate stock')
            ->icon('heroicon-m-archive-box-arrow-down')
            ->color('gray')
            ->schema([
                Select::make('stock_item_id')
                    ->label('Stock item')
                    ->options(fn () => \App\Models\StockItem::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()->required(),
                \Filament\Forms\Components\TextInput::make('qty')
                    ->label('Quantity')
                    ->numeric()->required()->minValue(1),
            ])
            ->action(function (array $data) {
                $record = $this->getRecord();
                $item = \App\Models\StockItem::findOrFail($data['stock_item_id']);

                app(StockService::class)->reserveForOrder($record, $item, (int) $data['qty'], auth()->user());

                if ($record->shortfall_qty > 0) {
                    app(NotificationService::class)->stockShort($record, $record->shortfall_qty);
                }
            });
    }

    private function raisePoAction(): Action
    {
        return Action::make('raisePo')
            ->label('Raise PO for shortfall')
            ->icon('heroicon-m-shopping-cart')
            ->color('danger')
            ->visible(fn () => $this->getRecord()->shortfall_qty > 0)
            ->schema([
                Select::make('supplier_id')
                    ->label('Supplier')
                    ->options(fn () => \App\Models\Supplier::orderBy('name')->pluck('name', 'id'))
                    ->required(),
            ])
            ->action(function (array $data) {
                app(ProcurementService::class)->raiseForOrderShortfall($this->getRecord(), (int) $data['supplier_id'], auth()->user());
            });
    }

    private function uploadDigitisedAction(): Action
    {
        return Action::make('uploadDigitised')
            ->label('Attach digitised file')
            ->icon('heroicon-m-document-arrow-up')
            ->color('gray')
            ->schema([
                FileUpload::make('digitised_file')
                    ->label('Digitised embroidery file')
                    ->disk('local')->directory('digitised')->maxSize(51200)->required(),
            ])
            ->action(function (array $data) {
                $record = $this->getRecord();
                $doc = $record->documents()->create([
                    'category' => 'digitised',
                    'name' => 'Digitised embroidery file',
                    'uploaded_by' => auth()->id(),
                ]);
                $doc->addMediaFromDisk($data['digitised_file'], 'local')->toMediaCollection('file');
            });
    }
}
