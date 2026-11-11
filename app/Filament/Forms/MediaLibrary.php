<?php

namespace App\Filament\Forms;

use App\Filament\Forms\Components\MediaGrid;
use App\Filament\Resources\Media\MediaResource;
use App\Models\Media;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;

/**
 * The "choose from media library / upload from PC" modal, shared by the
 * MediaPicker field and the rich-text editor's image button.
 */
class MediaLibrary
{
    /** Toolbar for rich editors: the image button opens the media library. */
    public const TOOLBAR = [
        ['bold', 'italic', 'underline', 'strike', 'link'],
        ['h2', 'h3'],
        ['alignStart', 'alignCenter', 'alignEnd'],
        ['blockquote', 'codeBlock', 'bulletList', 'orderedList'],
        ['table', 'mediaLibrary'],
        ['undo', 'redo'],
    ];

    public static function schema(bool $imagesOnly = true): array
    {
        return [
            Tabs::make('source')->contained(false)->tabs([
                Tab::make('Media library')
                    ->icon(Heroicon::OutlinedPhoto)
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 3])->schema([
                            TextInput::make('search')
                                ->hiddenLabel()
                                ->placeholder('Search by name…')
                                ->prefixIcon(Heroicon::OutlinedMagnifyingGlass)
                                ->live(debounce: 400)
                                ->columnSpan(['sm' => 2])
                                ->dehydrated(false),
                            Select::make('filter_folder')
                                ->hiddenLabel()
                                ->placeholder('All folders')
                                ->options(fn () => MediaResource::folderOptions())
                                ->live()
                                ->dehydrated(false),
                        ]),
                        MediaGrid::make('media_id')->hiddenLabel()->imagesOnly($imagesOnly),
                    ]),
                Tab::make('Upload from PC')
                    ->icon(Heroicon::OutlinedArrowUpTray)
                    ->schema([
                        FileUpload::make('upload')
                            ->hiddenLabel()
                            ->disk('public')
                            ->directory('media/'.now()->format('Y/m'))
                            ->storeFileNamesIn('upload_name')
                            ->image($imagesOnly)
                            ->imageEditor($imagesOnly)
                            ->maxSize(50 * 1024)
                            ->helperText('The file is saved in the media library so you can reuse it later.'),
                        TextInput::make('upload_folder')
                            ->label('Folder (optional)')
                            ->datalist(fn () => array_values(MediaResource::folderOptions())),
                    ]),
            ]),
        ];
    }

    /** Returns the chosen or freshly uploaded media item, or null if nothing was picked. */
    public static function resolve(array $data): ?Media
    {
        if (filled($data['upload'] ?? null)) {
            $path = is_array($data['upload']) ? reset($data['upload']) : $data['upload'];
            $names = (array) ($data['upload_name'] ?? []);

            return Media::fromPath($path, $names[$path] ?? (is_string($data['upload_name'] ?? null) ? $data['upload_name'] : null), filled($data['upload_folder'] ?? null) ? trim($data['upload_folder']) : null);
        }

        if (filled($data['media_id'] ?? null)) {
            return Media::find($data['media_id']);
        }

        Notification::make()->title('Pick a file from the library or upload one')->warning()->send();

        return null;
    }
}
