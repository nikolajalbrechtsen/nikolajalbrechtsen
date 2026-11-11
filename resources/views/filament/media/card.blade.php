@php
    /** @var \App\Models\Media $record */
    $record = $getRecord();
    $icons = ['video' => '🎬', 'audio' => '🎵', 'pdf' => '📕', 'file' => '📄'];
@endphp

<div style="display:flex;flex-direction:column;gap:.5rem;width:100%">
    <div style="aspect-ratio:4/3;width:100%;border-radius:.6rem;overflow:hidden;display:grid;place-items:center;background:repeating-conic-gradient(rgb(127 127 127 / .12) 0% 25%, transparent 0% 50%) 50% / 16px 16px">
        @if ($record->isImage())
            <img src="{{ $record->url() }}" alt="{{ $record->alt }}" loading="lazy" style="width:100%;height:100%;object-fit:cover">
        @elseif ($record->type() === 'video')
            <div style="position:relative;width:100%;height:100%">
                <video src="{{ $record->url() }}#t=0.5" preload="metadata" muted playsinline style="width:100%;height:100%;object-fit:cover;background:#000"></video>
                <span style="position:absolute;inset:0;display:grid;place-items:center;font-size:1.6rem;color:#fff;text-shadow:0 2px 8px rgb(0 0 0 / .6)">▶</span>
            </div>
        @else
            <span style="font-size:2.5rem">{{ $icons[$record->type()] ?? '📄' }}</span>
        @endif
    </div>
    <div style="min-width:0">
        <div style="font-size:.8rem;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $record->name }}">{{ $record->name }}</div>
        <div style="font-size:.7rem;opacity:.6;display:flex;gap:.4rem;flex-wrap:wrap">
            <span>{{ $record->humanSize() }}</span>
            @if ($record->width)<span>· {{ $record->width }}×{{ $record->height }}</span>@endif
            @if ($record->folder)<span>· 📁 {{ $record->folder }}</span>@endif
        </div>
    </div>
</div>
