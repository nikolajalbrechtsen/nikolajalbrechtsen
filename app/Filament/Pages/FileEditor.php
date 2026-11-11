<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;
use UnitEnum;

/**
 * A VS Code-style editor (Monaco) for every file of the website.
 */
class FileEditor extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCodeBracketSquare;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'File editor';

    protected string $view = 'filament.pages.file-editor';

    protected Width|string|null $maxContentWidth = Width::Full;

    /** Folders that are never shown or editable. */
    protected const HIDDEN = ['.git', 'vendor', 'node_modules', '.tools', 'storage/framework', 'storage/logs/.gitignore', 'public/build', 'public/storage'];

    protected const MAX_BYTES = 2 * 1024 * 1024;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('manage_files');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('vscode')
                ->label('Open VS Code')
                ->icon(Heroicon::OutlinedCommandLine)
                ->color('gray')
                ->url('/code/', shouldOpenInNewTab: true)
                ->visible(fn () => auth()->user()->can('use_code_editor')),
            Action::make('clearCache')
                ->label('Clear caches')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->action(function () {
                    Artisan::call('optimize:clear');
                    Notification::make()->title('Caches cleared')->body('Config, route and view changes are now live.')->success()->send();
                }),
            Action::make('buildAssets')
                ->label('Build CSS/JS')
                ->icon(Heroicon::OutlinedWrenchScrewdriver)
                ->requiresConfirmation()
                ->modalDescription('Runs "npm run build" so changes in resources/css and resources/js go live. Takes 10–60 seconds.')
                ->action(function () {
                    $result = Process::path(base_path())->timeout(600)->run('npm run build');
                    if ($result->successful()) {
                        Notification::make()->title('Assets built')->success()->send();
                    } else {
                        Log::error('npm run build failed', ['output' => $result->errorOutput() ?: $result->output()]);
                        Notification::make()->title('Build failed')->body(Str::limit($result->errorOutput() ?: $result->output(), 500))->danger()->persistent()->send();
                    }
                }),
        ];
    }

    /**
     * Turn a relative path from the browser into a safe absolute path inside the project.
     */
    protected function resolve(string $relative, bool $mustExist = true): string
    {
        $relative = trim(str_replace('\\', '/', $relative), '/');

        if (str_contains($relative, "\0") || collect(explode('/', $relative))->contains(fn ($part) => $part === '..')) {
            throw new RuntimeException('Invalid path.');
        }

        foreach (self::HIDDEN as $hidden) {
            if ($relative === $hidden || str_starts_with($relative, $hidden.'/')) {
                throw new RuntimeException('This folder is protected.');
            }
        }

        $base = realpath(base_path());
        $full = $relative === '' ? $base : $base.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);

        if ($mustExist) {
            $real = realpath($full);
            if ($real === false || ($real !== $base && ! str_starts_with($real, $base.DIRECTORY_SEPARATOR))) {
                throw new RuntimeException('File not found.');
            }

            return $real;
        }

        $parent = realpath(dirname($full));
        if ($parent === false || ($parent !== $base && ! str_starts_with($parent, $base.DIRECTORY_SEPARATOR))) {
            throw new RuntimeException('Folder not found.');
        }

        return $full;
    }

    protected function relative(string $absolute): string
    {
        return ltrim(str_replace('\\', '/', Str::after($absolute, realpath(base_path()))), '/');
    }

    protected function fail(RuntimeException $e): array
    {
        Notification::make()->title($e->getMessage())->danger()->send();

        return ['ok' => false, 'error' => $e->getMessage()];
    }

    /** List one folder (lazy-loaded by the tree). */
    public function listDir(string $path = ''): array
    {
        try {
            $dir = $this->resolve($path);
        } catch (RuntimeException $e) {
            return [];
        }

        $items = [];
        foreach (File::directories($dir) as $d) {
            $rel = $this->relative($d);
            if (! $this->isHidden($rel)) {
                $items[] = ['name' => basename($d), 'path' => $rel, 'type' => 'dir'];
            }
        }
        foreach (File::files($dir, true) as $f) {
            $rel = $this->relative($f->getPathname());
            if (! $this->isHidden($rel)) {
                $items[] = ['name' => $f->getFilename(), 'path' => $rel, 'type' => 'file', 'size' => $f->getSize()];
            }
        }

        usort($items, fn ($a, $b) => [$a['type'] !== 'dir', strtolower($a['name'])] <=> [$b['type'] !== 'dir', strtolower($b['name'])]);

        return $items;
    }

    protected function isHidden(string $relative): bool
    {
        foreach (self::HIDDEN as $hidden) {
            if ($relative === $hidden || str_starts_with($relative, $hidden.'/')) {
                return true;
            }
        }

        return false;
    }

    public function openFile(string $path): array
    {
        try {
            $file = $this->resolve($path);
            if (! is_file($file)) {
                throw new RuntimeException('Not a file.');
            }
            if (filesize($file) > self::MAX_BYTES) {
                throw new RuntimeException('File is too large to edit here (max 2 MB).');
            }
            $content = file_get_contents($file);
            if (! mb_check_encoding($content, 'UTF-8') || str_contains($content, "\0")) {
                throw new RuntimeException('Binary files cannot be edited.');
            }

            return ['ok' => true, 'content' => $content];
        } catch (RuntimeException $e) {
            return $this->fail($e);
        }
    }

    public function saveFile(string $path, string $content): array
    {
        try {
            $file = $this->resolve($path);
            if (! is_file($file) || ! is_writable($file)) {
                throw new RuntimeException('File is not writable.');
            }
            file_put_contents($file, $content, LOCK_EX);
            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate($file, true);
            }
            Log::info('File edited in admin', ['path' => $path, 'user' => auth()->user()->email]);

            return ['ok' => true];
        } catch (RuntimeException $e) {
            return $this->fail($e);
        }
    }

    public function createItem(string $path, string $type): array
    {
        try {
            $target = $this->resolve($path, mustExist: false);
            if (file_exists($target)) {
                throw new RuntimeException('Something with that name already exists.');
            }
            $type === 'dir' ? File::makeDirectory($target, 0775, true) : File::put($target, '');

            return ['ok' => true, 'path' => $this->relative($target)];
        } catch (RuntimeException $e) {
            return $this->fail($e);
        }
    }

    public function renameItem(string $from, string $to): array
    {
        try {
            $source = $this->resolve($from);
            $target = $this->resolve($to, mustExist: false);
            if (file_exists($target)) {
                throw new RuntimeException('Something with that name already exists.');
            }
            File::move($source, $target);

            return ['ok' => true, 'path' => $this->relative($target)];
        } catch (RuntimeException $e) {
            return $this->fail($e);
        }
    }

    public function deleteItem(string $path): array
    {
        try {
            $target = $this->resolve($path);
            if ($target === realpath(base_path())) {
                throw new RuntimeException('Cannot delete the project root.');
            }
            is_dir($target) ? File::deleteDirectory($target) : File::delete($target);
            Log::warning('File deleted in admin', ['path' => $path, 'user' => auth()->user()->email]);

            return ['ok' => true];
        } catch (RuntimeException $e) {
            return $this->fail($e);
        }
    }
}
