<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Vispa</title>
        <style>
            :root { color-scheme: light dark; --ink: #1f2a1c; --muted: #5c6857; --bg: #f7f8f4; --accent: #dd1f6e; }
            @media (prefers-color-scheme: dark) { :root { --ink: #eef1ea; --muted: #a9b3a3; --bg: #161a14; } }
            body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 2rem 1rem;
                   box-sizing: border-box; background: var(--bg); color: var(--ink);
                   font: 16px/1.6 ui-sans-serif, system-ui, sans-serif; }
            main { max-width: 40rem; text-align: center; }
            h1 { font: 600 2.5rem/1.2 ui-serif, Georgia, serif; margin: 0 0 2rem; }
            h1 svg { vertical-align: -0.1em; margin-right: 0.3rem; }
            section { margin-bottom: 1.5rem; }
            h2 { font-size: 0.8rem; letter-spacing: 0.1em; text-transform: uppercase; color: var(--muted); margin: 0 0 0.25rem; }
            p { margin: 0; }
            nav { margin-top: 2.5rem; display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap; }
            a { padding: 0.6rem 1.4rem; border-radius: 0.5rem; text-decoration: none; font-weight: 600;
                border: 2px solid var(--accent); color: var(--accent); }
            a.primary { background: var(--accent); color: #fff; }
        </style>
    </head>
    <body>
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
                Vispa recipe site
            </h1>

            <section lang="en">
                <h2>English</h2>
                <p>Collect your recipes in one place. Write them yourself or import one from a link,
                    add photos, keep every version, and keep each recipe in several languages.</p>
            </section>

            <section lang="sv">
                <h2>Svenska</h2>
                <p>Samla dina recept på ett ställe. Skriv dem själv eller importera ett från en länk,
                    lägg till bilder, behåll varje version och ha varje recept på flera språk.</p>
            </section>

            <nav>
                <a class="primary" href="{{ route('filament.app.auth.login') }}">Log in / Logga in</a>
                <a href="{{ route('filament.app.auth.register') }}">Register / Registrera dig</a>
            </nav>
        </main>
    </body>
</html>
