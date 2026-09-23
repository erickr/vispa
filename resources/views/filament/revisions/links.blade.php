{{-- Every version of this recipe, newest first, each a way back into it. The one being read is
     shown as plain text: a link to the page you are on is a dead end.

     A table rather than a list: the entry this sits in lays its children out in a row, so a <ul>
     of <li>s ends up on one line. The table is a single child of that row and rules its own. --}}
<table class="w-full table-auto text-sm">
    <thead>
        <tr class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-500 dark:border-white/10 dark:text-gray-400">
            <th class="py-1.5 pr-4 text-start font-medium">{{ __('revision.fields.version') }}</th>
            <th class="py-1.5 pr-4 text-start font-medium">{{ __('revision.fields.status') }}</th>
            <th class="py-1.5 text-start font-medium">{{ __('revision.fields.title') }}</th>
        </tr>
    </thead>

    <tbody>
        @foreach ($revisions as $revision)
            @php $isCurrent = $revision->is($current); @endphp

            <tr class="border-b border-gray-100 last:border-0 dark:border-white/5">
                <td class="whitespace-nowrap py-1.5 pr-4 align-top">
                    @if ($isCurrent)
                        <span class="font-semibold text-gray-950 dark:text-white">{{ $revision->locale }} v{{ $revision->version_number }}</span>
                    @else
                        <x-filament::link
                            :href="\App\Filament\Resources\RecipeRevisions\RecipeRevisionResource::getUrl('view', ['record' => $revision])"
                            size="sm"
                        >
                            {{ $revision->locale }} v{{ $revision->version_number }}
                        </x-filament::link>
                    @endif
                </td>

                <td class="whitespace-nowrap py-1.5 pr-4 align-top">
                    <x-filament::badge
                        :color="match ($revision->status) {
                            'published' => 'success',
                            'draft' => 'warning',
                            default => 'gray',
                        }"
                        size="xs"
                    >
                        {{ \App\Models\RecipeRevision::statusWord($revision->status) }}
                    </x-filament::badge>
                </td>

                <td class="py-1.5 align-top text-gray-500 dark:text-gray-400">
                    {{ $revision->title }}
                    @if ($isCurrent)
                        <span class="whitespace-nowrap">· {{ __('revision.infolist.you_are_here') }}</span>
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
