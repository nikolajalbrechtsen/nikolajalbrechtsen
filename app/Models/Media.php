<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

class Media extends Model
{
    protected $table = 'media';

    protected $fillable = ['name', 'path', 'folder', 'mime_type', 'size', 'width', 'height', 'alt', 'user_id'];

    protected static function booted(): void
    {
        // Deleting a media item also deletes the file.
        static::deleted(fn (Media $media) => Storage::disk('public')->delete($media->path));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Create a media record for a file that is already on the public disk. */
    public static function fromPath(string $path, ?string $name = null, ?string $folder = null): self
    {
        $disk = Storage::disk('public');
        $full = $disk->path($path);
        $mime = $disk->mimeType($path) ?: null;
        [$width, $height] = (Str::startsWith((string) $mime, 'image/') && $mime !== 'image/svg+xml')
            ? (@getimagesize($full) ?: [null, null])
            : [null, null];

        return static::firstOrCreate(['path' => $path], [
            'name' => $name ?: basename($path),
            'folder' => $folder,
            'mime_type' => $mime,
            'size' => $disk->size($path),
            'width' => $width,
            'height' => $height,
            'user_id' => auth()->id(),
        ]);
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    public function isImage(): bool
    {
        return Str::startsWith((string) $this->mime_type, 'image/');
    }

    public function type(): string
    {
        return match (true) {
            $this->isImage() => 'image',
            Str::startsWith((string) $this->mime_type, 'video/') => 'video',
            Str::startsWith((string) $this->mime_type, 'audio/') => 'audio',
            $this->mime_type === 'application/pdf' => 'pdf',
            default => 'file',
        };
    }

    public function humanSize(): string
    {
        return Number::fileSize($this->size, precision: 1);
    }
}
