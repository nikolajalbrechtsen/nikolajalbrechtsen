<?php

namespace App\Providers;

use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Queue jobs / emails (no web request) use the public https address.
        // Web requests are handled per request by the DetectHttpsProxy middleware.
        if ($this->app->runningInConsole() && str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // Super admins can do everything.
        Gate::before(fn (User $user) => $user->hasRole('super_admin') ? true : null);

        // Every new user (sign-up or social login) becomes a "member".
        User::created(function (User $user) {
            try {
                if (Role::where('name', 'member')->exists()) {
                    $user->assignRole('member');
                }
            } catch (Throwable) {
                // roles table not migrated yet
            }
        });

        $this->applySettings();

        View::composer(['components.layouts.site'], function ($view) {
            $view->with('navPages', $this->navPages());
        });
    }

    /**
     * Settings saved in the admin panel override config values at runtime.
     */
    protected function applySettings(): void
    {
        $settings = Setting::allCached();
        if (empty($settings)) {
            return;
        }

        config([
            'app.name' => $settings['site_name'] ?? config('app.name'),
            'mail.from.name' => $settings['site_name'] ?? config('mail.from.name'),
            'services.google' => [
                'client_id' => $settings['google_client_id'] ?? null,
                'client_secret' => $settings['google_client_secret'] ?? null,
                'redirect' => url('/auth/google/callback'),
            ],
            'services.github' => [
                'client_id' => $settings['github_client_id'] ?? null,
                'client_secret' => $settings['github_client_secret'] ?? null,
                'redirect' => url('/auth/github/callback'),
            ],
        ]);
    }

    protected function navPages()
    {
        static $pages;

        try {
            return $pages ??= Schema::hasTable('pages')
                ? Page::published()->where('show_in_nav', true)->orderBy('nav_order')->get(['id', 'title', 'slug', 'is_homepage', 'visibility'])
                : collect();
        } catch (Throwable) {
            return collect();
        }
    }
}
