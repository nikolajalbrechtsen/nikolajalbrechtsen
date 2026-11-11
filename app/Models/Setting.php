<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public const CACHE_KEY = 'site-settings';

    /** Settings whose key ends in "_secret" are stored encrypted. */
    public static function isSecret(string $key): bool
    {
        return Str::endsWith($key, '_secret');
    }

    /** @return array<string, mixed> */
    public static function allCached(): array
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY, function () {
                if (! Schema::hasTable('settings')) {
                    return [];
                }

                return static::query()->pluck('value', 'key')
                    ->map(fn ($value, $key) => static::decode($key, $value))
                    ->all();
            });
        } catch (Throwable) {
            return []; // e.g. database not reachable yet during install
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = static::allCached()[$key] ?? null;

        return ($value === null || $value === '') ? $default : $value;
    }

    /** @param array<string, mixed> $values */
    public static function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            if (static::isSecret($key) && blank($value)) {
                continue; // a blank secret field means "keep the existing value"
            }
            static::updateOrCreate(['key' => $key], ['value' => static::encode($key, $value)]);
        }
        Cache::forget(self::CACHE_KEY);
    }

    protected static function encode(string $key, mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $json = json_encode($value);

        return static::isSecret($key) ? Crypt::encryptString($json) : $json;
    }

    protected static function decode(string $key, ?string $value): mixed
    {
        if ($value === null) {
            return null;
        }
        try {
            if (static::isSecret($key)) {
                $value = Crypt::decryptString($value);
            }

            return json_decode($value, true);
        } catch (Throwable) {
            return null;
        }
    }
}
