<x-layouts.site :title="$post->meta_title ?: $post->title" :description="$post->meta_description ?: $post->summary()" :image="$post->coverUrl()">
    <article class="mx-auto max-w-3xl px-5 py-16 sm:px-8 sm:py-24">
        <a href="{{ route('blog.index') }}" class="text-sm font-medium text-zinc-500 hover:text-brand">← {{ setting('blog_title', 'Blog') }}</a>

        <header class="mt-6">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-zinc-500">
                <time datetime="{{ $post->published_at->toDateString() }}">{{ $post->published_at->format('F j, Y') }}</time>
                <span>·</span>
                <span>{{ $post->readingTime() }} min read</span>
                @if ($post->isMembersOnly())
                    <span class="rounded-full bg-amber-500/10 px-2 py-0.5 text-xs font-medium text-amber-600">Members only</span>
                @endif
            </div>
            <h1 class="mt-3 text-4xl font-bold tracking-tight text-balance text-zinc-900 sm:text-5xl dark:text-white">{{ $post->title }}</h1>
            @if ($post->excerpt)
                <p class="mt-4 text-xl text-zinc-600 dark:text-zinc-400">{{ $post->excerpt }}</p>
            @endif
            @if ($post->author)
                <div class="mt-6 flex items-center gap-3">
                    @if ($post->author->avatarUrl())
                        <img src="{{ $post->author->avatarUrl() }}" alt="" class="size-10 rounded-full object-cover">
                    @else
                        <span class="grid size-10 place-items-center rounded-full bg-brand/15 text-sm font-bold text-brand">{{ $post->author->initials() }}</span>
                    @endif
                    <span class="text-sm font-medium text-zinc-900 dark:text-white">{{ $post->author->name }}</span>
                </div>
            @endif
        </header>

        @if ($post->coverUrl())
            <img src="{{ $post->coverUrl() }}" alt="" class="mt-10 aspect-[16/9] w-full rounded-3xl object-cover shadow-xl">
        @endif

        <div class="prose prose-zinc prose-lg mt-10 max-w-none dark:prose-invert prose-headings:tracking-tight prose-a:text-brand prose-img:rounded-2xl">
            {{ rich($post->content) }}
        </div>

        @if ($post->tags)
            <div class="mt-10 flex flex-wrap gap-2">
                @foreach ($post->tags as $t)
                    <a href="{{ route('blog.index', ['tag' => $t]) }}" class="rounded-full bg-zinc-900/5 px-3 py-1 text-sm font-medium hover:bg-brand/10 hover:text-brand dark:bg-white/10">#{{ $t }}</a>
                @endforeach
            </div>
        @endif
    </article>

    @if ($related->isNotEmpty())
        <section class="mx-auto max-w-6xl px-5 sm:px-8">
            <h2 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">More posts</h2>
            <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($related as $p)
                    <x-site.post-card :post="$p" />
                @endforeach
            </div>
        </section>
    @endif
</x-layouts.site>
