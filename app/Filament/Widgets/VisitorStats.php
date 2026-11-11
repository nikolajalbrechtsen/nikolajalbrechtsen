<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Visitors\VisitorResource;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class VisitorStats extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    protected int|array|null $columns = 3;

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $today = now()->toDateString();

        $total = DB::table('visits')->distinct()->count('ip_hash');
        $todayCount = DB::table('visits')->where('date', $today)->count();
        $yesterday = DB::table('visits')->where('date', now()->subDay()->toDateString())->count();
        $week = DB::table('visits')->where('date', '>=', now()->subDays(6)->toDateString())->distinct()->count('ip_hash');

        // Unique visitors per day for the last 14 days (for the trend lines).
        $perDay = DB::table('visits')
            ->where('date', '>=', now()->subDays(13)->toDateString())
            ->selectRaw('date, count(*) as visitors')
            ->groupBy('date')
            ->pluck('visitors', 'date');
        $chart = collect(range(13, 0))
            ->map(fn ($daysAgo) => (int) ($perDay[now()->subDays($daysAgo)->toDateString()] ?? 0))
            ->all();

        $diff = $todayCount - $yesterday;

        return [
            Stat::make('Total visitors', number_format($total))
                ->description('Unique by IP, all time')
                ->descriptionIcon(Heroicon::OutlinedUsers)
                ->chart($chart)
                ->color('primary')
                ->url(VisitorResource::canAccess() ? VisitorResource::getUrl() : null),
            Stat::make('Visitors today', number_format($todayCount))
                ->description(match (true) {
                    $diff > 0 => "{$diff} more than yesterday",
                    $diff < 0 => abs($diff).' fewer than yesterday',
                    default => 'Same as yesterday',
                })
                ->descriptionIcon($diff >= 0 ? Heroicon::OutlinedArrowTrendingUp : Heroicon::OutlinedArrowTrendingDown)
                ->color($diff >= 0 ? 'success' : 'danger'),
            Stat::make('Last 7 days', number_format($week))
                ->description('Unique visitors')
                ->descriptionIcon(Heroicon::OutlinedCalendarDays)
                ->chart(array_slice($chart, -7))
                ->color('info'),
        ];
    }
}
