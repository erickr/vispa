<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;

/**
 * The locales Vispa speaks: both the language the panel is read in and the ones offered
 * when picking a locale for a recipe, a revision or a catalog translation.
 *
 * The domain itself stays open — locale columns are plain strings and a row written in
 * some other language keeps working. optionsIncluding() is how a picker stays honest
 * about a value from outside this list rather than silently dropping it.
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

    /**
     * The options for a picker holding $current, which may be a locale from outside this
     * list — an older row, or one seeded before a language was retired. It is offered as
     * itself so opening the record does not quietly rewrite it.
     *
     * @return array<string, string>
     */
    public static function optionsIncluding(?string $current): array
    {
        $options = static::options();

        if (filled($current) && ! array_key_exists($current, $options)) {
            $options[$current] = $current;
        }

        return $options;
    }

    /**
     * What a new row starts on: whatever language the author is reading the panel in.
     */
    public static function preferred(): string
    {
        return static::sanitize(Auth::user()?->locale);
    }
}
