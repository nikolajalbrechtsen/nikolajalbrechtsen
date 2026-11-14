<?php

namespace App\Support;

/**
 * The languages the website can be shown in (config/app.php → locales).
 * Danish is the default; content falls back to it when a translation is missing.
 */
class Locales
{
    public const SESSION_KEY = 'locale';

    public const COOKIE = 'locale';

    /** @return array<string, string> code => name */
    public static function all(): array
    {
        return config('app.locales', ['da' => 'Dansk']);
    }

    public static function default(): string
    {
        return config('app.default_locale', 'da');
    }

    /** @return array<string, string> every language except the default one */
    public static function translations(): array
    {
        return array_diff_key(static::all(), [static::default() => true]);
    }

    public static function isSupported(mixed $locale): bool
    {
        return is_string($locale) && array_key_exists($locale, static::all());
    }

    public static function isDefault(?string $locale = null): bool
    {
        return ($locale ?? app()->getLocale()) === static::default();
    }
}
