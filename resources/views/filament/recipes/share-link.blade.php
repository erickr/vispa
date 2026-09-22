{{-- The link itself, ready to copy. Deliberately plain text in a box rather than a form field:
     it is something to read and hand on, not something to edit. --}}
<div x-data="{ copied: false }" class="fi-share-link flex flex-col gap-3">
    <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-white/10 dark:bg-white/5">
        <span class="grow break-all font-mono text-sm text-gray-950 dark:text-white">{{ $url }}</span>

        <x-filament::button
            size="sm"
            color="gray"
            x-on:click="window.navigator.clipboard.writeText(@js($url)); copied = true; setTimeout(() => copied = false, 2000)"
        >
            <span x-show="! copied">{{ __('recipe.share.copy') }}</span>
            <span x-show="copied" x-cloak>{{ __('recipe.share.copied') }}</span>
        </x-filament::button>
    </div>

    @if ($isDraft)
        <p class="text-sm text-warning-600 dark:text-warning-400">{{ __('recipe.share.draft_warning') }}</p>
    @endif

    <x-filament::link :href="$url" target="_blank" size="sm">
        {{ __('recipe.share.open') }}
    </x-filament::link>
</div>
