<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\Post;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    /** All permissions used by the admin panel. */
    public const PERMISSIONS = [
        'access_admin' => 'Open the admin panel',
        'manage_pages' => 'Create and edit pages',
        'manage_posts' => 'Create and edit blog posts',
        'manage_messages' => 'Read contact messages',
        'manage_media' => 'Upload and manage media',
        'manage_calendar' => 'Manage calendar events',
        'manage_users' => 'Manage users',
        'manage_roles' => 'Manage roles & permissions',
        'manage_settings' => 'Change site settings',
        'manage_files' => 'Use the file editor',
        'use_code_editor' => 'Use VS Code (code-server)',
    ];

    /**
     * Safe to run many times: it only creates what is missing.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $newPermissions = [];
        foreach (array_keys(self::PERMISSIONS) as $name) {
            if (! Permission::where('name', $name)->where('guard_name', 'web')->exists()) {
                Permission::create(['name' => $name, 'guard_name' => 'web']);
                $newPermissions[] = $name;
            }
        }

        // Default roles. Permissions are only set the first time (or when a brand-new
        // permission is added by an update), so changes you make in
        // Admin → Roles & permissions are never overwritten by `update`.
        $defaults = [
            'super_admin' => [], // gets everything through Gate::before
            'admin' => array_diff(array_keys(self::PERMISSIONS), ['manage_files', 'use_code_editor']),
            'editor' => ['access_admin', 'manage_pages', 'manage_posts', 'manage_messages', 'manage_media', 'manage_calendar'],
            'member' => [],
        ];

        foreach ($defaults as $name => $permissions) {
            $role = Role::where('name', $name)->where('guard_name', 'web')->first();
            if (! $role) {
                Role::create(['name' => $name, 'guard_name' => 'web'])->syncPermissions($permissions);
            } elseif ($added = array_intersect($permissions, $newPermissions)) {
                $role->givePermissionTo($added);
            }
        }

        $this->seedAdmin();
        $this->seedSettings();
        $this->seedContent();
    }

    protected function seedAdmin(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@example.com');

        // Only create the first admin once.
        if (User::role('super_admin')->exists() || User::where('email', $email)->exists()) {
            return;
        }

        $password = env('ADMIN_PASSWORD') ?: Str::password(16, symbols: false);

        $user = User::create([
            'name' => env('ADMIN_NAME', 'Admin'),
            'email' => $email,
            'password' => $password,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->syncRoles(['super_admin']);

        if (! env('ADMIN_PASSWORD')) {
            $this->command?->warn("Admin created: {$email} / {$password}  (change it after logging in!)");
        }
    }

    protected function seedSettings(): void
    {
        if (Setting::query()->exists()) {
            return;
        }

        Setting::setMany([
            'site_name' => config('app.name', 'My Website'),
            'site_tagline' => 'Building things for the web.',
            'site_description' => 'A modern personal website built with Laravel.',
            'primary_color' => '#6366f1',
            'footer_text' => '© '.date('Y').' All rights reserved.',
            'contact_email' => env('ADMIN_EMAIL', 'admin@example.com'),
            'social_links' => [
                ['platform' => 'github', 'url' => 'https://github.com'],
                ['platform' => 'linkedin', 'url' => 'https://linkedin.com'],
            ],
            'registration_enabled' => true,
            'email_verification_required' => false,
            'google_login_enabled' => false,
            'github_login_enabled' => false,
            'blog_enabled' => true,
            'maintenance_message' => null,
        ]);
    }

    protected function seedContent(): void
    {
        if (Page::query()->exists()) {
            return;
        }

        Page::create([
            'title' => 'Home',
            'slug' => 'home',
            'is_homepage' => true,
            'show_in_nav' => false,
            'nav_order' => 0,
            'blocks' => [
                ['type' => 'hero', 'data' => [
                    'eyebrow' => 'Welcome',
                    'heading' => 'Hi, I build modern things for the web.',
                    'subheading' => 'This whole page is editable from the admin panel — drag blocks around, change the text, swap the images.',
                    'primary_label' => 'Get in touch', 'primary_url' => '/contact',
                    'secondary_label' => 'Read the blog', 'secondary_url' => '/blog',
                    'image' => null,
                ]],
                ['type' => 'features', 'data' => [
                    'heading' => 'What I do',
                    'subheading' => 'A few things I care about.',
                    'items' => [
                        ['icon' => 'code-bracket', 'title' => 'Web development', 'text' => 'Fast, accessible websites and apps built with Laravel.'],
                        ['icon' => 'server', 'title' => 'Self-hosting', 'text' => 'Proxmox, containers and automation at home and at work.'],
                        ['icon' => 'sparkles', 'title' => 'Design', 'text' => 'Clean, modern interfaces that are a joy to use.'],
                    ],
                ]],
                ['type' => 'latest_posts', 'data' => ['heading' => 'Latest from the blog', 'count' => 3]],
                ['type' => 'cta', 'data' => [
                    'heading' => 'Let’s work together',
                    'text' => 'Have a project in mind? I would love to hear about it.',
                    'button_label' => 'Contact me', 'button_url' => '/contact',
                ]],
            ],
        ]);

        Page::create([
            'title' => 'About',
            'slug' => 'about',
            'nav_order' => 1,
            'blocks' => [
                ['type' => 'rich_text', 'data' => [
                    'heading' => 'About me',
                    'content' => '<p>Write something about yourself here. Edit this page under <strong>Admin → Pages → About</strong>.</p>',
                ]],
            ],
        ]);

        Page::create([
            'title' => 'Contact',
            'slug' => 'contact',
            'nav_order' => 3,
            'blocks' => [
                ['type' => 'contact_form', 'data' => [
                    'heading' => 'Get in touch',
                    'text' => 'Send me a message and I will get back to you as soon as possible.',
                ]],
            ],
        ]);

        Page::create([
            'title' => 'Members',
            'slug' => 'members',
            'visibility' => 'members',
            'nav_order' => 4,
            'show_in_nav' => false,
            'blocks' => [
                ['type' => 'rich_text', 'data' => [
                    'heading' => 'Members area',
                    'content' => '<p>Only logged-in users can see this page. Set any page or post to <em>Members only</em> in the admin panel.</p>',
                ]],
            ],
        ]);

        Post::create([
            'author_id' => User::query()->value('id'),
            'title' => 'Hello world',
            'slug' => 'hello-world',
            'excerpt' => 'The very first post on the new website.',
            'content' => '<p>This is the first post. Edit or delete it under <strong>Admin → Blog posts</strong>.</p>',
            'tags' => ['news'],
            'published_at' => now(),
        ]);
    }
}
