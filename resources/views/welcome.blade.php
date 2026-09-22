<x-public-page :languages="$languages" centered>
    <h1>
        <x-whisk :size="36" style="vertical-align: -0.15em; margin-right: 0.3rem;" />
        Vispa
    </h1>

    <p>{{ __('landing.description') }}</p>

    <nav class="actions">
        <a class="primary" href="{{ route('filament.app.auth.login') }}">{{ __('landing.login') }}</a>
        <a href="{{ route('filament.app.auth.register') }}">{{ __('landing.register') }}</a>
    </nav>
</x-public-page>
