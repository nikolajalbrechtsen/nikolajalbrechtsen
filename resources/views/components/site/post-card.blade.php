@props(['post'])

<a href="{{ route('blog.show', $post->slug) }}" class="group flex flex-col overflow-hidden rounded-3xl border border-zinc-200/70 bg-white/70 shadow-sm backdrop-blur transition hover:-translate-y-1 hover:shadow-xl dark:border-white/10 dark:bg-white/5">
    @if ($post->coverUrl())
        <img src="{{ $post->coverUrl() }}" alt="" loading="lazy" class="aspect-[16/9] w-full object-cover">
    @else
        <div class="aspect-[16/9] w-full bg-gradient-to-br from-brand/30 via-brand/10 to-transparent"></div>
    @endif
    <div class="flex flex-1 flex-col p-6">
        <div class="flex items-center gap-2 text-xs text-zinc-500">
            <time>{{ $post->published_at?->format('M j, Y') }}</time>
            <span>·</span>
            <span>{{ $post->readingTime() }} min</span>
            @if ($post->isMembersOnly())<span class="ml-auto">🔒</span>@endif
        </div>
        <h3 class="mt-2 text-lg font-semibold tracking-tight text-zinc-900 group-hover:text-brand dark:text-white">{{ $post->title }}</h3>
        <p class="mt-2 line-clamp-3 text-sm text-zinc-600 dark:text-zinc-400">{{ $post->summary() }}</p>
    </div>
</a>
