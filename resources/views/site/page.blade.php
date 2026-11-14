@php $title = $page->translate('title'); @endphp
<x-layouts.site :title="$page->is_homepage ? null : ($page->translate('meta_title') ?: $title)" :description="$page->translate('meta_description')">
    @forelse ($page->translate('blocks') ?? [] as $block)
        @includeIf('blocks.'.$block['type'], ['data' => $block['data'] ?? []])
    @empty
        <section class="section">
            <h1 class="text-4xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ $title }}</h1>
            <p class="mt-4 text-zinc-500">{{ __('This page has no content yet. Add blocks in the admin panel.') }}</p>
        </section>
    @endforelse
</x-layouts.site>
