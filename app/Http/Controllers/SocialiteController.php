<?php

namespace App\Http\Controllers;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class SocialiteController extends Controller
{
    public const PROVIDERS = ['google', 'github'];

    public static function enabled(string $provider): bool
    {
        return (bool) setting("{$provider}_login_enabled")
            && filled(setting("{$provider}_client_id"))
            && filled(setting("{$provider}_client_secret"));
    }

    public function redirect(string $provider)
    {
        abort_unless(self::enabled($provider), 404);

        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider)
    {
        abort_unless(self::enabled($provider), 404);

        try {
            $social = Socialite::driver($provider)->user();
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('login')->withErrors(['email' => 'Login with '.ucfirst($provider).' failed. Please try again.']);
        }

        // 1. Already linked → log in.
        $account = SocialAccount::where('provider', $provider)->where('provider_id', $social->getId())->first();

        if ($account) {
            return $this->login($account->user);
        }

        if (blank($social->getEmail())) {
            return redirect()->route('login')->withErrors(['email' => 'Your '.ucfirst($provider).' account has no public email address.']);
        }

        // 2. Existing user with same email (or logged in) → link the account.
        $user = auth()->user() ?? User::where('email', $social->getEmail())->first();

        // 3. Otherwise create a new user, if sign-ups are allowed.
        if (! $user && ! setting('registration_enabled', true)) {
            return redirect()->route('login')->withErrors(['email' => 'New sign-ups are currently disabled.']);
        }

        $user = DB::transaction(function () use ($user, $social, $provider) {
            $isNew = ! $user;

            $user ??= User::create([
                'name' => $social->getName() ?: $social->getNickname() ?: 'User',
                'email' => $social->getEmail(),
                'password' => null,
                'avatar' => $social->getAvatar(),
            ]);

            // The provider verified the email address for us.
            if (! $user->email_verified_at) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            $user->socialAccounts()->create([
                'provider' => $provider,
                'provider_id' => $social->getId(),
                'avatar' => $social->getAvatar(),
            ]);

            if ($isNew) {
                event(new Registered($user));
            }

            return $user;
        });

        return $this->login($user);
    }

    protected function login(User $user)
    {
        if (! $user->is_active) {
            return redirect()->route('login')->withErrors(['email' => 'This account has been disabled.']);
        }

        Auth::login($user, remember: true);
        request()->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
