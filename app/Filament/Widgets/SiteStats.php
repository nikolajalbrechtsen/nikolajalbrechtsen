<?php

namespace App\Filament\Widgets;

use App\Models\ContactMessage;
use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SiteStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $newUsers = User::where('created_at', '>=', now()->subDays(30))->count();
        $unread = ContactMessage::whereNull('read_at')->count();

        return [
            Stat::make('Users', User::count())
                ->description($newUsers.' new in the last 30 days')
                ->descriptionIcon(Heroicon::OutlinedArrowTrendingUp)
                ->color('success'),
            Stat::make('Pages', Page::count())
                ->description(Page::where('is_published', false)->count().' unpublished')
                ->descriptionIcon(Heroicon::OutlinedDocumentDuplicate),
            Stat::make('Blog posts', Post::published()->count())
                ->description(Post::whereNull('published_at')->count().' drafts')
                ->descriptionIcon(Heroicon::OutlinedNewspaper),
            Stat::make('Unread messages', $unread)
                ->descriptionIcon(Heroicon::OutlinedInbox)
                ->description($unread ? 'Waiting for a reply' : 'Inbox zero 🎉')
                ->color($unread ? 'warning' : 'gray'),
        ];
    }
}
