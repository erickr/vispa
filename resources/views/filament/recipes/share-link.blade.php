{{-- The link itself, ready to copy. Deliberately plain text in a box rather than a form field:
     it is something to read and hand on, not something to edit.

     The URL and the copying live in x-data, not in the button's own x-on:click: Blade does not
     compile directives inside a component's attributes, so an @js() there would reach the browser
     verbatim. Clipboard access needs a secure context, which a panel served over plain http on
     anything but localhost is not, hence the textarea fallback. --}}
<div
    class="fi-share-link flex flex-col gap-3"
    x-data="{
        url: @js($url),
        copied: false,
        copy() {
            if (window.navigator.clipboard && window.isSecureContext) {
                window.navigator.clipboard.writeText(this.url)
            } else {
                const area = document.createElement('textarea')
                area.value = this.url
                area.setAttribute('readonly', '')
                area.style.position = 'fixed'
                area.style.opacity = '0'
                document.body.appendChild(area)
                area.select()
                document.execCommand('copy')
                area.remove()
            }

            this.copied = true
            setTimeout(() => this.copied = false, 2000)
        },
    }"
>
    <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-white/10 dark:bg-white/5">
        <span class="grow break-all font-mono text-sm text-gray-950 dark:text-white">{{ $url }}</span>

        <x-filament::button size="sm" color="gray" x-on:click="copy()">
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
