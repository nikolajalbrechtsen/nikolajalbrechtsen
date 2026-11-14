<section class="mx-auto max-w-3xl px-5 py-16 sm:px-8">
    @if (! empty($data['heading']))
        <h2 class="mb-8 text-4xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ $data['heading'] }}</h2>
    @endif
    <div class="prose prose-zinc prose-lg max-w-none dark:prose-invert prose-headings:tracking-tight prose-a:text-brand prose-img:rounded-2xl">
        {{ rich($data['content'] ?? null) }}
    </div>
</section>
