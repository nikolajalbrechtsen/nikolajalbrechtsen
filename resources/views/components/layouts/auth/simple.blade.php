<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <div class="bg-background flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div class="flex w-full max-w-sm flex-col gap-2">
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-2 font-medium" wire:navigate>
                    @if ($logo = public_url(setting('logo')))
                        <img src="{{ $logo }}" alt="" class="mb-1 h-10 w-auto">
                    @else
                        <span class="mb-1 grid size-10 place-items-center rounded-xl bg-brand text-lg font-bold text-white shadow-lg shadow-brand/30">{{ mb_substr(setting('site_name', config('app.name')), 0, 1) }}</span>
                    @endif
                    <span class="text-sm text-zinc-500">{{ setting('site_name', config('app.name')) }}</span>
                </a>
                <div class="flex flex-col gap-6">
                    {{ $slot }}
                </div>
            </div>
        </div>
        @fluxScripts
    </body>
</html>
