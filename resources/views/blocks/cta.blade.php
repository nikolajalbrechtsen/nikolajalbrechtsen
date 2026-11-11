<section class="mx-auto max-w-6xl px-5 py-16 sm:px-8">
    <div class="relative overflow-hidden rounded-[2.5rem] bg-zinc-900 px-8 py-16 text-center shadow-2xl sm:px-16 dark:bg-white/5 dark:ring-1 dark:ring-white/10">
        <div aria-hidden="true" class="absolute -top-24 left-1/2 h-72 w-[40rem] -translate-x-1/2 rounded-full bg-brand/40 blur-3xl"></div>
        <h2 class="relative text-3xl font-bold tracking-tight text-white sm:text-4xl">{{ $data['heading'] ?? '' }}</h2>
        @if (! empty($data['text']))
            <p class="relative mx-auto mt-4 max-w-xl text-lg text-zinc-300">{{ $data['text'] }}</p>
        @endif
        @if (! empty($data['button_label']))
            <a href="{{ $data['button_url'] ?? '#' }}" class="btn-primary relative mt-8">{{ $data['button_label'] }} <span aria-hidden="true">→</span></a>
        @endif
    </div>
</section>
