@php $logo = public_url(setting('logo')); $siteName = setting('site_name', config('app.name')); @endphp
@if ($logo)
    <img src="{{ $logo }}" alt="{{ $siteName }}" class="h-8 w-auto">
@else
    <div class="flex aspect-square size-8 items-center justify-center rounded-lg bg-brand text-sm font-bold text-white">
        {{ mb_substr($siteName, 0, 1) }}
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate leading-none font-semibold">{{ $siteName }}</span>
    </div>
@endif
