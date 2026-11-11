<?php

namespace App\Filament\Resources\Media;

use App\Models\Media;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\View;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Js;
use UnitEnum;

class MediaResource extends Resource
{
    protected static ?string $model = Media::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Media';

    protected static ?string $modelLabel = 'media file';

    protected static ?string $pluralModelLabel = 'Media';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('manage_media');
    }

    public static function canCreate(): bool
    {
        return false; // files are added with the "Upload" button
    }

    /** @return array<string, string> */
    public static function folderOptions(): array
    {
        return Media::query()->whereNotNull('folder')->distinct()->orderBy('folder')->pluck('folder', 'folder')->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Text::make(fn (?Media $record) => $record?->isImage()
                ? new HtmlString('<img src="'.e($record->url()).'" style="max-height:240px;border-radius:.75rem;margin:auto">')
                : null),
            TextInput::make('name')->required(),
            TextInput::make('alt')->label('Alt text')->helperText('Describes the image for screen readers and search engines.'),
            TextInput::make('folder')
                ->datalist(fn () => array_values(static::folderOptions()))
                ->placeholder('No folder — type a name to create one')
                ->dehydrateStateUsing(fn (?string $state) => filled($state) ? trim($state) : null)
                ->maxLength(100),
            TextInput::make('url')
                ->label('Public URL')
                ->formatStateUsing(fn (?Media $record) => $record?->url())
                ->disabled()
                ->dehydrated(false)
                ->copyable(),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->contentGrid(['default' => 2, 'md' => 3, 'xl' => 5])
            ->paginated([20, 40, 80])
            ->columns([
                View::make('filament.media.card'),
                // Hidden columns used for searching/sorting:
                TextColumn::make('name')->searchable()->sortable()->hidden(),
                TextColumn::make('size')->sortable()->hidden(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(['image' => 'Images', 'video' => 'Videos', 'pdf' => 'PDFs', 'other' => 'Other files'])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'image' => $query->where('mime_type', 'like', 'image/%'),
                        'video' => $query->where('mime_type', 'like', 'video/%'),
                        'pdf' => $query->where('mime_type', 'application/pdf'),
                        'other' => $query->where(fn ($q) => $q->whereNull('mime_type')
                            ->orWhere(fn ($q) => $q->where('mime_type', 'not like', 'image/%')
                                ->where('mime_type', 'not like', 'video/%')
                                ->where('mime_type', '!=', 'application/pdf'))),
                        default => $query,
                    }),
                SelectFilter::make('folder')->options(fn () => static::folderOptions()),
            ])
            ->recordActions([
                Action::make('copyUrl')
                    ->label('Copy URL')
                    ->icon(Heroicon::OutlinedClipboardDocument)
                    ->iconButton()
                    ->tooltip('Copy URL')
                    ->alpineClickHandler(fn (Media $record) => 'window.navigator.clipboard.writeText('.Js::from($record->url()).'); $tooltip("Copied!", { timeout: 1500 })'),
                Action::make('open')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->iconButton()
                    ->tooltip('Open')
                    ->url(fn (Media $record) => $record->url(), shouldOpenInNewTab: true),
                EditAction::make()->iconButton()->modalWidth('lg'),
                DeleteAction::make()->iconButton(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('move')
                        ->label('Move to folder')
                        ->icon(Heroicon::OutlinedFolder)
                        ->schema([
                            TextInput::make('folder')
                                ->datalist(fn () => array_values(static::folderOptions()))
                                ->placeholder('No folder — type a name to create one')
                                ->dehydrateStateUsing(fn (?string $state) => filled($state) ? trim($state) : null)
                                ->maxLength(100),
                        ])
                        ->action(fn (Collection $records, array $data) => $records->each->update(['folder' => $data['folder']])),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMedia::route('/'),
        ];
    }
}
