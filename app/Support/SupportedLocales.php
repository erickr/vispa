<?php

namespace App\Support;

/**
 * The locales a user can run the panel in.
 *
 * This is the *interface* language only — recipe content stays locale-aware per
 * revision (see RecipeRevision), and a user reading the panel in Swedish can
 * still own revisions in any locale.
 */
class SupportedLocales
{
    public const DEFAULT = 'en';

    /**
     * Locale code => label, written in the language itself.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            'en' => 'English',
            'sv' => 'Svenska',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function codes(): array
    {
        return array_keys(static::options());
    }

    public static function isSupported(?string $locale): bool
    {
        return $locale !== null && array_key_exists($locale, static::options());
    }

    public static function sanitize(?string $locale): string
    {
        return static::isSupported($locale) ? $locale : static::DEFAULT;
    }
}
