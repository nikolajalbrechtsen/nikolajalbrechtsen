@php
    $url = $field->getUrl();
    $state = $getState();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div style="display:flex;align-items:center;gap:1rem;padding:.75rem;border-radius:.75rem;border:1px dashed rgb(127 127 127 / .35)">
        <div style="width:5.5rem;height:5.5rem;flex-shrink:0;border-radius:.6rem;overflow:hidden;display:grid;place-items:center;background:repeating-conic-gradient(rgb(127 127 127 / .12) 0% 25%, transparent 0% 50%) 50% / 14px 14px">
            @if ($url && $field->isImagePath())
                <img src="{{ $url }}" alt="" style="width:100%;height:100%;object-fit:cover">
            @elseif ($url)
                <span style="font-size:2rem">📄</span>
            @else
                <x-filament::icon icon="heroicon-o-photo" style="width:2rem;height:2rem;opacity:.35" />
            @endif
        </div>

        <div style="min-width:0;flex:1;display:flex;flex-direction:column;gap:.4rem">
            @if ($url)
                <a href="{{ $url }}" target="_blank" style="font-size:.8rem;font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $state }}">{{ basename((string) $state) }}</a>
            @else
                <span style="font-size:.8rem;opacity:.6">No {{ $field->isImagesOnly() ? 'image' : 'file' }} selected</span>
            @endif
            <div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap">
                {{ $getAction('browse') }}
                @if (filled($state))
                    {{ $getAction('remove') }}
                @endif
            </div>
        </div>
    </div>
</x-dynamic-component>
