<?php

namespace App\Filament\Forms\Components;

use App\Filament\Forms\MediaLibrary;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Image/file field that picks from the Media library, with an
 * "Upload from PC" tab. Stores the file path on the public disk
 * (same format as a normal FileUpload, so existing data keeps working).
 *
 *     MediaPicker::make('cover_image')
 *     MediaPicker::make('attachment')->images(false)   // any file type
 */
class MediaPicker extends Field
{
    protected string $view = 'filament.forms.components.media-picker';

    protected bool $imagesOnly = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerActions([
            fn (MediaPicker $component): Action => $component->getBrowseAction(),
            fn (MediaPicker $component): Action => $component->getRemoveAction(),
        ]);

        // Old FileUpload data may be stored as ['uuid' => 'path'].
        $this->afterStateHydrated(function (MediaPicker $component, $state) {
            if (is_array($state)) {
                $component->state(reset($state) ?: null);
            }
        });
    }

    public function images(bool $condition = true): static
    {
        $this->imagesOnly = $condition;

        return $this;
    }

    public function isImagesOnly(): bool
    {
        return $this->imagesOnly;
    }

    public function getBrowseAction(): Action
    {
        return Action::make('browse')
            ->label(fn (MediaPicker $component) => filled($component->getState()) ? 'Change' : ($component->isImagesOnly() ? 'Choose image' : 'Choose file'))
            ->icon(Heroicon::OutlinedPhoto)
            ->color('gray')
            ->size('sm')
            ->modalHeading('Media library')
            ->modalWidth('5xl')
            ->modalSubmitActionLabel('Use this file')
            ->schema(fn (MediaPicker $component) => MediaLibrary::schema($component->isImagesOnly()))
            ->action(function (array $data, MediaPicker $component) {
                if ($media = MediaLibrary::resolve($data)) {
                    $component->state($media->path);
                }
            })
            ->disabled(fn (MediaPicker $component) => $component->isDisabled());
    }

    public function getRemoveAction(): Action
    {
        return Action::make('remove')
            ->label('Remove')
            ->icon(Heroicon::OutlinedXMark)
            ->color('danger')
            ->size('sm')
            ->link()
            ->action(fn (MediaPicker $component) => $component->state(null))
            ->visible(fn (MediaPicker $component) => filled($component->getState()) && ! $component->isDisabled());
    }

    public function getUrl(): ?string
    {
        $state = $this->getState();
        if (is_array($state)) {
            $state = reset($state) ?: null;
        }

        return filled($state) ? Storage::disk('public')->url($state) : null;
    }

    public function isImagePath(): bool
    {
        return Str::endsWith(Str::lower((string) $this->getState()), ['.jpg', '.jpeg', '.png', '.gif', '.webp', '.svg', '.avif', '.ico', '.bmp']);
    }
}
