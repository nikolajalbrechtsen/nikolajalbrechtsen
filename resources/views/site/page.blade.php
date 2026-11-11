<x-layouts.site :title="$page->is_homepage ? null : ($page->meta_title ?: $page->title)" :description="$page->meta_description">
    @forelse ($page->blocks ?? [] as $block)
        @includeIf('blocks.'.$block['type'], ['data' => $block['data'] ?? []])
    @empty
        <section class="section">
            <h1 class="text-4xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ $page->title }}</h1>
            <p class="mt-4 text-zinc-500">This page has no content yet. Add blocks in the admin panel.</p>
        </section>
    @endforelse
</x-layouts.site>
