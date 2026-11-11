<?php

namespace App\Filament\Resources\Visitors;

use App\Models\Visit;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * All unique visitors (one row per IP), with where they come from.
 */
class VisitorResource extends Resource
{
    protected static ?string $model = Visit::class;

    protected static ?string $slug = 'visitors';

    protected static ?string $modelLabel = 'visitor';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Visitors';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('view_visitors');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /** One row per visitor: the per-day rows grouped by the visitor hash. */
    public static function getEloquentQuery(): Builder
    {
        return Visit::query()
            ->select('ip_hash')
            ->selectRaw('MIN(id) as id')
            ->selectRaw('MAX(ip) as ip')
            ->selectRaw('MAX(country) as country')
            ->selectRaw('MAX(region) as region')
            ->selectRaw('MAX(city) as city')
            ->selectRaw('MAX(referrer) as referrer')
            ->selectRaw('MAX(user_agent) as user_agent')
            ->selectRaw('SUM(hits) as total_hits')
            ->selectRaw('COUNT(*) as days')
            ->selectRaw('MIN(created_at) as first_seen')
            ->selectRaw('MAX(updated_at) as last_seen')
            ->groupBy('ip_hash');
    }

    public static function table(Table $table): Table
    {
        $sortBy = fn (string $alias) => fn (Builder $query, string $direction) => $query->orderBy($alias, $direction);

        return $table
            ->defaultSort(fn (Builder $query) => $query->orderByDesc('last_seen'))
            ->columns([
                TextColumn::make('ip')
                    ->label('IP address')
                    ->placeholder('Not recorded')
                    ->copyable()
                    ->fontFamily('mono')
                    ->searchable(query: fn (Builder $query, string $search) => $query->where('ip', 'like', "%{$search}%")),
                TextColumn::make('location')
                    ->label('Location')
                    ->state(fn (Visit $record) => Visit::formatLocation($record->country, $record->region, $record->city))
                    ->placeholder('Unknown')
                    ->searchable(query: fn (Builder $query, string $search) => $query->where(fn (Builder $q) => $q
                        ->where('city', 'like', "%{$search}%")
                        ->orWhere('region', 'like', "%{$search}%")
                        ->orWhere('country', 'like', "%{$search}%"))),
                TextColumn::make('device')
                    ->label('Device')
                    ->state(fn (Visit $record) => Visit::describeAgent($record->user_agent))
                    ->tooltip(fn (Visit $record) => $record->user_agent)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('referrer')
                    ->label('Came from')
                    ->formatStateUsing(fn (?string $state) => $state ? (parse_url($state, PHP_URL_HOST) ?: $state) : null)
                    ->url(fn (Visit $record) => $record->referrer, shouldOpenInNewTab: true)
                    ->placeholder('Direct')
                    ->toggleable(),
                TextColumn::make('total_hits')
                    ->label('Page views')
                    ->numeric()
                    ->alignEnd()
                    ->sortable(query: $sortBy('total_hits')),
                TextColumn::make('days')
                    ->label('Days visited')
                    ->numeric()
                    ->alignEnd()
                    ->sortable(query: $sortBy('days'))
                    ->toggleable(),
                TextColumn::make('first_seen')
                    ->label('First visit')
                    ->dateTime()
                    ->sortable(query: $sortBy('first_seen'))
                    ->toggleable(),
                TextColumn::make('last_seen')
                    ->label('Last visit')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(query: $sortBy('last_seen')),
            ])
            ->filters([
                SelectFilter::make('period')
                    ->label('Visited')
                    ->options([
                        'today' => 'Today',
                        '7' => 'Last 7 days',
                        '30' => 'Last 30 days',
                    ])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'today' => $query->where('date', now()->toDateString()),
                        '7' => $query->where('date', '>=', now()->subDays(6)->toDateString()),
                        '30' => $query->where('date', '>=', now()->subDays(29)->toDateString()),
                        default => $query,
                    }),
                SelectFilter::make('country')
                    ->label('Country')
                    ->options(fn () => DB::table('visits')->whereNotNull('country')->distinct()->orderBy('country')->pluck('country')
                        ->mapWithKeys(fn (string $code) => [$code => Visit::formatLocation($code) ?? $code])
                        ->all())
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null)
                        ? $query->where('country', $data['value'])
                        : $query),
            ])
            ->recordActions([
                Action::make('lookup')
                    ->label('Look up IP')
                    ->icon(Heroicon::OutlinedMapPin)
                    ->url(fn (Visit $record) => 'https://ipinfo.io/'.urlencode((string) $record->ip), shouldOpenInNewTab: true)
                    ->visible(fn (Visit $record) => filled($record->ip)),
                DeleteAction::make()
                    ->label('Forget')
                    ->modalHeading('Forget this visitor?')
                    ->modalDescription('Deletes every visit recorded for this IP.')
                    ->using(fn (Visit $record) => Visit::where('ip_hash', $record->ip_hash)->delete()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVisitors::route('/'),
        ];
    }
}
