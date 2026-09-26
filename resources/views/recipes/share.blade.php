@php
    use App\Models\RecipeRevisionIngredient;

    $cover = $revision->coverImage();
    $ungroupedIngredients = $revision->ingredients->whereNull('group_id');
    $unsectionedSteps = $revision->instructionSteps->whereNull('section_id');
    $gallery = $revision->images->where('id', '!=', $cover?->id);
    $times = array_filter([
        $revision->servings ? trans_choice('recipe.summary.servings', $revision->servings) : null,
        $revision->prep_time_minutes ? __('share.prep', ['minutes' => $revision->prep_time_minutes]) : null,
        $revision->cook_time_minutes ? __('share.cook', ['minutes' => $revision->cook_time_minutes]) : null,
    ]);
@endphp

<x-public-page
    :title="$revision->title"
    :languages="$languages"
    :robots="$recipe->visibility === 'public' ? 'index' : 'noindex'"
>
    @if ($cover)
        <img class="cover" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($cover->path) }}"
            alt="{{ $cover->alt_text ?? $revision->title }}">
    @endif

    <h1>{{ $revision->title }}</h1>

    <div class="meta">
        @foreach ($times as $item)
            <span>{{ $item }}</span>
        @endforeach
        @if ($revision->status !== 'published')
            <span class="badge">{{ \App\Models\RecipeRevision::statusWord($revision->status) }}</span>
        @endif
    </div>

    @if ($revision->description)
        <p>{{ $revision->description }}</p>
    @endif

    {{-- Keep it in your own household's recipes: read-only there, with a way to make it yours. --}}
    @if ($save)
        <div class="save">
            @if ($save['state'] === 'save')
                <form method="POST" action="{{ $save['url'] }}">
                    @csrf
                    <button type="submit" class="primary">{{ __('share.save.action') }}</button>
                </form>
                <span class="muted">{{ __('share.save.hint') }}</span>
            @elseif ($save['state'] === 'guest')
                <a class="primary" href="{{ $save['url'] }}">{{ __('share.save.sign_in') }}</a>
                <span class="muted">{{ __('share.save.hint') }}</span>
            @else
                <a href="{{ $save['url'] }}">{{ __('share.save.open') }}</a>
                <span class="muted">{{ __('share.save.'.$save['state']) }}</span>
            @endif
        </div>
    @endif

    @if ($recipe->source_url || $revision->source_credit)
        <a class="source" @if ($recipe->source_url) href="{{ $recipe->source_url }}" rel="nofollow noopener" @endif>
            <strong>{{ $revision->source_credit ?: $recipe->sourceHost() }}</strong>
            @if ($recipe->source_url)
                <span class="muted">· {{ __('share.open_original') }}</span>
            @endif
        </a>
    @endif

    @if ($revision->ingredients->isNotEmpty())
        <h2>{{ __('revision.infolist.ingredients_heading') }}</h2>

        @foreach ($revision->ingredientGroups as $group)
            @php $lines = $revision->ingredients->where('group_id', $group->id); @endphp
            @continue($lines->isEmpty())

            @if ($group->title || $revision->ingredientGroups->count() > 1)
                <h3>{{ $group->title ?: __('revision.items.ungrouped') }}</h3>
            @endif

            <ul class="ingredients">
                @foreach ($lines as $line)
                    <li>{{ RecipeRevisionIngredient::formatLine(
                        RecipeRevisionIngredient::formatQuantity($line->quantity),
                        $line->unit?->code,
                        $line->ingredient?->nameFor($revision->locale, $recipe->default_locale),
                        $line->preparation_note,
                        (bool) $line->optional,
                    ) }}</li>
                @endforeach
            </ul>
        @endforeach

        @if ($ungroupedIngredients->isNotEmpty())
            <ul class="ingredients">
                @foreach ($ungroupedIngredients as $line)
                    <li>{{ RecipeRevisionIngredient::formatLine(
                        RecipeRevisionIngredient::formatQuantity($line->quantity),
                        $line->unit?->code,
                        $line->ingredient?->nameFor($revision->locale, $recipe->default_locale),
                        $line->preparation_note,
                        (bool) $line->optional,
                    ) }}</li>
                @endforeach
            </ul>
        @endif
    @endif

    @if ($revision->instructionSteps->isNotEmpty())
        <h2>{{ __('revision.infolist.instructions_heading') }}</h2>

        @foreach ($revision->instructionSections as $section)
            @php $steps = $revision->instructionSteps->where('section_id', $section->id); @endphp
            @continue($steps->isEmpty())

            @if ($section->title || $revision->instructionSections->count() > 1)
                <h3>{{ $section->title ?: __('revision.items.steps') }}</h3>
            @endif

            <ol class="steps">
                @foreach ($steps as $step)
                    <li>{{ $step->instruction_text }}</li>
                @endforeach
            </ol>
        @endforeach

        @if ($unsectionedSteps->isNotEmpty())
            <ol class="steps">
                @foreach ($unsectionedSteps as $step)
                    <li>{{ $step->instruction_text }}</li>
                @endforeach
            </ol>
        @endif
    @endif

    @if ($revision->notes)
        <h2>{{ __('revision.fields.notes') }}</h2>
        <p class="notes">{{ $revision->notes }}</p>
    @endif

    @if ($gallery->isNotEmpty())
        <h2>{{ __('revision.infolist.photos_heading') }}</h2>
        <div class="gallery">
            @foreach ($gallery as $image)
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($image->path) }}"
                    alt="{{ $image->alt_text ?? '' }}">
            @endforeach
        </div>
    @endif

    <footer>
        <a class="brand" href="{{ route('landing') }}">
            <x-whisk :size="20" /> {{ __('share.footer') }}
        </a>
        <a class="muted" href="{{ route('filament.app.auth.register') }}">{{ __('share.footer_register') }}</a>
    </footer>
</x-public-page>
