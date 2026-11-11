<?php

namespace App\Filament\Resources\Media\Pages;

use App\Filament\Resources\Media\MediaResource;
use App\Models\Media;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;

class ListMedia extends ListRecords
{
    protected static string $resource = MediaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')
                ->label('Find existing uploads')
                ->icon(Heroicon::OutlinedMagnifyingGlass)
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Adds files that were uploaded elsewhere (pages, posts, settings) to the media library.')
                ->action(function () {
                    $known = Media::pluck('path')->flip();
                    $added = 0;
                    foreach (Storage::disk('public')->allFiles() as $path) {
                        if (isset($known[$path]) || str_starts_with(basename($path), '.') || str_starts_with($path, 'livewire-tmp/')) {
                            continue;
                        }
                        Media::fromPath($path, folder: dirname($path) === '.' ? null : dirname($path));
                        $added++;
                    }
                    Notification::make()->title($added ? "Added {$added} files" : 'No new files found')->success()->send();
                }),

            Action::make('upload')
                ->label('Upload')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->modalHeading('Upload files')
                ->modalWidth('2xl')
                ->schema([
                    FileUpload::make('files')
                        ->hiddenLabel()
                        ->multiple()
                        ->disk('public')
                        ->directory('media/'.now()->format('Y/m'))
                        ->storeFileNamesIn('names')
                        ->maxSize(50 * 1024)
                        ->panelLayout('grid')
                        ->reorderable(false)
                        ->required(),
                    TextInput::make('folder')
                        ->datalist(fn () => array_values(MediaResource::folderOptions()))
                        ->placeholder('No folder — type a name to create one')
                        ->dehydrateStateUsing(fn (?string $state) => filled($state) ? trim($state) : null)
                        ->maxLength(100),
                ])
                ->action(function (array $data) {
                    foreach ($data['files'] ?? [] as $path) {
                        Media::fromPath($path, $data['names'][$path] ?? null, $data['folder'] ?? null);
                    }
                    Notification::make()->title(count($data['files'] ?? []).' files uploaded')->success()->send();
                }),
        ];
    }
}
