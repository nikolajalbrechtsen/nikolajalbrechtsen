<section class="mx-auto max-w-2xl px-5 py-16 sm:px-8 sm:py-24">
    <div class="text-center">
        <h2 class="text-4xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ $data['heading'] ?? 'Get in touch' }}</h2>
        @if (! empty($data['text']))
            <p class="mt-4 text-lg text-zinc-600 dark:text-zinc-400">{{ $data['text'] }}</p>
        @endif
    </div>
    <div class="card mt-10 sm:p-8">
        <livewire:contact-form />
    </div>
</section>
