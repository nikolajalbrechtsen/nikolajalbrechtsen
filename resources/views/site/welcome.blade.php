<x-layouts.site>
    <section class="section text-center">
        <h1 class="text-5xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ setting('site_name', config('app.name')) }}</h1>
        <p class="mx-auto mt-4 max-w-xl text-lg text-zinc-500">No homepage has been set yet. Go to <strong>Admin → Pages</strong> and mark a page as homepage.</p>
        <a href="{{ url('/admin') }}" class="btn-primary mt-8">Open admin panel</a>
    </section>
</x-layouts.site>
