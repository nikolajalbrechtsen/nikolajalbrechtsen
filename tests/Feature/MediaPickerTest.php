<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings;
use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Models\Media;
use App\Models\Post;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class MediaPickerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::role('super_admin')->firstOrFail());
    }

    protected function libraryImage(string $name = 'library.png'): Media
    {
        Storage::disk('public')->put("media/{$name}", UploadedFile::fake()->image($name, 300, 200)->getContent());

        return Media::fromPath("media/{$name}", $name);
    }

    public function test_pick_cover_image_from_library(): void
    {
        $media = $this->libraryImage();

        Livewire::test(CreatePost::class)
            ->fillForm(['title' => 'With cover', 'slug' => 'with-cover', 'visibility' => 'public'])
            ->callAction(TestAction::make('browse')->schemaComponent('cover_image'), data: ['media_id' => $media->id])
            ->assertHasNoActionErrors()
            ->assertSchemaStateSet(['cover_image' => 'media/library.png'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('media/library.png', Post::where('slug', 'with-cover')->value('cover_image'));
    }

    public function test_upload_from_pc_adds_to_media_library(): void
    {
        Livewire::test(Settings::class)
            ->callAction(TestAction::make('browse')->schemaComponent('logo'), data: [
                'upload' => UploadedFile::fake()->image('my-logo.png', 200, 200),
                'upload_folder' => 'Branding',
            ])
            ->assertHasNoActionErrors()
            ->call('save');

        $media = Media::where('name', 'my-logo.png')->firstOrFail();
        $this->assertSame('Branding', $media->folder);
        $this->assertSame($media->path, Setting::get('logo'));
        Storage::disk('public')->assertExists($media->path);
    }

    public function test_insert_image_into_rich_editor(): void
    {
        $media = $this->libraryImage('inline.png');
        $post = Post::firstOrFail();

        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])
            ->callAction(
                TestAction::make('mediaLibrary')->schemaComponent('content'),
                data: ['media_id' => $media->id, 'alt' => 'An inline image'],
                arguments: ['editorSelection' => ['type' => 'text', 'anchor' => 1, 'head' => 1]],
            )
            ->assertHasNoActionErrors();
    }

    public function test_remove_clears_the_image(): void
    {
        $media = $this->libraryImage();
        $post = Post::firstOrFail();
        $post->update(['cover_image' => $media->path]);

        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])
            ->callAction(TestAction::make('remove')->schemaComponent('cover_image'))
            ->call('save');

        $this->assertNull($post->fresh()->cover_image);
    }
}
