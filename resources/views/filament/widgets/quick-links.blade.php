@php
    $user = auth()->user();
    $links = array_filter([
        $user->can('manage_pages') ? ['New page', 'Build a page with drag-and-drop blocks', \App\Filament\Resources\Pages\PageResource::getUrl('create'), 'heroicon-o-document-plus', false] : null,
        $user->can('manage_posts') ? ['New blog post', 'Write and schedule a post', \App\Filament\Resources\Posts\PostResource::getUrl('create'), 'heroicon-o-pencil-square', false] : null,
        $user->can('manage_settings') ? ['Site settings', 'Name, logo, colors, login options', \App\Filament\Pages\Settings::getUrl(), 'heroicon-o-cog-6-tooth', false] : null,
        $user->can('manage_files') ? ['File editor', 'Edit any file of the website', \App\Filament\Pages\FileEditor::getUrl(), 'heroicon-o-code-bracket-square', false] : null,
        $user->can('use_code_editor') ? ['VS Code', 'Full VS Code in your browser', url('/code/'), 'heroicon-o-command-line', true] : null,
        ['View website', 'Open the public site', url('/'), 'heroicon-o-globe-alt', true],
    ]);
@endphp

<x-filament-widgets::widget>
    <x-filament::section heading="Quick actions">
        <style>
            .ql-grid { display: grid; gap: .75rem; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); }
            .ql-card { display: flex; align-items: flex-start; gap: .85rem; padding: 1rem; border-radius: .85rem; border: 1px solid rgb(0 0 0 / .08); transition: background .15s, border-color .15s; }
            .ql-card:hover { background: rgb(99 102 241 / .06); border-color: rgb(99 102 241 / .35); }
            .dark .ql-card { border-color: rgb(255 255 255 / .1); }
            .dark .ql-card:hover { background: rgb(99 102 241 / .12); border-color: rgb(99 102 241 / .5); }
            .ql-icon { flex-shrink: 0; display: grid; place-items: center; width: 2.25rem; height: 2.25rem; border-radius: .6rem; background: rgb(99 102 241 / .12); color: rgb(99 102 241); }
            .ql-icon svg { width: 1.25rem; height: 1.25rem; }
            .ql-title { font-size: .875rem; font-weight: 600; }
            .ql-desc { font-size: .75rem; opacity: .65; margin-top: .1rem; }
        </style>
        <div class="ql-grid">
            @foreach ($links as [$label, $description, $url, $icon, $newTab])
                <a href="{{ $url }}" @if ($newTab) target="_blank" @endif class="ql-card">
                    <span class="ql-icon"><x-filament::icon :icon="$icon" /></span>
                    <span>
                        <span class="ql-title" style="display:block">{{ $label }}</span>
                        <span class="ql-desc" style="display:block">{{ $description }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
