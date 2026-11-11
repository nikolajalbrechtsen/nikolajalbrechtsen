<?php

use App\Http\Controllers\CodeServerAuthController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\SocialiteController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

/*
|--------------------------------------------------------------------------
| Public website
|--------------------------------------------------------------------------
*/

Route::get('/', [SiteController::class, 'home'])->name('home');
Route::get('/blog', [SiteController::class, 'blog'])->name('blog.index');
Route::get('/blog/{slug}', [SiteController::class, 'post'])->name('blog.show');

/*
|--------------------------------------------------------------------------
| User account (logged-in members)
|--------------------------------------------------------------------------
*/

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

/*
|--------------------------------------------------------------------------
| Social login (Google / GitHub) — configured in Admin → Settings
|--------------------------------------------------------------------------
*/

Route::get('/auth/{provider}/redirect', [SocialiteController::class, 'redirect'])
    ->whereIn('provider', SocialiteController::PROVIDERS)
    ->name('social.redirect');
Route::get('/auth/{provider}/callback', [SocialiteController::class, 'callback'])
    ->whereIn('provider', SocialiteController::PROVIDERS)
    ->name('social.callback');

/*
|--------------------------------------------------------------------------
| Used by Nginx to protect VS Code (code-server) at /code/
|--------------------------------------------------------------------------
*/

Route::any('/_code-auth', CodeServerAuthController::class)->name('code.auth');

require __DIR__.'/auth.php';

/*
|--------------------------------------------------------------------------
| CMS pages — must stay last so it doesn't swallow other routes
|--------------------------------------------------------------------------
*/

Route::get('/{slug}', [SiteController::class, 'page'])
    ->where('slug', '[a-z0-9\-]+')
    ->name('page.show');
