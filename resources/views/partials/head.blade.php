<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>{{ isset($title) ? $title.' · ' : '' }}{{ setting('site_name', config('app.name')) }}</title>
@if ($favicon = public_url(setting('favicon')))<link rel="icon" href="{{ $favicon }}">@endif

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
<style>:root { --brand: {{ setting('primary_color', '#6366f1') }}; }</style>

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
