@php
    $user = auth()->user();
    $memberPages = \App\Models\Page::published()->where('visibility', 'members')->orderBy('nav_order')->get();
    $memberPosts = setting('blog_enabled', true)
        ? \App\Models\Post::published()->where('visibility', 'members')->latest('published_at')->limit(6)->get()
        : collect();
@endphp

<x-layouts.app :title="__('My account')">
    <div class="flex w-full flex-1 flex-col gap-6">
        <div class="flex flex-wrap items-center gap-4 rounded-2xl border border-zinc-200 p-6 dark:border-zinc-700">
            @if ($user->avatarUrl())
                <img src="{{ $user->avatarUrl() }}" alt="" class="size-16 rounded-full object-cover">
            @else
                <span class="grid size-16 place-items-center rounded-full bg-brand/15 text-xl font-bold text-brand">{{ $user->initials() }}</span>
            @endif
            <div class="flex-1">
                <flux:heading size="xl">{{ __('Welcome back, :name!', ['name' => Str::before($user->name, ' ')]) }}</flux:heading>
                <flux:text>{{ __('Member since :date', ['date' => $user->created_at->isoFormat('MMMM YYYY')]) }} · {{ $user->getRoleNames()->map(fn ($r) => Str::headline($r))->join(', ') ?: __('Member') }}</flux:text>
            </div>
            @can('access_admin')
                <flux:button href="{{ url('/admin') }}" variant="primary" icon="shield-check">{{ __('Open admin panel') }}</flux:button>
            @endcan
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="rounded-2xl border border-zinc-200 p-6 dark:border-zinc-700">
                <flux:heading size="lg">{{ __('Members-only pages') }}</flux:heading>
                <div class="mt-4 flex flex-col gap-2">
                    @forelse ($memberPages as $p)
                        <a href="{{ $p->url() }}" class="flex items-center justify-between rounded-xl px-4 py-3 ring-1 ring-zinc-200 transition hover:bg-zinc-50 dark:ring-zinc-700 dark:hover:bg-white/5">
                            <span class="font-medium">{{ $p->translate('title') }}</span><span aria-hidden="true">→</span>
                        </a>
                    @empty
                        <flux:text>{{ __('Nothing here yet.') }}</flux:text>
                    @endforelse
                </div>
            </div>

            <div class="rounded-2xl border border-zinc-200 p-6 dark:border-zinc-700">
                <flux:heading size="lg">{{ __('Members-only posts') }}</flux:heading>
                <div class="mt-4 flex flex-col gap-2">
                    @forelse ($memberPosts as $post)
                        <a href="{{ route('blog.show', $post->slug) }}" class="flex items-center justify-between gap-4 rounded-xl px-4 py-3 ring-1 ring-zinc-200 transition hover:bg-zinc-50 dark:ring-zinc-700 dark:hover:bg-white/5">
                            <span class="font-medium">{{ $post->translate('title') }}</span>
                            <span class="shrink-0 text-xs text-zinc-500">{{ $post->published_at->isoFormat(__('MMM D')) }}</span>
                        </a>
                    @empty
                        <flux:text>{{ __('Nothing here yet.') }}</flux:text>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
