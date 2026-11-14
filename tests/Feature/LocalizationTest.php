<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Post;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_the_site_is_danish_by_default(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('<html lang="da"', false)
            ->assertSee('Log ind på din konto');
    }

    public function test_visitors_can_switch_language(): void
    {
        $this->from('/login')->get('/lang/en')
            ->assertRedirect('/login')
            ->assertSessionHas('locale', 'en')
            ->assertCookie('locale');

        $this->get('/login')
            ->assertSee('<html lang="en"', false)
            ->assertSee('Log in to your account');

        $this->get('/lang/da');
        $this->get('/login')->assertSee('Log ind på din konto');
    }

    public function test_unknown_languages_are_ignored(): void
    {
        $this->get('/lang/xx')->assertNotFound();
        $this->get('/login?lang=xx')->assertSee('<html lang="da"', false);
    }

    public function test_lang_query_parameter_switches_language(): void
    {
        $this->get('/login?lang=en')->assertSee('Log in to your account')->assertSessionHas('locale', 'en');
    }

    public function test_switching_never_redirects_off_site(): void
    {
        $this->from('https://evil.example/phish')->get('/lang/en')->assertRedirect(route('home'));
    }

    public function test_pages_show_the_translation_or_fall_back_to_danish(): void
    {
        $page = Page::create([
            'title' => 'Om mig',
            'slug' => 'om-mig',
            'is_published' => true,
            'visibility' => 'public',
            'blocks' => [['type' => 'rich_text', 'data' => ['heading' => 'Hej, jeg hedder Nikolaj']]],
            'translations' => ['en' => [
                'title' => 'About me',
                'blocks' => [['type' => 'rich_text', 'data' => ['heading' => 'Hi, I am Nikolaj']]],
                'meta_title' => null,
            ]],
        ]);

        $this->get('/om-mig')->assertSee('Hej, jeg hedder Nikolaj')->assertDontSee('Hi, I am Nikolaj');
        $this->get('/om-mig?lang=en')->assertSee('Hi, I am Nikolaj')->assertSee('About me');

        // An empty translation falls back to the Danish content.
        $page->update(['translations' => ['en' => ['title' => 'About me', 'blocks' => []]]]);
        $this->get('/om-mig?lang=en')->assertSee('Hej, jeg hedder Nikolaj');

        $this->assertSame(['da', 'en'], $page->fresh()->availableLocales());
        $page->update(['translations' => null]);
        $this->assertSame(['da'], $page->fresh()->availableLocales());
    }

    public function test_posts_show_the_translation_or_fall_back_to_danish(): void
    {
        Post::create([
            'author_id' => User::first()->id,
            'title' => 'Mit første indlæg',
            'slug' => 'foerste',
            'content' => '<p>Dansk tekst</p>',
            'visibility' => 'public',
            'published_at' => now()->subDay(),
            'translations' => ['en' => ['title' => 'My first post']],
        ]);

        $this->get('/blog/foerste')->assertSee('Mit første indlæg')->assertSee('Dansk tekst');
        $this->get('/blog/foerste?lang=en')->assertSee('My first post')->assertSee('Dansk tekst');
    }

    public function test_text_settings_can_be_translated(): void
    {
        Setting::setMany(['footer_text' => 'Lavet i Danmark', 'footer_text_en' => 'Made in Denmark']);

        $this->get('/login')->assertOk();
        $this->get('/blog')->assertSee('Lavet i Danmark');
        $this->get('/blog?lang=en')->assertSee('Made in Denmark');

        Setting::setMany(['footer_text_en' => null]);
        $this->get('/blog?lang=en')->assertSee('Lavet i Danmark');
    }

    public function test_admin_panel_stays_english(): void
    {
        $admin = User::role('super_admin')->firstOrFail();

        $this->actingAs($admin)->get('/admin/pages')
            ->assertOk()
            ->assertSee('lang="en"', false)
            ->assertDontSee('lang="da"', false);
    }
}
