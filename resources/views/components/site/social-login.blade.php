@php
    use App\Http\Controllers\SocialiteController;
    $google = SocialiteController::enabled('google');
    $github = SocialiteController::enabled('github');
@endphp

@if ($google || $github)
    <div class="flex flex-col gap-3">
        @if ($google)
            <a href="{{ route('social.redirect', 'google') }}"
               class="flex w-full items-center justify-center gap-3 rounded-lg border border-zinc-200 bg-white px-4 py-2.5 text-sm font-medium text-zinc-800 shadow-xs transition hover:bg-zinc-50 dark:border-white/10 dark:bg-white/5 dark:text-white dark:hover:bg-white/10">
                <svg class="size-5" viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285F4" d="M23.5 12.27c0-.85-.08-1.67-.22-2.45H12v4.64h6.45a5.52 5.52 0 0 1-2.4 3.62v3h3.88c2.27-2.09 3.57-5.17 3.57-8.81Z"/><path fill="#34A853" d="M12 24c3.24 0 5.96-1.07 7.94-2.9l-3.88-3.01c-1.07.72-2.45 1.15-4.06 1.15-3.13 0-5.78-2.11-6.72-4.95H1.27v3.11A12 12 0 0 0 12 24Z"/><path fill="#FBBC05" d="M5.28 14.29a7.2 7.2 0 0 1 0-4.58V6.6H1.27a12 12 0 0 0 0 10.8l4.01-3.11Z"/><path fill="#EA4335" d="M12 4.77c1.76 0 3.34.6 4.59 1.8l3.44-3.44A11.5 11.5 0 0 0 12 0 12 12 0 0 0 1.27 6.6l4.01 3.11C6.22 6.88 8.87 4.77 12 4.77Z"/></svg>
                Continue with Google
            </a>
        @endif
        @if ($github)
            <a href="{{ route('social.redirect', 'github') }}"
               class="flex w-full items-center justify-center gap-3 rounded-lg bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white shadow-xs transition hover:bg-zinc-800 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
                <x-site.social-icon platform="github" class="size-5" />
                Continue with GitHub
            </a>
        @endif
        <div class="relative my-1 text-center text-xs uppercase tracking-wide text-zinc-400">
            <span class="relative z-10 bg-white px-3 dark:bg-zinc-900">or</span>
            <div class="absolute inset-x-0 top-1/2 border-t border-zinc-200 dark:border-white/10"></div>
        </div>
    </div>
@endif
