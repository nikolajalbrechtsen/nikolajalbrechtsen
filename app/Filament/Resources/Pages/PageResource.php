<?php

namespace App\Filament\Resources\Pages;

use App\Filament\Blocks\PageBlocks;
use App\Models\Page;
use App\Support\Locales;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('manage_pages');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Group::make()->columnSpan(['lg' => 2])->schema([
                    Tabs::make('language')->contained(false)->tabs([
                        Tab::make(Locales::all()[Locales::default()])->schema([
                            Section::make()->schema([
                                TextInput::make('title')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (?string $state, Set $set, string $operation) {
                                        if ($operation === 'create') {
                                            $set('slug', Str::slug((string) $state));
                                        }
                                    }),
                            ]),
                            Section::make('Page content')
                                ->description('Add, reorder and edit blocks. Drag the handle to move a block.')
                                ->schema([
                                    Builder::make('blocks')
                                        ->hiddenLabel()
                                        ->blocks(PageBlocks::all())
                                        ->collapsible()
                                        ->cloneable()
                                        ->blockNumbers(false)
                                        ->addActionLabel('Add block'),
                                ]),
                        ]),
                        ...collect(Locales::translations())->map(fn (string $name, string $locale) => static::translationTab($locale, $name))->values()->all(),
                    ]),
                ]),

                Group::make()->columnSpan(['lg' => 1])->schema([
                    Section::make('Publishing')->schema([
                        Toggle::make('is_published')->label('Published')->default(true),
                        Radio::make('visibility')
                            ->options([
                                'public' => 'Everyone',
                                'members' => 'Logged-in members only',
                            ])
                            ->default('public')
                            ->required(),
                        Toggle::make('is_homepage')->label('Use as homepage'),
                    ]),
                    Section::make('Navigation')->schema([
                        TextInput::make('slug')
                            ->label('URL')
                            ->prefix('/')
                            ->required()
                            ->alphaDash()
                            ->unique(ignoreRecord: true)
                            ->notIn(['admin', 'blog', 'login', 'register', 'dashboard', 'settings', 'code', 'auth', 'livewire', 'storage', 'up']),
                        Toggle::make('show_in_nav')->label('Show in menu')->default(true),
                        TextInput::make('nav_order')->label('Menu position')->numeric()->default(0),
                    ]),
                    Section::make('SEO')->collapsed()->schema([
                        TextInput::make('meta_title')->placeholder('Defaults to the page title'),
                        Textarea::make('meta_description')->rows(3),
                    ]),
                ]),
            ]);
    }

    /** The page's content in another language. Empty fields fall back to Danish on the website. */
    protected static function translationTab(string $locale, string $name): Tab
    {
        $default = Locales::all()[Locales::default()];

        return Tab::make($name)
            ->badge(fn (?Page $record) => $record?->hasTranslation($locale) ? null : 'Not translated')
            ->badgeColor('gray')
            ->schema([
                Section::make()->schema([
                    TextInput::make("translations.{$locale}.title")
                        ->label('Title')
                        ->placeholder(fn (Get $get) => $get('title')),
                ]),
                Section::make('Page content')
                    ->description("Leave empty to show the {$default} content to visitors who chose {$name}.")
                    ->headerActions([
                        Action::make("copy_blocks_{$locale}")
                            ->label("Copy blocks from {$default}")
                            ->icon(Heroicon::OutlinedDocumentDuplicate)
                            ->link()
                            ->requiresConfirmation(fn (Get $get) => filled($get("translations.{$locale}.blocks")))
                            ->modalDescription("This replaces the current {$name} blocks with a copy of the {$default} ones.")
                            ->action(fn (Get $get, Set $set) => $set("translations.{$locale}.blocks", $get('blocks'))),
                    ])
                    ->schema([
                        Builder::make("translations.{$locale}.blocks")
                            ->hiddenLabel()
                            ->blocks(PageBlocks::all($locale))
                            ->collapsible()
                            ->cloneable()
                            ->blockNumbers(false)
                            ->addActionLabel('Add block'),
                    ]),
                Section::make('SEO')->collapsed()->schema([
                    TextInput::make("translations.{$locale}.meta_title")->label('Meta title')->placeholder('Defaults to the page title'),
                    Textarea::make("translations.{$locale}.meta_description")->label('Meta description')->rows(3),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('nav_order')
            ->reorderable('nav_order')
            ->columns([
                TextColumn::make('title')->searchable()->sortable()->weight('medium')
                    ->description(fn (Page $record) => $record->is_homepage ? 'Homepage' : '/'.$record->slug),
                TextColumn::make('visibility')->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'members' ? 'Members' : 'Public')
                    ->color(fn (string $state) => $state === 'members' ? 'warning' : 'gray'),
                TextColumn::make('languages')->label('Languages')->badge()->color('gray')
                    ->state(fn (Page $record) => array_map('strtoupper', $record->availableLocales())),
                ToggleColumn::make('is_published')->label('Published'),
                IconColumn::make('show_in_nav')->label('In menu')->boolean(),
                TextColumn::make('updated_at')->since()->sortable()->label('Updated'),
            ])
            ->recordActions([
                Action::make('view')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (Page $record) => $record->url())
                    ->openUrlInNewTab(),
                EditAction::make(),
                ReplicateAction::make()
                    ->label('Duplicate')
                    ->excludeAttributes(['is_homepage'])
                    ->mutateRecordDataUsing(function (array $data) {
                        $data['title'] .= ' (copy)';
                        $data['slug'] .= '-copy-'.Str::lower(Str::random(4));
                        $data['is_published'] = false;

                        return $data;
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPages::route('/'),
            'create' => Pages\CreatePage::route('/create'),
            'edit' => Pages\EditPage::route('/{record}/edit'),
        ];
    }
}
