<?php

namespace Tests\Feature;

use App\Filament\Pages\Calendar;
use App\Filament\Resources\Media\Pages\ListMedia;
use App\Models\Event;
use App\Models\Media;
use App\Models\Page;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CalendarAndMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::role('super_admin')->firstOrFail());
    }

    public function test_pages_load(): void
    {
        $this->get('/admin/calendar')->assertOk()->assertSee('fullcalendar');
        $this->get('/admin/media')->assertOk();
    }

    public function test_calendar_create_fetch_move_edit_delete(): void
    {
        $c = Livewire::test(Calendar::class)
            ->callAction('createEvent', data: [
                'title' => 'Launch party',
                'starts_at' => '2026-12-05 18:00:00',
                'ends_at' => '2026-12-05 21:00:00',
                'color' => 'rose',
                'is_public' => true,
            ])
            ->assertHasNoActionErrors();

        $event = Event::firstOrFail();
        $this->assertSame('Launch party', $event->title);

        $events = $c->instance()->fetchEvents('2026-12-01T00:00:00Z', '2027-01-01T00:00:00Z');
        $this->assertSame('Launch party', collect($events)->firstWhere('id', (string) $event->id)['title']);

        $c->instance()->moveEvent($event->id, '2026-12-06', '2026-12-08', true);
        $event->refresh();
        $this->assertTrue($event->all_day);
        $this->assertSame('2026-12-07', $event->ends_at->toDateString()); // exclusive end converted

        Livewire::test(Calendar::class)
            ->callAction('editEvent', data: ['title' => 'Renamed'], arguments: ['id' => $event->id])
            ->assertHasNoActionErrors();
        $this->assertSame('Renamed', $event->fresh()->title);

        Livewire::test(Calendar::class)
            ->callAction('editEvent', arguments: ['id' => $event->id, 'delete' => true]);
        $this->assertDatabaseCount('events', 0);
    }

    public function test_public_events_show_in_page_block(): void
    {
        Event::create(['title' => 'Public meetup', 'starts_at' => now()->addWeek(), 'is_public' => true]);
        Event::create(['title' => 'Private dentist', 'starts_at' => now()->addWeek(), 'is_public' => false]);
        Page::create(['title' => 'Events', 'slug' => 'events', 'blocks' => [['type' => 'upcoming_events', 'data' => ['heading' => 'Events']]]]);

        $this->get('/events')->assertOk()->assertSee('Public meetup')->assertDontSee('Private dentist');
    }

    public function test_media_upload_import_and_delete(): void
    {
        Storage::fake('public');

        Livewire::test(ListMedia::class)
            ->callAction('upload', data: [
                'files' => [UploadedFile::fake()->image('photo.jpg', 640, 480)],
                'folder' => 'Blog',
            ])
            ->assertHasNoActionErrors();

        $media = Media::firstOrFail();
        $this->assertSame('photo.jpg', $media->name);
        $this->assertSame('Blog', $media->folder);
        $this->assertSame(640, $media->width);
        Storage::disk('public')->assertExists($media->path);

        // A file uploaded elsewhere is picked up by "Find existing uploads"
        Storage::disk('public')->put('pages/hero.png', UploadedFile::fake()->image('hero.png')->getContent());
        Livewire::test(ListMedia::class)->callAction('import');
        $this->assertDatabaseHas('media', ['path' => 'pages/hero.png', 'folder' => 'pages']);

        $path = $media->path;
        $media->delete();
        Storage::disk('public')->assertMissing($path);
    }

    public function test_media_urls_are_relative_and_preview_opens(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('media/clip.mp4', 'fake-video');
        Storage::disk('public')->put('media/pic.png', UploadedFile::fake()->image('pic.png')->getContent());
        $video = Media::fromPath('media/clip.mp4');
        $image = Media::fromPath('media/pic.png');

        // Relative, so thumbnails work on any IP/domain and over https
        $this->assertSame('/storage/media/pic.png', $image->url());
        $this->assertStringStartsWith('http', $image->absoluteUrl());

        Livewire::test(ListMedia::class)
            ->mountTableAction('preview', $image)
            ->assertSee('/storage/media/pic.png')
            ->assertSee('<img', false);

        Livewire::test(ListMedia::class)
            ->mountTableAction('preview', $video)
            ->assertSee('<video', false);
    }

    public function test_members_cannot_open_calendar_or_media(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get('/admin/calendar')->assertForbidden();
        $this->get('/admin/media')->assertForbidden();
    }
}
