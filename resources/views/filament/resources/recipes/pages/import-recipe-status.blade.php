<x-filament-panels::page>
    <div @unless ($import->isFinished()) wire:poll.2s="check" @endunless>
        <x-filament::section>
            <div class="flex items-start gap-3">
                @if ($import->status === \App\Models\RecipeImport::STATUS_FAILED)
                    <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-6 w-6 shrink-0 text-danger-500" />
                @elseif ($import->status === \App\Models\RecipeImport::STATUS_DONE)
                    <x-filament::icon icon="heroicon-o-check-circle" class="h-6 w-6 shrink-0 text-success-500" />
                @else
                    <x-filament::loading-indicator class="h-6 w-6 shrink-0" />
                @endif

                <div class="min-w-0 space-y-1">
                    <p class="font-medium">{{ __('recipe.import.status.'.$import->status) }}</p>

                    @if ($import->error)
                        <p class="text-sm text-gray-600 dark:text-gray-300">{{ $import->error }}</p>
                    @endif

                    <p class="truncate text-sm text-gray-500 dark:text-gray-400">{{ $import->source_url }}</p>
                </div>
            </div>

            @if ($import->status === \App\Models\RecipeImport::STATUS_FAILED)
                <div class="mt-4 flex flex-wrap gap-2">
                    <x-filament::button wire:click="retry" icon="heroicon-o-arrow-path">
                        {{ __('recipe.import.retry') }}
                    </x-filament::button>
                    <x-filament::button color="gray" tag="a" :href="\App\Filament\Resources\Recipes\RecipeResource::getUrl('index')">
                        {{ __('recipe.import.back') }}
                    </x-filament::button>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
