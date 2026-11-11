<section class="section">
    @if (! empty($data['heading']))
        <div class="mx-auto max-w-2xl text-center">
            <h2 class="text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl dark:text-white">{{ $data['heading'] }}</h2>
            @if (! empty($data['subheading']))
                <p class="mt-4 text-lg text-zinc-600 dark:text-zinc-400">{{ $data['subheading'] }}</p>
            @endif
        </div>
    @endif
    <div class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($data['items'] ?? [] as $item)
            <div class="card group transition hover:-translate-y-1 hover:shadow-xl">
                <div class="grid size-12 place-items-center rounded-2xl bg-brand/10 text-brand transition group-hover:bg-brand group-hover:text-white">
                    <flux:icon :icon="array_key_exists($item['icon'] ?? '', \App\Filament\Blocks\PageBlocks::ICONS) ? $item['icon'] : 'sparkles'" variant="outline" class="size-6" />
                </div>
                <h3 class="mt-5 text-lg font-semibold text-zinc-900 dark:text-white">{{ $item['title'] ?? '' }}</h3>
                <p class="mt-2 text-zinc-600 dark:text-zinc-400">{{ $item['text'] ?? '' }}</p>
            </div>
        @endforeach
    </div>
</section>
