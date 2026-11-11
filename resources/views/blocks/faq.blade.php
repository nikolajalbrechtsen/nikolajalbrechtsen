<section class="mx-auto max-w-3xl px-5 py-16 sm:px-8 sm:py-24">
    @if (! empty($data['heading']))
        <h2 class="text-center text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl dark:text-white">{{ $data['heading'] }}</h2>
    @endif
    <div class="mt-12 divide-y divide-zinc-200 dark:divide-white/10">
        @foreach ($data['items'] ?? [] as $item)
            <details class="group py-5">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-4 text-lg font-medium text-zinc-900 dark:text-white">
                    {{ $item['question'] ?? '' }}
                    <span class="grid size-7 shrink-0 place-items-center rounded-full bg-zinc-900/5 transition group-open:rotate-45 dark:bg-white/10">+</span>
                </summary>
                <p class="mt-3 whitespace-pre-line text-zinc-600 dark:text-zinc-400">{{ $item['answer'] ?? '' }}</p>
            </details>
        @endforeach
    </div>
</section>
