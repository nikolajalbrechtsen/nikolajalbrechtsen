@props([
    'title' => null,
    'description' => null,
    'image' => null,
])

@php
    $siteName = setting('site_name', config('app.name'));
    $pageTitle = $title ? $title.' · '.$siteName : $siteName.(setting('site_tagline') ? ' · '.setting('site_tagline') : '');
    $metaDescription = $description ?: setting('site_description');
    $metaImage = $image ?: public_url(setting('meta_image'));
    $metaImage = $metaImage ? url($metaImage) : null; // social networks need an absolute URL
    $logo = public_url(setting('logo'));
    $favicon = public_url(setting('favicon'));
    $user = auth()->user();
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $pageTitle }}</title>
    @if ($metaDescription)<meta name="description" content="{{ $metaDescription }}">@endif
    <meta property="og:title" content="{{ $pageTitle }}">
    @if ($metaDescription)<meta property="og:description" content="{{ $metaDescription }}">@endif
    @if ($metaImage)<meta property="og:image" content="{{ $metaImage }}">@endif
    <meta property="og:url" content="{{ url()->current() }}">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="canonical" href="{{ url()->current() }}">
    @if ($favicon)<link rel="icon" href="{{ $favicon }}">@endif

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet">

    <style>:root { --brand: {{ setting('primary_color', '#6366f1') }}; }</style>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
    {!! setting('analytics_head') !!}
</head>
<body class="min-h-screen bg-white text-zinc-800 antialiased selection:bg-brand/20 dark:bg-zinc-950 dark:text-zinc-200">

    {{-- Soft background glow --}}
    <div aria-hidden="true" class="pointer-events-none fixed inset-x-0 top-0 -z-10 h-[36rem] overflow-hidden">
        <div class="absolute left-1/2 top-[-12rem] h-[36rem] w-[60rem] -translate-x-1/2 rounded-full bg-brand/15 blur-3xl dark:bg-brand/20"></div>
    </div>

    @if ($announcement = setting('announcement'))
        <div class="bg-brand px-4 py-2 text-center text-sm font-medium text-white">{{ $announcement }}</div>
    @endif

    {{-- Header --}}
    <header x-data="{ open: false, userMenu: false }" class="sticky top-0 z-40 border-b border-zinc-200/60 bg-white/70 backdrop-blur-xl dark:border-white/10 dark:bg-zinc-950/70">
        <nav class="mx-auto flex h-16 max-w-6xl items-center gap-6 px-5 sm:px-8">
            <a href="{{ route('home') }}" class="flex items-center gap-2.5 font-bold tracking-tight text-zinc-900 dark:text-white">
                @if ($logo)
                    <img src="{{ $logo }}" alt="{{ $siteName }}" class="h-8 w-auto">
                @else
                    <span class="grid size-8 place-items-center rounded-xl bg-brand text-sm text-white shadow-lg shadow-brand/30">{{ mb_substr($siteName, 0, 1) }}</span>
                    <span>{{ $siteName }}</span>
                @endif
            </a>

            <div class="hidden flex-1 items-center gap-1 md:flex">
                @foreach ($navPages ?? [] as $navPage)
                    @php $active = request()->is($navPage->slug) || ($navPage->is_homepage && request()->is('/')); @endphp
                    <a href="{{ $navPage->url() }}"
                       class="rounded-full px-3.5 py-2 text-sm font-medium transition {{ $active ? 'bg-zinc-900/5 text-zinc-900 dark:bg-white/10 dark:text-white' : 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white' }}">
                        {{ $navPage->title }}
                        @if ($navPage->visibility === 'members')<span class="ml-0.5 text-xs opacity-60">🔒</span>@endif
                    </a>
                @endforeach
                @if (setting('blog_enabled', true))
                    <a href="{{ route('blog.index') }}"
                       class="rounded-full px-3.5 py-2 text-sm font-medium transition {{ request()->is('blog*') ? 'bg-zinc-900/5 text-zinc-900 dark:bg-white/10 dark:text-white' : 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white' }}">
                        {{ setting('blog_title', 'Blog') }}
                    </a>
                @endif
            </div>

            <div class="ml-auto flex items-center gap-2">
                <button type="button" x-data x-on:click="$flux.dark = ! $flux.dark" class="grid size-9 place-items-center rounded-full text-zinc-500 transition hover:bg-zinc-900/5 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-white/10 dark:hover:text-white" aria-label="Toggle dark mode">
                    <flux:icon.moon variant="mini" class="dark:hidden" />
                    <flux:icon.sun variant="mini" class="hidden dark:block" />
                </button>

                @auth
                    <div class="relative hidden md:block" @click.outside="userMenu = false">
                        <button type="button" @click="userMenu = !userMenu" class="flex items-center gap-2 rounded-full p-1 pr-3 text-sm font-medium transition hover:bg-zinc-900/5 dark:hover:bg-white/10">
                            @if ($user->avatarUrl())
                                <img src="{{ $user->avatarUrl() }}" alt="" class="size-7 rounded-full object-cover">
                            @else
                                <span class="grid size-7 place-items-center rounded-full bg-brand/15 text-xs font-bold text-brand">{{ $user->initials() }}</span>
                            @endif
                            {{ Str::before($user->name, ' ') }}
                        </button>
                        <div x-show="userMenu" x-transition.origin.top.right x-cloak class="absolute right-0 mt-2 w-52 overflow-hidden rounded-2xl border border-zinc-200 bg-white p-1.5 text-sm shadow-xl dark:border-white/10 dark:bg-zinc-900">
                            <a href="{{ route('dashboard') }}" class="block rounded-xl px-3 py-2 hover:bg-zinc-100 dark:hover:bg-white/10">My account</a>
                            <a href="{{ route('settings.profile') }}" class="block rounded-xl px-3 py-2 hover:bg-zinc-100 dark:hover:bg-white/10">Profile settings</a>
                            @can('access_admin')
                                <a href="{{ url('/admin') }}" class="block rounded-xl px-3 py-2 font-medium text-brand hover:bg-zinc-100 dark:hover:bg-white/10">Admin panel</a>
                            @endcan
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="block w-full rounded-xl px-3 py-2 text-left hover:bg-zinc-100 dark:hover:bg-white/10">Log out</button>
                            </form>
                        </div>
                    </div>
                @else
                    <a href="{{ route('login') }}" class="hidden rounded-full px-3.5 py-2 text-sm font-medium text-zinc-600 hover:text-zinc-900 md:inline-flex dark:text-zinc-400 dark:hover:text-white">Log in</a>
                    @if (setting('registration_enabled', true))
                        <a href="{{ route('register') }}" class="btn-primary hidden !py-2 md:inline-flex">Sign up</a>
                    @endif
                @endauth

                <button type="button" @click="open = !open" class="grid size-9 place-items-center rounded-full hover:bg-zinc-900/5 md:hidden dark:hover:bg-white/10" aria-label="Menu">
                    <flux:icon.bars-3 x-show="!open" />
                    <flux:icon.x-mark x-show="open" x-cloak />
                </button>
            </div>
        </nav>

        {{-- Mobile menu --}}
        <div x-show="open" x-transition x-cloak class="border-t border-zinc-200/60 px-5 py-4 md:hidden dark:border-white/10">
            <div class="flex flex-col gap-1 text-sm font-medium">
                @foreach ($navPages ?? [] as $navPage)
                    <a href="{{ $navPage->url() }}" class="rounded-xl px-3 py-2.5 hover:bg-zinc-900/5 dark:hover:bg-white/10">{{ $navPage->title }}</a>
                @endforeach
                @if (setting('blog_enabled', true))
                    <a href="{{ route('blog.index') }}" class="rounded-xl px-3 py-2.5 hover:bg-zinc-900/5 dark:hover:bg-white/10">{{ setting('blog_title', 'Blog') }}</a>
                @endif
                <div class="my-2 border-t border-zinc-200/60 dark:border-white/10"></div>
                @auth
                    <a href="{{ route('dashboard') }}" class="rounded-xl px-3 py-2.5 hover:bg-zinc-900/5 dark:hover:bg-white/10">My account</a>
                    @can('access_admin')
                        <a href="{{ url('/admin') }}" class="rounded-xl px-3 py-2.5 text-brand hover:bg-zinc-900/5 dark:hover:bg-white/10">Admin panel</a>
                    @endcan
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="w-full rounded-xl px-3 py-2.5 text-left hover:bg-zinc-900/5 dark:hover:bg-white/10">Log out</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="rounded-xl px-3 py-2.5 hover:bg-zinc-900/5 dark:hover:bg-white/10">Log in</a>
                    @if (setting('registration_enabled', true))
                        <a href="{{ route('register') }}" class="btn-primary mt-1">Sign up</a>
                    @endif
                @endauth
            </div>
        </div>
    </header>

    <main>
        {{ $slot }}
    </main>

    {{-- Footer --}}
    <footer class="mt-24 border-t border-zinc-200/60 dark:border-white/10">
        <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-6 px-5 py-10 sm:flex-row sm:px-8">
            <div class="text-center sm:text-left">
                <div class="font-semibold text-zinc-900 dark:text-white">{{ $siteName }}</div>
                <div class="mt-1 text-sm text-zinc-500">{{ setting('footer_text', '© '.date('Y')) }}</div>
            </div>
            <div class="flex items-center gap-2">
                @foreach (setting('social_links', []) as $link)
                    <a href="{{ $link['platform'] === 'email' && ! Str::startsWith($link['url'], 'mailto:') ? 'mailto:'.$link['url'] : $link['url'] }}"
                       target="_blank" rel="noopener me" aria-label="{{ ucfirst($link['platform']) }}"
                       class="grid size-10 place-items-center rounded-full text-zinc-500 transition hover:bg-zinc-900/5 hover:text-zinc-900 dark:hover:bg-white/10 dark:hover:text-white">
                        <x-site.social-icon :platform="$link['platform']" class="size-5" />
                    </a>
                @endforeach
            </div>
        </div>
    </footer>

    <style>[x-cloak]{display:none!important}</style>
    @fluxScripts
</body>
</html>
