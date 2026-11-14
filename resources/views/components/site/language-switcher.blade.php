@php
    use App\Support\Locales;
    $current = app()->getLocale();
@endphp

@if (count(Locales::all()) > 1)
    <div {{ $attributes->merge(['class' => 'flex items-center rounded-full bg-zinc-900/5 p-0.5 text-xs font-semibold dark:bg-white/10']) }} role="group" aria-label="{{ __('Language') }}">
        @foreach (Locales::all() as $code => $name)
            <a href="{{ route('locale.switch', $code) }}" hreflang="{{ $code }}" lang="{{ $code }}" title="{{ $name }}"
               @if ($code === $current) aria-current="true" @endif
               class="rounded-full px-2.5 py-1 uppercase transition {{ $code === $current ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-800 dark:text-white' : 'text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white' }}">
                {{ $code }}
            </a>
        @endforeach
    </div>
@endif
