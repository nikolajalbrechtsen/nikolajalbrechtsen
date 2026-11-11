<x-layouts.site :title="setting('blog_title', 'Blog')">
    <section class="section">
        <div class="max-w-2xl">
            <p class="eyebrow">{{ setting('blog_title', 'Blog') }}</p>
            <h1 class="mt-2 text-4xl font-bold tracking-tight text-zinc-900 sm:text-5xl dark:text-white">
                {{ $tag ? '#'.$tag : 'Writing & notes' }}
            </h1>
            @if (setting('blog_intro'))
                <p class="mt-4 text-lg text-zinc-600 dark:text-zinc-400">{{ setting('blog_intro') }}</p>
            @endif
            @if ($tag)
                <a href="{{ route('blog.index') }}" class="mt-4 inline-block text-sm font-medium text-brand">← All posts</a>
            @endif
        </div>

        <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($posts as $post)
                <x-site.post-card :post="$post" />
            @empty
                <p class="text-zinc-500">No posts yet.</p>
            @endforelse
        </div>

        <div class="mt-12">{{ $posts->links() }}</div>
    </section>
</x-layouts.site>
