<?php

namespace Tests\Feature;

use App\Filament\Pages\FileEditor;
use App\Filament\Pages\Settings;
use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    protected function admin(): User
    {
        return User::role('super_admin')->firstOrFail();
    }

    public function test_admin_pages_load_for_super_admin(): void
    {
        $this->actingAs($this->admin());

        $page = Page::first();

        foreach ([
            '/admin', '/admin/pages', '/admin/pages/create', "/admin/pages/{$page->id}/edit",
            '/admin/posts', '/admin/posts/create', '/admin/users', '/admin/users/create',
            '/admin/roles', '/admin/contact-messages', '/admin/settings', '/admin/file-editor', '/admin/profile',
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_members_cannot_open_admin(): void
    {
        $member = User::factory()->create();
        $this->assertTrue($member->hasRole('member'));

        $this->actingAs($member)->get('/admin')->assertForbidden();
        $this->actingAs($member)->get('/_code-auth')->assertUnauthorized();
    }

    public function test_editor_only_sees_content(): void
    {
        $editor = User::factory()->create();
        $editor->syncRoles(['editor']);

        $this->actingAs($editor);
        $this->get('/admin/pages')->assertOk();
        $this->get('/admin/users')->assertForbidden();
        $this->get('/admin/settings')->assertForbidden();
        $this->get('/admin/file-editor')->assertForbidden();
    }

    public function test_code_server_auth_allows_super_admin(): void
    {
        $this->actingAs($this->admin())->get('/_code-auth')->assertNoContent();
    }

    public function test_settings_can_be_saved(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(Settings::class)
            ->set('data.site_name', 'Test Site')
            ->set('data.github_client_secret', 'shh-secret')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Test Site', Setting::get('site_name'));
        $this->assertSame('shh-secret', Setting::get('github_client_secret'));
        // stored encrypted
        $this->assertStringNotContainsString('shh-secret', Setting::where('key', 'github_client_secret')->value('value'));

        // a blank secret keeps the stored value
        Livewire::test(Settings::class)->set('data.github_client_secret', '')->call('save');
        $this->assertSame('shh-secret', Setting::get('github_client_secret'));

        $this->get('/')->assertSee('Test Site');
    }

    public function test_file_editor_reads_writes_and_blocks_escapes(): void
    {
        $this->actingAs($this->admin());
        $file = 'storage/app/editor-test.txt';
        @unlink(base_path($file));

        $c = Livewire::test(FileEditor::class);

        $this->assertNotEmpty($c->instance()->listDir(''));
        $this->assertTrue($c->instance()->createItem($file, 'file')['ok']);
        $this->assertTrue($c->instance()->saveFile($file, 'hello')['ok']);
        $this->assertSame('hello', $c->instance()->openFile($file)['content']);
        $this->assertFalse($c->instance()->openFile('../outside.txt')['ok']);
        $this->assertFalse($c->instance()->openFile('vendor/autoload.php')['ok']);
        $this->assertTrue($c->instance()->deleteItem($file)['ok']);
        $this->assertFileDoesNotExist(base_path($file));
    }

    public function test_members_only_page_requires_login(): void
    {
        $this->get('/members')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/members')->assertOk()->assertSee('Members area');
    }

    public function test_contact_form_stores_message(): void
    {
        Livewire::test('contact-form')
            ->set('name', 'Jane')
            ->set('email', 'jane@example.com')
            ->set('message', 'Hello there, nice website!')
            ->call('send')
            ->assertSet('sent', true);

        $this->assertDatabaseHas('contact_messages', ['email' => 'jane@example.com']);
    }

    public function test_registration_can_be_disabled(): void
    {
        $this->get('/register')->assertOk();
        Setting::setMany(['registration_enabled' => false]);
        $this->get('/register')->assertRedirect('/login');
    }

    public function test_inactive_users_are_logged_out(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $this->actingAs($user)->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }
}
