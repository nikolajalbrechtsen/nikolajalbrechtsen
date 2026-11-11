<?php

namespace App\Filament\Resources\Pages;

use App\Filament\Blocks\PageBlocks;
use App\Models\Page;
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
