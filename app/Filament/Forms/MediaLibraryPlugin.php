<?php

namespace App\Filament\Forms;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\EditorCommand;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

/**
 * Adds an "Insert image" toolbar button to rich editors that opens the
 * media library (with an "Upload from PC" tab).
 *
 *     RichEditor::make('content')->plugins([MediaLibraryPlugin::make()])->toolbarButtons(MediaLibrary::TOOLBAR)
 */
class MediaLibraryPlugin implements RichContentPlugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getTipTapPhpExtensions(): array
    {
        return [];
    }

    public function getTipTapJsExtensions(): array
    {
        return [];
    }

    public function getEditorTools(): array
    {
        return [
            RichEditorTool::make('mediaLibrary')
                ->label('Insert image from media library')
                ->icon(Heroicon::Photo)
                ->action(),
        ];
    }

    public function getEditorActions(): array
    {
        return [
            Action::make('mediaLibrary')
                ->modalHeading('Insert image')
                ->modalWidth('5xl')
                ->modalSubmitActionLabel('Insert')
                ->schema([
                    ...MediaLibrary::schema(imagesOnly: true),
                    TextInput::make('alt')
                        ->label('Alt text (optional)')
                        ->helperText('Describes the image for screen readers and search engines.'),
                ])
                ->action(function (array $arguments, array $data, RichEditor $component) {
                    $media = MediaLibrary::resolve($data);
                    if (! $media) {
                        return;
                    }

                    $component->runCommands(
                        [
                            EditorCommand::make('insertContent', arguments: [[
                                'type' => 'image',
                                'attrs' => [
                                    'alt' => $data['alt'] ?: $media->alt,
                                    // Relative URL, so content keeps working if the domain changes.
                                    'src' => parse_url($media->url(), PHP_URL_PATH),
                                ],
                            ]]),
                        ],
                        editorSelection: $arguments['editorSelection'] ?? null,
                    );
                }),
        ];
    }
}
