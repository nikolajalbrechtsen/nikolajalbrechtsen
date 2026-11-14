@php $blogTitle = setting_t('blog_title', 'Blog'); @endphp
<x-layouts.site :title="$blogTitle">
    <section class="section">
        <div class="max-w-2xl">
            <p class="eyebrow">{{ $blogTitle }}</p>
            <h1 class="mt-2 text-4xl font-bold tracking-tight text-zinc-900 sm:text-5xl dark:text-white">
                {{ $tag ? '#'.$tag : __('Writing & notes') }}
            </h1>
            @if ($intro = setting_t('blog_intro'))
                <p class="mt-4 text-lg text-zinc-600 dark:text-zinc-400">{{ $intro }}</p>
            @endif
            @if ($tag)
                <a href="{{ route('blog.index') }}" class="mt-4 inline-block text-sm font-medium text-brand">← {{ __('All posts') }}</a>
            @endif
        </div>

        <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($posts as $post)
                <x-site.post-card :post="$post" />
            @empty
                <p class="text-zinc-500">{{ __('No posts yet.') }}</p>
            @endforelse
        </div>

        <div class="mt-12">{{ $posts->links() }}</div>
    </section>
</x-layouts.site>
