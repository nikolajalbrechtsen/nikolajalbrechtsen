@php
    $items = $field->getItems($get('search'), $get('filter_folder'));
    $statePath = $getStatePath();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div x-data="{ state: $wire.$entangle(@js($statePath)) }">
        <style>
            .mg-grid { display: grid; gap: .6rem; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); max-height: 55vh; overflow-y: auto; padding: 2px; }
            .mg-item { position: relative; cursor: pointer; border-radius: .6rem; overflow: hidden; border: 2px solid transparent; background: rgb(127 127 127 / .08); text-align: left; }
            .mg-item:hover { border-color: rgb(99 102 241 / .5); }
            .mg-item.is-selected { border-color: rgb(99 102 241); box-shadow: 0 0 0 3px rgb(99 102 241 / .25); }
            .mg-thumb { aspect-ratio: 1; width: 100%; display: grid; place-items: center; font-size: 2rem; background: repeating-conic-gradient(rgb(127 127 127 / .12) 0% 25%, transparent 0% 50%) 50% / 14px 14px; }
            .mg-thumb img { width: 100%; height: 100%; object-fit: cover; }
            .mg-name { font-size: .7rem; padding: .3rem .4rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
            .mg-check { position: absolute; top: .35rem; right: .35rem; width: 1.3rem; height: 1.3rem; border-radius: 9999px; background: rgb(99 102 241); color: #fff; font-size: .75rem; display: grid; place-items: center; }
            .mg-empty { padding: 2rem; text-align: center; opacity: .6; font-size: .875rem; }
        </style>

        @if ($items->isEmpty())
            <div class="mg-empty">No files found. Use the <strong>Upload from PC</strong> tab to add one.</div>
        @else
            <div class="mg-grid">
                @foreach ($items as $item)
                    <button
                        type="button"
                        wire:key="media-grid-{{ $item->id }}"
                        class="mg-item"
                        :class="{ 'is-selected': String(state) === '{{ $item->id }}' }"
                        x-on:click="state = {{ $item->id }}"
                        title="{{ $item->name }}{{ $item->width ? ' · '.$item->width.'×'.$item->height : '' }}"
                    >
                        <div class="mg-thumb">
                            @if ($item->isImage())
                                <img src="{{ $item->url() }}" alt="" loading="lazy">
                            @else
                                <span>📄</span>
                            @endif
                        </div>
                        <div class="mg-name">{{ $item->name }}</div>
                        <span class="mg-check" x-show="String(state) === '{{ $item->id }}'" x-cloak>✓</span>
                    </button>
                @endforeach
            </div>
            @if ($items->count() >= 60)
                <p style="font-size:.75rem;opacity:.6;margin-top:.5rem">Showing the 60 newest files — search to find older ones.</p>
            @endif
        @endif
    </div>
</x-dynamic-component>
