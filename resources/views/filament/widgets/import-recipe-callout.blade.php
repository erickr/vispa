<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex flex-col gap-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="space-y-1">
                    <h2 class="fi-header-heading text-xl font-semibold text-gray-950 dark:text-white">
                        {{ __('recipe.import.callout.heading') }}
                    </h2>
                    <p class="max-w-2xl text-sm text-gray-600 dark:text-gray-300">
                        {{ __('recipe.import.callout.description') }}
                    </p>
                </div>

                <x-filament::button
                    tag="a"
                    :href="$this->importUrl()"
                    icon="heroicon-o-arrow-down-tray"
                    size="lg"
                    class="shrink-0"
                >
                    {{ __('recipe.import.action') }}
                </x-filament::button>
            </div>

            <ul class="grid gap-4 sm:grid-cols-3">
                @foreach ($this->benefits() as $benefit)
                    <li class="flex gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-600 dark:bg-primary-400/10 dark:text-primary-300">
                            <x-filament::icon :icon="$benefit['icon']" class="h-5 w-5" />
                        </span>

                        <div class="space-y-0.5">
                            <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $benefit['title'] }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">{{ $benefit['body'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
