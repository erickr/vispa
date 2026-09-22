@props(['title' => null, 'languages' => [], 'centered' => false, 'robots' => 'index'])

<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ? $title.' · Vispa' : 'Vispa' }}</title>
        <meta name="robots" content="{{ $robots ?? 'index' }}">
        <style>
            :root { color-scheme: light dark; --ink: #1f2a1c; --muted: #5c6857; --bg: #f7f8f4;
                    --card: #ffffff; --line: #e3e5d9; --accent: #dd1f6e; }
            @media (prefers-color-scheme: dark) {
                :root { --ink: #eef1ea; --muted: #a9b3a3; --bg: #161a14; --card: #1e231c; --line: #2f352c; }
            }
            * { box-sizing: border-box; }
            body { margin: 0; min-height: 100vh; padding: 2rem 1rem; background: var(--bg); color: var(--ink);
                   font: 16px/1.6 ui-sans-serif, system-ui, sans-serif; }
            body.centered { display: grid; place-items: center; }
            main { max-width: 42rem; margin: 0 auto; }
            main.centered { text-align: center; }
            h1 { font: 600 2.5rem/1.2 ui-serif, Georgia, serif; margin: 0 0 1rem; }
            h2 { font: 600 1.35rem/1.3 ui-serif, Georgia, serif; margin: 2.5rem 0 0.75rem; }
            h3 { font-size: 1rem; margin: 1.5rem 0 0.5rem; color: var(--muted); }
            p { margin: 0 0 1rem; }
            .muted { color: var(--muted); }
            .actions { margin-top: 2.5rem; display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap; }
            .actions a { padding: 0.6rem 1.4rem; border-radius: 0.5rem; text-decoration: none; font-weight: 600;
                         border: 2px solid var(--accent); color: var(--accent); }
            .actions a.primary { background: var(--accent); color: #fff; }
            .languages { position: absolute; top: 1rem; right: 1rem; display: flex; gap: 0.25rem; font-size: 0.9rem; }
            .languages a { padding: 0.2rem 0.6rem; border-radius: 0.4rem; color: var(--muted); text-decoration: none; }
            .languages a[aria-current] { color: var(--ink); font-weight: 600;
                                         background: color-mix(in srgb, var(--accent) 12%, transparent); }
            .brand { display: inline-flex; align-items: center; gap: 0.4rem; color: inherit; text-decoration: none; }
            .whisk { transform: rotate(45deg); flex: none; }

            /* The shared recipe page. */
            .cover { width: 100%; border-radius: 1rem; margin-bottom: 1.5rem; aspect-ratio: 3 / 2; object-fit: cover; }
            .meta { display: flex; flex-wrap: wrap; gap: 0.5rem 1rem; color: var(--muted); margin-bottom: 1.5rem; }
            .badge { display: inline-block; padding: 0.1rem 0.6rem; border-radius: 999px; font-size: 0.85rem;
            border: 1px solid var(--line); background: var(--card); }
            .source { display: block; padding: 0.9rem 1.1rem; border: 1px solid var(--line); border-left: 3px solid var(--accent);
            border-radius: 0.6rem; background: var(--card); margin-bottom: 1.5rem; text-decoration: none; color: inherit; }
            ul.ingredients { list-style: none; padding: 0; margin: 0; }
            ul.ingredients li { padding: 0.4rem 0; border-bottom: 1px solid var(--line); }
            ol.steps { padding-left: 1.4rem; margin: 0; }
            ol.steps li { padding: 0.35rem 0; }
            .notes { padding: 1rem 1.2rem; border-radius: 0.6rem; background: var(--card); border: 1px solid var(--line); }
            .gallery { display: grid; grid-template-columns: repeat(auto-fill, minmax(9rem, 1fr)); gap: 0.75rem; }
            .gallery img { width: 100%; aspect-ratio: 1; object-fit: cover; border-radius: 0.6rem; }
            footer { margin-top: 3.5rem; padding-top: 1.5rem; border-top: 1px solid var(--line); color: var(--muted);
            display: flex; justify-content: space-between; gap: 1rem; flex-wrap: wrap; font-size: 0.9rem; }
        </style>
    </head>
    <body @class(['centered' => $centered])>
        @if (count($languages) > 1)
            <nav class="languages" aria-label="{{ __('landing.language') }}">
                @foreach ($languages as $language)
                    <a href="{{ $language['url'] }}" lang="{{ $language['code'] }}" hreflang="{{ $language['code'] }}"
                        @if ($language['current']) aria-current="true" @endif>{{ $language['label'] }}</a>
                @endforeach
            </nav>
        @endif

        <main @class(['centered' => $centered])>
            {{ $slot }}
        </main>
    </body>
</html>
