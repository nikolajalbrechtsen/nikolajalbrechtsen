<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Posts\PostResource;
use App\Models\Event;
use App\Models\Post;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use UnitEnum;

class Calendar extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.calendar';

    protected Width|string|null $maxContentWidth = Width::Full;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('manage_calendar');
    }

    /** Events for the visible range (called by FullCalendar). */
    public function fetchEvents(string $start, string $end): array
    {
        $start = Carbon::parse($start);
        $end = Carbon::parse($end);

        $events = Event::query()
            ->where('starts_at', '<', $end)
            ->where(fn ($q) => $q->where('ends_at', '>=', $start)->orWhere(fn ($q) => $q->whereNull('ends_at')->where('starts_at', '>=', $start)))
            ->get()
            ->map(fn (Event $e) => [
                'id' => (string) $e->id,
                'title' => $e->title,
                'start' => $e->all_day ? $e->starts_at->toDateString() : $e->starts_at->toIso8601String(),
                'end' => $e->ends_at ? ($e->all_day ? $e->ends_at->copy()->addDay()->toDateString() : $e->ends_at->toIso8601String()) : null,
                'allDay' => $e->all_day,
                'color' => $e->hex(),
                'extendedProps' => ['type' => 'event', 'location' => $e->location, 'public' => $e->is_public],
            ]);

        $posts = auth()->user()->can('manage_posts')
            ? Post::query()->whereBetween('published_at', [$start, $end])->get()->map(fn (Post $p) => [
                'id' => 'post-'.$p->id,
                'title' => '📝 '.$p->title,
                'start' => $p->published_at->toIso8601String(),
                'allDay' => false,
                'color' => '#64748b',
                'editable' => false,
                'url' => PostResource::getUrl('edit', ['record' => $p]),
                'extendedProps' => ['type' => 'post'],
            ])
            : collect();

        return $events->concat($posts)->values()->all();
    }

    /** Drag & drop / resize in the calendar. */
    public function moveEvent(int $id, string $start, ?string $end, bool $allDay): void
    {
        $event = Event::findOrFail($id);
        $starts = Carbon::parse($start)->setTimezone(config('app.timezone'));
        $ends = $end ? Carbon::parse($end)->setTimezone(config('app.timezone')) : null;

        if ($allDay && $ends) {
            $ends = $ends->subDay(); // FullCalendar uses an exclusive end date for all-day events
        }

        $event->update(['starts_at' => $starts, 'ends_at' => $ends, 'all_day' => $allDay]);
        Notification::make()->title('Event moved')->success()->send();
    }

    protected function eventForm(): array
    {
        return [
            TextInput::make('title')->required()->autofocus(),
            Toggle::make('all_day')->label('All day')->live(),
            Grid::make(2)->schema([
                DateTimePicker::make('starts_at')->label('Starts')->required()->seconds(false)
                    ->time(fn (Get $get) => ! $get('all_day')),
                DateTimePicker::make('ends_at')->label('Ends')->seconds(false)->afterOrEqual('starts_at')
                    ->time(fn (Get $get) => ! $get('all_day')),
            ]),
            Select::make('color')
                ->options(collect(Event::COLORS)->mapWithKeys(fn ($hex, $name) => [
                    $name => new HtmlString('<span style="display:inline-block;width:.7rem;height:.7rem;border-radius:9999px;background:'.$hex.';margin-right:.4rem"></span>'.ucfirst($name)),
                ])->all())
                ->allowHtml()
                ->default('indigo')
                ->native(false),
            TextInput::make('location'),
            TextInput::make('url')->label('Link')->url(),
            Textarea::make('description')->rows(3),
            Toggle::make('is_public')->label('Show on the website')->helperText('Appears in the "Upcoming events" page block.'),
        ];
    }

    public function createEventAction(): Action
    {
        return Action::make('createEvent')
            ->label('New event')
            ->icon(Heroicon::OutlinedPlus)
            ->modalHeading('New event')
            ->modalWidth('xl')
            ->schema($this->eventForm())
            ->fillForm(function (array $arguments) {
                $allDay = (bool) ($arguments['allDay'] ?? false);
                $start = isset($arguments['start']) ? Carbon::parse($arguments['start'])->setTimezone(config('app.timezone')) : now()->addHour()->startOfHour();
                $end = isset($arguments['end']) ? Carbon::parse($arguments['end'])->setTimezone(config('app.timezone')) : null;
                if ($allDay && $end) {
                    $end = $end->subDay();
                }

                return [
                    'starts_at' => $start,
                    'ends_at' => $allDay && $end?->isSameDay($start) ? null : $end,
                    'all_day' => $allDay,
                    'color' => 'indigo',
                ];
            })
            ->action(function (array $data) {
                Event::create($data + ['user_id' => auth()->id()]);
                Notification::make()->title('Event created')->success()->send();
                $this->dispatch('calendar-refresh');
            });
    }

    public function editEventAction(): Action
    {
        return Action::make('editEvent')
            ->modalHeading('Edit event')
            ->modalWidth('xl')
            ->schema($this->eventForm())
            ->fillForm(fn (array $arguments) => Event::findOrFail($arguments['id'])->toArray())
            ->extraModalFooterActions(fn (Action $action) => [
                $action->makeModalSubmitAction('delete', arguments: ['delete' => true])
                    ->label('Delete')
                    ->color('danger'),
            ])
            ->action(function (array $data, array $arguments) {
                $event = Event::findOrFail($arguments['id']);
                if ($arguments['delete'] ?? false) {
                    $event->delete();
                    Notification::make()->title('Event deleted')->success()->send();
                } else {
                    $event->update($data);
                    Notification::make()->title('Event saved')->success()->send();
                }
                $this->dispatch('calendar-refresh');
            });
    }

    protected function getHeaderActions(): array
    {
        return [$this->createEventAction()];
    }
}
