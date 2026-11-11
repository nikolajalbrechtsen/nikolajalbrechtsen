<?php

use App\Models\Setting;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

if (! function_exists('setting')) {
    /** Read a site setting saved in Admin → Settings. */
    function setting(string $key, mixed $default = null): mixed
    {
        return Setting::get($key, $default);
    }
}

if (! function_exists('rich')) {
    /** Render rich-editor content (with image attachments) as safe HTML. */
    function rich(?string $html): HtmlString
    {
        if (blank($html)) {
            return new HtmlString('');
        }

        return new HtmlString(
            RichContentRenderer::make($html)
                ->fileAttachmentsDisk('public')
                ->fileAttachmentsVisibility('public')
                ->toHtml()
        );
    }
}

if (! function_exists('public_url')) {
    /** URL of a file on the public disk (uploads). */
    function public_url(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }
}
