{{-- The public pages' language toggle, on the panel's login and registration pages. --}}
<nav class="absolute end-4 top-4 flex gap-1 text-sm" aria-label="{{ __('landing.language') }}">
    @foreach ($languages as $language)
        <a
            href="{{ $language['url'] }}"
            lang="{{ $language['code'] }}"
            hreflang="{{ $language['code'] }}"
            @if ($language['current']) aria-current="true" @endif
            @class([
                'rounded-md px-2.5 py-1',
                'bg-primary-50 font-semibold text-gray-950 dark:bg-primary-950 dark:text-white' => $language['current'],
                'text-gray-500 hover:text-gray-950 dark:text-gray-400 dark:hover:text-white' => ! $language['current'],
            ])
        >{{ $language['label'] }}</a>
    @endforeach
</nav>
