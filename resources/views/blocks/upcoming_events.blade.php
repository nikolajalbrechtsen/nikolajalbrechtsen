@php
    $events = \App\Models\Event::query()
        ->where('is_public', true)
        ->upcoming()
        ->orderBy('starts_at')
        ->limit((int) ($data['count'] ?? 5))
        ->get();
@endphp

<section class="mx-auto max-w-4xl px-5 py-16 sm:px-8 sm:py-24">
    @if (! empty($data['heading']))
        <h2 class="text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl dark:text-white">{{ $data['heading'] }}</h2>
    @endif

    <div class="mt-10 flex flex-col gap-4">
        @forelse ($events as $event)
            <div class="card flex items-start gap-5 !p-5">
                <div class="flex w-16 shrink-0 flex-col items-center rounded-2xl py-2 text-white" style="background: {{ $event->hex() }}">
                    <span class="text-xs font-semibold uppercase opacity-90">{{ $event->starts_at->format('M') }}</span>
                    <span class="text-2xl font-extrabold leading-none">{{ $event->starts_at->format('j') }}</span>
                </div>
                <div class="min-w-0 flex-1">
                    <h3 class="text-lg font-semibold text-zinc-900 dark:text-white">
                        @if ($event->url)
                            <a href="{{ $event->url }}" target="_blank" rel="noopener" class="hover:text-brand">{{ $event->title }} ↗</a>
                        @else
                            {{ $event->title }}
                        @endif
                    </h3>
                    <p class="mt-1 text-sm text-zinc-500">
                        @if ($event->all_day)
                            {{ $event->starts_at->format('l, F j') }}@if ($event->ends_at && ! $event->ends_at->isSameDay($event->starts_at)) – {{ $event->ends_at->format('F j') }}@endif
                        @else
                            {{ $event->starts_at->format('l, F j · H:i') }}@if ($event->ends_at) – {{ $event->ends_at->isSameDay($event->starts_at) ? $event->ends_at->format('H:i') : $event->ends_at->format('F j H:i') }}@endif
                        @endif
                        @if ($event->location) · 📍 {{ $event->location }} @endif
                    </p>
                    @if ($event->description)
                        <p class="mt-2 whitespace-pre-line text-zinc-600 dark:text-zinc-400">{{ $event->description }}</p>
                    @endif
                </div>
            </div>
        @empty
            <p class="text-zinc-500">{{ $data['empty_text'] ?? 'No upcoming events right now.' }}</p>
        @endforelse
    </div>
</section>
