<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * What language a signed-out visitor reads the public pages in: their own choice if they have
 * made one (remembered for the session), otherwise the best match for their browser.
 *
 * The panel has its own rule — a signed-in user's saved preference, see SetUserLocale.
 */
class PublicLocale
{
    public const SESSION_KEY = 'public_locale';

    /**
     * @param  array<int, string>|null  $available  the locales this page can actually be read in,
     *                                              defaulting to every locale Vispa speaks
     */
    public static function resolve(Request $request, ?array $available = null): string
    {
        $available = array_values(array_filter(
            $available ?? SupportedLocales::codes(),
            fn (string $locale): bool => SupportedLocales::isSupported($locale),
        ));

        if ($available === []) {
            $available = SupportedLocales::codes();
        }

        // ?lang= is the toggle. It is remembered even when this page cannot honour it, so the
        // choice still holds on the next page that can.
        $chosen = $request->query('lang');

        if (SupportedLocales::isSupported($chosen)) {
            $request->session()->put(static::SESSION_KEY, $chosen);
        }

        $preferences = array_filter([
            $request->session()->get(static::SESSION_KEY),
            $request->getPreferredLanguage($available),
        ]);

        foreach ($preferences as $preference) {
            if (in_array($preference, $available, true)) {
                return $preference;
            }
        }

        return $available[0];
    }

    /**
     * The toggle: one link per locale, each back to this page in that language.
     *
     * @param  array<int, string>|null  $available
     * @return array<int, array{code: string, label: string, url: string, current: bool}>
     */
    public static function links(string $route, array $parameters = [], ?array $available = null, ?string $current = null): array
    {
        $current ??= app()->getLocale();
        $options = SupportedLocales::options();

        if ($available !== null) {
            $options = array_intersect_key($options, array_flip($available));
        }

        return array_values(array_map(fn (string $label, string $code): array => [
            'code' => $code,
            'label' => $label,
            'url' => route($route, $parameters + ['lang' => $code]),
            'current' => $code === $current,
        ], $options, array_keys($options)));
    }
}
