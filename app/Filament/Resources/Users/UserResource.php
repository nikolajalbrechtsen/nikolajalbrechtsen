<?php

namespace App\Filament\Resources\Users;

use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Users';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('manage_users');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Profile')->schema([
                    FileUpload::make('avatar')->avatar()->image()->disk('public')->directory('avatars')->imageEditor(),
                    TextInput::make('name')->required()->maxLength(255),
                    TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
                    TextInput::make('password')
                        ->password()
                        ->revealable()
                        ->rule('min:8')
                        ->dehydrated(fn (?string $state) => filled($state))
                        ->dehydrateStateUsing(fn (string $state) => Hash::make($state))
                        ->required(fn (string $operation) => $operation === 'create')
                        ->helperText(fn (string $operation) => $operation === 'edit' ? 'Leave empty to keep the current password.' : null),
                ]),
                Section::make('Access')->schema([
                    Toggle::make('is_active')
                        ->label('Active')
                        ->helperText('Inactive users cannot log in.')
                        ->default(true),
                    DateTimePicker::make('email_verified_at')->label('Email verified at'),
                    CheckboxList::make('roles')
                        ->relationship('roles', 'name')
                        ->getOptionLabelFromRecordUsing(fn ($record) => str($record->name)->replace('_', ' ')->title())
                        ->disableOptionWhen(fn (string $value, $record) => ! auth()->user()->isSuperAdmin()
                            && Role::find($value)?->name === 'super_admin')
                        ->helperText('Roles decide what the user can do in the admin panel. "Member" = normal website user.'),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                ImageColumn::make('avatar')->label('')->circular()->size(36)
                    ->state(fn (User $record) => $record->avatarUrl())
                    ->defaultImageUrl(fn (User $record) => 'https://ui-avatars.com/api/?background=random&name='.urlencode($record->name)),
                TextColumn::make('name')->searchable()->sortable()->weight('medium')->description(fn (User $record) => $record->email),
                TextColumn::make('roles.name')->badge()
                    ->formatStateUsing(fn (string $state) => str($state)->replace('_', ' ')->title())
                    ->color(fn (string $state) => match ($state) {
                        'super_admin' => 'danger', 'admin' => 'warning', 'editor' => 'info', default => 'gray',
                    }),
                TextColumn::make('socialAccounts.provider')->label('Login via')->badge()->color('gray')->toggleable(),
                ToggleColumn::make('is_active')->label('Active')
                    ->disabled(fn (User $record) => $record->is(auth()->user())),
                TextColumn::make('email_verified_at')->label('Verified')->since()->placeholder('Not verified')->toggleable(),
                TextColumn::make('created_at')->label('Joined')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('roles')->relationship('roles', 'name')->multiple()->preload(),
            ])
            ->recordActions([
                Action::make('verify')
                    ->label('Verify email')
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->visible(fn (User $record) => $record->email_verified_at === null)
                    ->action(function (User $record) {
                        $record->forceFill(['email_verified_at' => now()])->save();
                        Notification::make()->title('Email marked as verified')->success()->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->action(fn ($records) => $records->reject(fn (User $u) => $u->is(auth()->user()))->each->delete()),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['roles', 'socialAccounts']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
