<section class="section">
    <div class="grid items-center gap-12 lg:grid-cols-2">
        <div class="{{ ! empty($data['image_right']) ? 'lg:order-2' : '' }}">
            <img src="{{ public_url($data['image'] ?? null) }}" alt="" loading="lazy" class="w-full rounded-[2rem] object-cover shadow-2xl ring-1 ring-zinc-900/10 dark:ring-white/10">
        </div>
        <div>
            @if (! empty($data['heading']))
                <h2 class="text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl dark:text-white">{{ $data['heading'] }}</h2>
            @endif
            <div class="prose prose-zinc prose-lg mt-6 max-w-none dark:prose-invert prose-a:text-brand">{{ rich($data['content'] ?? null) }}</div>
            @if (! empty($data['button_label']))
                <a href="{{ $data['button_url'] ?? '#' }}" class="btn-primary mt-8">{{ $data['button_label'] }} <span aria-hidden="true">→</span></a>
            @endif
        </div>
    </div>
</section>
