@php
    $posts = setting('blog_enabled', true)
        ? \App\Models\Post::published()->latest('published_at')->limit((int) ($data['count'] ?? 3))->get()
        : collect();
@endphp
@if ($posts->isNotEmpty())
    <section class="section">
        <div class="flex items-end justify-between gap-4">
            <h2 class="text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl dark:text-white">{{ $data['heading'] ?? 'Latest posts' }}</h2>
            <a href="{{ route('blog.index') }}" class="shrink-0 text-sm font-semibold text-brand">View all →</a>
        </div>
        <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($posts as $post)
                <x-site.post-card :post="$post" />
            @endforeach
        </div>
    </section>
@endif
