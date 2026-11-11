<?php

namespace App\Filament\Resources\Posts;

use App\Filament\Forms\Components\MediaPicker;
use App\Filament\Forms\MediaLibrary;
use App\Filament\Forms\MediaLibraryPlugin;
use App\Models\Post;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Blog posts';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'title';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('manage_posts');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Group::make()->columnSpan(['lg' => 2])->schema([
                    Section::make()->schema([
                        TextInput::make('title')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, Set $set, string $operation) {
                                if ($operation === 'create') {
                                    $set('slug', Str::slug((string) $state));
                                }
                            }),
                        Textarea::make('excerpt')->rows(2)->helperText('Short summary shown on the blog overview.'),
                        RichEditor::make('content')
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('posts')
                            ->fileAttachmentsVisibility('public')
                            ->plugins([MediaLibraryPlugin::make()])
                            ->toolbarButtons(MediaLibrary::TOOLBAR)
                            ->extraInputAttributes(['style' => 'min-height: 24rem']),
                    ]),
                ]),
                Group::make()->columnSpan(['lg' => 1])->schema([
                    Section::make('Publishing')->schema([
                        DateTimePicker::make('published_at')
                            ->label('Publish date')
                            ->helperText('Leave empty to keep it as a draft. A future date schedules the post.')
                            ->default(now()),
                        Radio::make('visibility')
                            ->options(['public' => 'Everyone', 'members' => 'Logged-in members only'])
                            ->default('public')
                            ->required(),
                        Select::make('author_id')
                            ->relationship('author', 'name')
                            ->default(fn () => auth()->id())
                            ->searchable()
                            ->preload(),
                    ]),
                    Section::make('Details')->schema([
                        TextInput::make('slug')->label('URL')->prefix('/blog/')->required()->alphaDash()->unique(ignoreRecord: true),
                        MediaPicker::make('cover_image'),
                        TagsInput::make('tags'),
                    ]),
                    Section::make('SEO')->collapsed()->schema([
                        TextInput::make('meta_title')->placeholder('Defaults to the post title'),
                        Textarea::make('meta_description')->rows(3),
                    ]),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('published_at', 'desc')
            ->columns([
                ImageColumn::make('cover_image')->disk('public')->label('')->square()->size(40),
                TextColumn::make('title')->searchable()->sortable()->weight('medium')->limit(60),
                TextColumn::make('status')
                    ->state(fn (Post $record) => match (true) {
                        $record->published_at === null => 'Draft',
                        $record->published_at->isFuture() => 'Scheduled',
                        default => 'Published',
                    })
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'Draft' => 'gray', 'Scheduled' => 'info', default => 'success',
                    }),
                TextColumn::make('visibility')->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'members' ? 'Members' : 'Public')
                    ->color(fn (string $state) => $state === 'members' ? 'warning' : 'gray'),
                TextColumn::make('author.name')->toggleable(),
                TextColumn::make('published_at')->date()->sortable()->label('Date'),
            ])
            ->filters([
                TernaryFilter::make('published_at')->label('Published')->nullable(),
                SelectFilter::make('visibility')->options(['public' => 'Public', 'members' => 'Members']),
            ])
            ->recordActions([
                Action::make('view')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (Post $record) => route('blog.show', $record->slug))
                    ->openUrlInNewTab(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPosts::route('/'),
            'create' => Pages\CreatePost::route('/create'),
            'edit' => Pages\EditPost::route('/{record}/edit'),
        ];
    }
}
