<?php

namespace App\Models\Concerns;

use App\Support\Locales;

/**
 * Per-language versions of a model's text fields.
 *
 * The normal columns hold the Danish (default) content. Other languages live
 * in the "translations" JSON column: {"en": {"title": "...", "blocks": [...]}}.
 * A field without a translation falls back to the Danish content.
 */
trait HasTranslations
{
    public function initializeHasTranslations(): void
    {
        $this->mergeCasts(['translations' => 'array']);
        $this->mergeFillable(['translations']);
    }

    /** The field in the current (or given) language, falling back to Danish. */
    public function translate(string $field, ?string $locale = null): mixed
    {
        $locale ??= app()->getLocale();

        if (! Locales::isDefault($locale)) {
            $value = data_get($this->translations, "{$locale}.{$field}");

            if (filled($value)) {
                return $value;
            }
        }

        return $this->getAttribute($field);
    }

    /** Whether any of the translatable fields has content in the given language. */
    public function hasTranslation(string $locale): bool
    {
        if (Locales::isDefault($locale)) {
            return true;
        }

        return collect($this->translations[$locale] ?? [])->filter(fn ($value) => filled($value))->isNotEmpty();
    }

    /** @return array<string> the language codes this record has content in */
    public function availableLocales(): array
    {
        return array_values(array_filter(array_keys(Locales::all()), fn ($locale) => $this->hasTranslation($locale)));
    }
}
