@php
    /** @var \App\Models\Media $media */
    $url = $media->url();
    $type = $media->type();
@endphp

<div style="display:flex;justify-content:center;align-items:center;min-height:200px;border-radius:.75rem;overflow:hidden;background:repeating-conic-gradient(rgb(127 127 127 / .12) 0% 25%, transparent 0% 50%) 50% / 20px 20px">
    @if ($type === 'image')
        <img src="{{ $url }}" alt="{{ $media->alt }}" style="max-width:100%;max-height:75vh;object-fit:contain;display:block">
    @elseif ($type === 'video')
        <video src="{{ $url }}" controls autoplay playsinline style="max-width:100%;max-height:75vh;display:block;background:#000"></video>
    @elseif ($type === 'audio')
        <div style="padding:3rem;width:100%;max-width:36rem">
            <div style="font-size:3rem;text-align:center;margin-bottom:1rem">🎵</div>
            <audio src="{{ $url }}" controls autoplay style="width:100%"></audio>
        </div>
    @elseif ($type === 'pdf')
        <iframe src="{{ $url }}" style="width:100%;height:75vh;border:0;background:#fff"></iframe>
    @else
        <div style="padding:3rem;text-align:center">
            <div style="font-size:3rem">📄</div>
            <p style="margin-top:.75rem;opacity:.7">No preview available for this file type.</p>
            <a href="{{ $url }}" download="{{ $media->name }}" style="display:inline-block;margin-top:1rem;font-weight:600;color:rgb(99 102 241)">Download {{ $media->name }}</a>
        </div>
    @endif
</div>

@if ($media->alt)
    <p style="margin-top:.75rem;font-size:.8rem;opacity:.7">Alt text: {{ $media->alt }}</p>
@endif
