<?php

namespace Tests\Feature;

use App\Models\Page;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageMarkdownTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_about_page_as_markdown(): void
    {
        Page::where('slug', 'about')->update(['blocks' => [
            ['type' => 'rich_text', 'data' => [
                'heading' => 'About me',
                'content' => '<p>I build <strong>web apps</strong>. <a href="/blog">Read my blog</a>.</p><p><img src="/storage/media/me.jpg" alt="Me"></p>',
            ]],
            ['type' => 'features', 'data' => ['heading' => 'Skills', 'items' => [
                ['icon' => 'code-bracket', 'title' => 'Laravel', 'text' => 'Since 2019'],
            ]]],
            ['type' => 'contact_form', 'data' => ['heading' => 'Get in touch']],
        ]]);

        $res = $this->get('https://nikolajalbrechtsen.com/about.md');

        $res->assertOk()->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');
        $md = $res->getContent();
        $this->assertStringContainsString('## About me', $md);
        $this->assertStringContainsString('**web apps**', $md);
        $this->assertStringContainsString('(https://nikolajalbrechtsen.com/blog)', $md);       // links made absolute
        $this->assertStringContainsString('(https://nikolajalbrechtsen.com/storage/media/me.jpg)', $md);
        $this->assertStringContainsString('- **Laravel** — Since 2019', $md);
        $this->assertStringNotContainsString('Get in touch', $md);                           // forms are skipped
        $this->assertStringNotContainsString('<', strip_tags($md) === $md ? '' : '<');
    }

    public function test_members_only_and_unknown_pages_are_not_exposed(): void
    {
        $this->get('/members.md')->assertNotFound();
        $this->get('/does-not-exist.md')->assertNotFound();
    }

    public function test_normal_page_route_still_works(): void
    {
        $this->get('/about')->assertOk();
    }
}
