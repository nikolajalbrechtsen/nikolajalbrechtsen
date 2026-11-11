<?php

namespace App\Filament\Resources\Visitors\Pages;

use App\Filament\Resources\Visitors\VisitorResource;
use App\Filament\Widgets\VisitorStats;
use Filament\Resources\Pages\ListRecords;

class ListVisitors extends ListRecords
{
    protected static string $resource = VisitorResource::class;

    protected ?string $subheading = 'Everyone who visited the public website, one row per IP address.';

    protected function getHeaderWidgets(): array
    {
        return [VisitorStats::class];
    }
}
