<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Locale;

/**
 * One row per visitor (IP) per day — see the TrackVisitor middleware.
 */
class Visit extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'first_seen' => 'datetime',
            'last_seen' => 'datetime',
        ];
    }

    /** "Copenhagen, Capital Region, Denmark" */
    public static function formatLocation(?string $country, ?string $region = null, ?string $city = null): ?string
    {
        $country = strtoupper((string) $country);
        if ($country === '' || in_array($country, ['XX', 'T1'], true)) {
            $label = $country === 'T1' ? 'Tor network' : null;

            return $label ?? (($city || $region) ? implode(', ', array_filter([$city, $region])) : null);
        }

        $name = class_exists(Locale::class) ? (Locale::getDisplayRegion('-'.$country, 'en') ?: $country) : $country;

        return implode(', ', array_unique(array_filter([$city, $region, $name])));
    }

    /** Very small user-agent summary: "Chrome on Windows". */
    public static function describeAgent(?string $agent): ?string
    {
        if (! $agent) {
            return null;
        }

        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') => 'Opera',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            default => 'Other browser',
        };
        $os = match (true) {
            str_contains($agent, 'iPhone'), str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Mac OS') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => null,
        };

        return $os ? "{$browser} on {$os}" : $browser;
    }
}
