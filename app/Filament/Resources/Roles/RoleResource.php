<?php

namespace App\Filament\Resources\Roles;

use BackedEnum;
use Database\Seeders\DatabaseSeeder;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;
use UnitEnum;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Users';

    protected static ?string $navigationLabel = 'Roles & permissions';

    protected static ?int $navigationSort = 2;

    /** Roles the system relies on — they cannot be renamed or deleted. */
    public const PROTECTED = ['super_admin', 'member'];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('manage_roles');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                TextInput::make('name')
                    ->required()
                    ->alphaDash()
                    ->unique(ignoreRecord: true)
                    ->disabled(fn (?Role $record) => $record && in_array($record->name, self::PROTECTED)),
                CheckboxList::make('permissions')
                    ->relationship('permissions', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => DatabaseSeeder::PERMISSIONS[$record->name] ?? $record->name)
                    ->columns(2)
                    ->bulkToggleable()
                    ->hidden(fn (?Role $record) => $record?->name === 'super_admin')
                    ->helperText('Users get the combined permissions of all their roles.'),
            ]),
            Section::make()
                ->visible(fn (?Role $record) => $record?->name === 'super_admin')
                ->schema([
                    TextInput::make('info')
                        ->hiddenLabel()
                        ->default('Super admins always have every permission.')
                        ->disabled()
                        ->dehydrated(false),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->formatStateUsing(fn (string $state) => str($state)->replace('_', ' ')->title())->weight('medium'),
                TextColumn::make('permissions_count')->counts('permissions')->label('Permissions')
                    ->formatStateUsing(fn ($state, Role $record) => $record->name === 'super_admin' ? 'All' : $state),
                TextColumn::make('users_count')->counts('users')->label('Users'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->hidden(fn (Role $record) => in_array($record->name, self::PROTECTED)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
