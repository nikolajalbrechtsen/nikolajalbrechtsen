<section class="section">
    @if (! empty($data['heading']))
        <h2 class="text-center text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl dark:text-white">{{ $data['heading'] }}</h2>
    @endif
    <dl class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-{{ min(4, max(1, count($data['items'] ?? []))) }}">
        @foreach ($data['items'] ?? [] as $item)
            <div class="card text-center">
                <dt class="order-2 mt-2 text-sm font-medium text-zinc-500">{{ $item['label'] ?? '' }}</dt>
                <dd class="bg-gradient-to-br from-brand to-brand/60 bg-clip-text text-5xl font-extrabold tracking-tight text-transparent">{{ $item['value'] ?? '' }}</dd>
            </div>
        @endforeach
    </dl>
</section>
