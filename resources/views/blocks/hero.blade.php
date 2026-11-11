@php $image = public_url($data['image'] ?? null); @endphp
<section class="relative overflow-hidden">
    <div class="mx-auto grid max-w-6xl items-center gap-12 px-5 pb-16 pt-20 sm:px-8 sm:pt-28 {{ $image ? 'lg:grid-cols-2' : '' }}">
        <div class="{{ $image ? '' : 'mx-auto max-w-3xl text-center' }}">
            @if (! empty($data['eyebrow']))
                <span class="inline-flex items-center gap-2 rounded-full border border-brand/20 bg-brand/10 px-3 py-1 text-sm font-medium text-brand">
                    <span class="size-1.5 rounded-full bg-brand"></span>{{ $data['eyebrow'] }}
                </span>
            @endif
            <h1 class="mt-6 text-5xl font-extrabold tracking-tight text-balance text-zinc-900 sm:text-6xl lg:text-7xl dark:text-white">
                {{ $data['heading'] ?? '' }}
            </h1>
            @if (! empty($data['subheading']))
                <p class="mt-6 text-lg text-pretty text-zinc-600 sm:text-xl dark:text-zinc-400 {{ $image ? '' : 'mx-auto max-w-2xl' }}">{{ $data['subheading'] }}</p>
            @endif
            <div class="mt-10 flex flex-wrap gap-3 {{ $image ? '' : 'justify-center' }}">
                @if (! empty($data['primary_label']))
                    <a href="{{ $data['primary_url'] ?? '#' }}" class="btn-primary">{{ $data['primary_label'] }} <span aria-hidden="true">→</span></a>
                @endif
                @if (! empty($data['secondary_label']))
                    <a href="{{ $data['secondary_url'] ?? '#' }}" class="btn-ghost">{{ $data['secondary_label'] }}</a>
                @endif
            </div>
        </div>
        @if ($image)
            <div class="relative">
                <div class="absolute -inset-4 -z-10 rounded-[2.5rem] bg-gradient-to-tr from-brand/30 to-transparent blur-2xl"></div>
                <img src="{{ $image }}" alt="" class="aspect-square w-full rounded-[2rem] object-cover shadow-2xl ring-1 ring-zinc-900/10 dark:ring-white/10">
            </div>
        @endif
    </div>
</section>
