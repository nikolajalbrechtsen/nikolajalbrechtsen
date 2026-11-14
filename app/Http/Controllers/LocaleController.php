<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\RedirectResponse;

class LocaleController extends Controller
{
    /** Switch the website's language and go back to the page the visitor was on. */
    public function __invoke(string $locale): RedirectResponse
    {
        SetLocale::remember($locale);

        $back = url()->previous();

        // Only redirect within this site, and never back to the switcher itself.
        if (! str_starts_with($back, url('/')) || str_contains($back, '/lang/')) {
            $back = route('home');
        }

        return redirect()->to($back);
    }
}
