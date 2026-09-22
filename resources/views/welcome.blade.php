<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Vispa · {{ __('landing.title') }}</title>
        <style>
            :root { color-scheme: light dark; --ink: #1f2a1c; --muted: #5c6857; --bg: #f7f8f4; --accent: #dd1f6e; }
            @media (prefers-color-scheme: dark) { :root { --ink: #eef1ea; --muted: #a9b3a3; --bg: #161a14; } }
            body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 2rem 1rem;
                   box-sizing: border-box; background: var(--bg); color: var(--ink);
                   font: 16px/1.6 ui-sans-serif, system-ui, sans-serif; }
            main { max-width: 40rem; text-align: center; }
            h1 { font: 600 2.5rem/1.2 ui-serif, Georgia, serif; margin: 0 0 2rem; }
            h1 svg { vertical-align: -0.1em; margin-right: 0.3rem; }
            p { margin: 0; }
            .actions { margin-top: 2.5rem; display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap; }
            .actions a { padding: 0.6rem 1.4rem; border-radius: 0.5rem; text-decoration: none; font-weight: 600;
                border: 2px solid var(--accent); color: var(--accent); }
            .actions a.primary { background: var(--accent); color: #fff; }
            .languages { position: absolute; top: 1rem; right: 1rem; display: flex; gap: 0.25rem; font-size: 0.9rem; }
            .languages a { padding: 0.2rem 0.6rem; border-radius: 0.4rem; color: var(--muted); text-decoration: none; }
            .languages a[aria-current] { color: var(--ink); font-weight: 600; background: color-mix(in srgb, var(--accent) 12%, transparent); }
        </style>
    </head>
    <body>
        <nav class="languages" aria-label="{{ __('landing.language') }}">
            @foreach ($locales as $code => $label)
                <a href="{{ route('landing', ['lang' => $code]) }}" lang="{{ $code }}" hreflang="{{ $code }}"
                    @if ($code === app()->getLocale()) aria-current="true" @endif>{{ $label }}</a>
            @endforeach
        </nav>

        <main>
            <h1>
                <svg width="36" height="36" viewBox="0 0 26 26" aria-hidden="true">
                    <path d="M13 2v7" stroke="#dd1f6e" stroke-width="2.4" stroke-linecap="round" />
                    <g fill="none" stroke="currentColor" stroke-width="1.6" opacity="0.85">
                        <path d="M13 9c-4 0-6 3.4-6 7.5S9.2 24 13 24s6-3.4 6-7.5S17 9 13 9z" />
                        <path d="M13 9c-1.9 0-2.7 3.4-2.7 7.5S11.6 24 13 24s2.7-3.4 2.7-7.5S14.9 9 13 9z" />
                        <path d="M7.4 16.5h11.2" stroke-width="1.3" />
                    </g>
                </svg>
                {{ __('landing.title') }}
            </h1>

            <p>{{ __('landing.description') }}</p>

            <nav class="actions">
                <a class="primary" href="{{ route('filament.app.auth.login') }}">{{ __('landing.login') }}</a>
                <a href="{{ route('filament.app.auth.register') }}">{{ __('landing.register') }}</a>
            </nav>
        </main>
    </body>
</html>
