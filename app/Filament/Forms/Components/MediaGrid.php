<?php

namespace App\Filament\Forms\Components;

use App\Models\Media;
use Filament\Forms\Components\Field;
use Illuminate\Support\Collection;

/**
 * Thumbnail grid of media items (used inside the media library modal).
 * State = the selected media id.
 */
class MediaGrid extends Field
{
    protected string $view = 'filament.forms.components.media-grid';

    protected bool $imagesOnly = true;

    public function imagesOnly(bool $condition = true): static
    {
        $this->imagesOnly = $condition;

        return $this;
    }

    /** @return Collection<int, Media> */
    public function getItems(?string $search, ?string $folder): Collection
    {
        return Media::query()
            ->when($this->imagesOnly, fn ($q) => $q->where('mime_type', 'like', 'image/%'))
            ->when(filled($search), fn ($q) => $q->where('name', 'like', '%'.$search.'%'))
            ->when(filled($folder), fn ($q) => $q->where('folder', $folder))
            ->latest()
            ->limit(60)
            ->get();
    }
}
