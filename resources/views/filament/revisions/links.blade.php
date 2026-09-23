{{-- Every version of this recipe, newest first, each a way back into it. The one being read is
     shown as plain text: a link to the page you are on is a dead end. --}}
<ul class="flex flex-col gap-1">
    @foreach ($revisions as $revision)
        @php $isCurrent = $revision->is($current); @endphp

        <li class="flex items-center gap-2 text-sm">
            @if ($isCurrent)
                <span class="font-semibold text-gray-950 dark:text-white">{{ $revision->locale }} v{{ $revision->version_number }}</span>
            @else
                <x-filament::link :href="\App\Filament\Resources\RecipeRevisions\RecipeRevisionResource::getUrl('view', ['record' => $revision])" size="sm">
                    {{ $revision->locale }} v{{ $revision->version_number }}
                </x-filament::link>
            @endif

            <x-filament::badge :color="match ($revision->status) {
                'published' => 'success',
                'draft' => 'warning',
                default => 'gray',
            }" size="xs">
                {{ \App\Models\RecipeRevision::statusWord($revision->status) }}
            </x-filament::badge>

            <span class="fi-text-color-gray truncate text-gray-500 dark:text-gray-400">{{ $revision->title }}</span>

            @if ($isCurrent)
                <span class="text-gray-500 dark:text-gray-400">· {{ __('revision.infolist.you_are_here') }}</span>
            @endif
        </li>
    @endforeach
</ul>
