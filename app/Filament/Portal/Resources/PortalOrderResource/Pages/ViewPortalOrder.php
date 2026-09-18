<?php

namespace App\Filament\Portal\Resources\PortalOrderResource\Pages;

use App\Enums\ArtworkStatus;
use App\Filament\Portal\Resources\PortalOrderResource;
use App\Services\ArtworkService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class ViewPortalOrder extends ViewRecord
{
    protected static string $resource = PortalOrderResource::class;

    public function infolist(Schema $schema): Schema
    {
        $stages = \App\Models\ProductionStage::orderBy('position')->get();

        return $schema->components([
            Section::make('Progress')->schema([
                TextEntry::make('progress')
                    ->hiddenLabel()
                    ->state(function (Model $record) use ($stages) {
                        $currentIndex = $stages->search(fn ($s) => $s->key === $record->stage->value);

                        return new HtmlString(
                            '<div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">'
                            .$stages->map(function ($s, $i) use ($currentIndex) {
                                $bg = $i === $currentIndex ? '#4f46e5' : ($i < $currentIndex ? '#a5b4fc' : '#e5e7eb');
                                $fg = $i <= $currentIndex ? '#ffffff' : '#6b7280';

                                return "<span style=\"background:{$bg};color:{$fg};padding:4px 10px;border-radius:999px;font-size:12px\">".e($s->name).'</span>';
                            })->implode('<span style="color:#9ca3af">→</span>')
                            .'</div>'
                        );
                    }),
            ]),
            Section::make('Job details')->schema([
                TextEntry::make('number')->label('Job number')->weight('bold'),
                TextEntry::make('due_date')->label('Due')->date(),
                TextEntry::make('quantity'),
                TextEntry::make('customer_notes')->label('Your brief'),
            ])->columns(4),
            Section::make('Artwork versions')->schema([
                RepeatableEntry::make('artworkVersions')
                    ->state(fn (Model $record) => $record->artworkVersions()->orderByDesc('version_number')->get())
                    ->schema([
                        TextEntry::make('version_number')->label('Version')->badge(),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('approved_at')->label('Approved')->dateTime()->placeholder('—'),
                    ])
                    ->columns(3),
                TextEntry::make('final_status')
                    ->hiddenLabel()
                    ->state(fn (Model $record) => $record->artworkVersions()->where('status', ArtworkStatus::AwaitingCustomer)->exists()
                        ? new HtmlString('<em>Your artwork is awaiting your approval below.</em>')
                        : new HtmlString('<em>No artwork is currently awaiting your approval.</em>')),
            ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->approveAction(),
            $this->requestChangesAction(),
            $this->uploadAction(),
        ];
    }

    private function pendingVersion()
    {
        return $this->getRecord()->artworkVersions()
            ->where('status', ArtworkStatus::AwaitingCustomer)
            ->orderByDesc('version_number')
            ->first();
    }

    private function approveAction(): Action
    {
        return Action::make('approveArtwork')
            ->label('Approve artwork')
            ->icon('heroicon-m-check-circle')
            ->color('success')
            ->visible(fn () => (bool) $this->pendingVersion())
            ->requiresConfirmation()
            ->modalDescription('Approving locks this artwork for production. You can still request changes afterwards if needed.')
            ->action(function () {
                if ($version = $this->pendingVersion()) {
                    app(ArtworkService::class)->approve($version, auth()->user());

                    Notification::make()->title('Artwork approved — thank you!')->success()->send();
                }
            });
    }

    private function requestChangesAction(): Action
    {
        return Action::make('requestChanges')
            ->label('Request changes')
            ->icon('heroicon-m-arrow-path')
            ->color('warning')
            ->visible(fn () => (bool) $this->pendingVersion())
            ->schema([Textarea::make('reason')->label('What would you like changed?')->required()])
            ->action(function (array $data) {
                if ($version = $this->pendingVersion()) {
                    app(ArtworkService::class)->reject($version, auth()->user(), $data['reason']);

                    Notification::make()->title('Change request sent — our design team will be in touch.')->success()->send();
                }
            });
    }

    private function uploadAction(): Action
    {
        return Action::make('uploadFile')
            ->label('Upload a file')
            ->icon('heroicon-m-arrow-up-tray')
            ->color('gray')
            ->schema([
                FileUpload::make('file')->disk('local')->directory('customer-uploads')->required(),
                Textarea::make('name')->label('What is this file?'),
            ])
            ->action(function (array $data) {
                $doc = $this->getRecord()->documents()->create([
                    'category' => 'customer_upload',
                    'name' => $data['name'] ?? 'Customer upload',
                    'uploaded_by' => auth()->id(),
                ]);
                $doc->addMediaFromDisk($data['file'], 'local')->toMediaCollection('file');

                Notification::make()->title('File uploaded — thank you!')->success()->send();
            });
    }
}
