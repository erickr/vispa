<?php

namespace App\Filament\Resources\Recipes\Schemas;

use App\Filament\Resources\RecipeRevisions\RecipeRevisionResource;
use App\Models\Recipe;
use App\Support\PageTitleFetcher;
use App\Support\SupportedLocales;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class RecipeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            // Tab names stay in English: they are the tab's key, and the URL remembers
            // which one was open. The label is what the reader sees.
            Tabs::make()->tabs([

                Tab::make('recipe')->label(__('recipe.tabs.recipe'))->schema([
                    Select::make('default_locale')
                        ->label(__('recipe.fields.default_locale'))
                        ->options(fn (?Recipe $record): array => SupportedLocales::optionsIncluding($record?->default_locale))
                        ->default(fn (): string => SupportedLocales::preferred())
                        ->required()
                        ->selectablePlaceholder(false)
                        ->native(false),

                    // Only a question for someone in more than one family; everyone else's recipes
                    // go to their one family (see Recipe::booted()).
                    Select::make('team_id')
                        ->label(__('family.fields.family'))
                        ->helperText(__('family.fields.family_helper'))
                        ->options(fn (?Recipe $record): array => self::familyOptions($record))
                        ->default(fn (): ?int => Auth::user()?->current_team_id)
                        ->in(fn (?Recipe $record): array => array_keys(self::familyOptions($record)))
                        ->visible(fn (?Recipe $record): bool => count(self::familyOptions($record)) > 1)
                        ->selectablePlaceholder(false)
                        ->native(false),

                    Select::make('visibility')
                        ->label(__('recipe.fields.visibility'))
                        ->required()
                        ->options([
                            'private' => __('recipe.visibility.private'),
                            'unlisted' => __('recipe.visibility.unlisted'),
                            'public' => __('recipe.visibility.public'),
                        ])
                        ->default('private')
                        ->native(false),

                    TextInput::make('source_url')
                        ->label(__('recipe.fields.source_url'))
                        ->url()
                        ->maxLength(500)
                        ->placeholder(__('recipe.fields.source_url_placeholder'))
                        ->helperText(__('recipe.fields.source_url_helper'))
                        ->suffixAction(
                            // Reads the page's heading into the title below, so a saved link has a name.
                            Action::make('fetchTitle')
                                ->label(__('recipe.actions.fetch_title'))
                                ->button()
                                ->visible(fn (string $operation): bool => $operation === 'create')
                                ->action(function (Get $get, Set $set): void {
                                    $url = trim((string) $get('source_url'));

                                    if (! filter_var($url, FILTER_VALIDATE_URL)) {
                                        Notification::make()->title(__('recipe.notifications.fetch_title.no_url'))->warning()->send();

                                        return;
                                    }

                                    $title = app(PageTitleFetcher::class)->fetch($url);

                                    if ($title === null) {
                                        Notification::make()
                                            ->title(__('recipe.notifications.fetch_title.not_found'))
                                            ->body(__('recipe.notifications.fetch_title.not_found_body'))
                                            ->warning()
                                            ->send();

                                        return;
                                    }

                                    $set('title', $title);
                                })
                        )
                        ->columnSpanFull(),

                    // Not a recipe column: CreateRecipe hands it to the seeded first revision.
                    TextInput::make('title')
                        ->label(__('recipe.fields.title'))
                        ->placeholder(__('recipe.untitled'))
                        ->helperText(__('recipe.fields.title_helper'))
                        ->maxLength(255)
                        ->visibleOn('create')
                        ->columnSpanFull(),

                    Select::make('forked_from_recipe_id')
                        ->label(__('recipe.fields.forked_from_recipe'))
                        ->relationship(
                            name: 'forkedFromRecipe',
                            titleAttribute: 'uuid',
                            modifyQueryUsing: fn ($query, $record) => $record
                                ? $query->where('id', '!=', $record->id)
                                : $query,
                        )
                        ->searchable()
                        ->preload()
                        ->nullable(),

                    Select::make('forked_from_revision_id')
                        ->label(__('recipe.fields.forked_from_revision'))
                        ->relationship('forkedFromRevision', 'uuid')
                        ->searchable()
                        ->preload()
                        ->nullable(),
                ])->columns(2),

                Tab::make('slugs')->label(__('recipe.tabs.slugs'))->schema([
                    Repeater::make('localeSlugs')
                        ->relationship()
                        ->hiddenLabel()
                        ->schema([
                            Select::make('locale')
                                ->label(__('recipe.fields.locale'))
                                ->options(fn (?Model $record): array => SupportedLocales::optionsIncluding($record?->locale))
                                ->default(fn (): string => SupportedLocales::preferred())
                                ->required()
                                ->selectablePlaceholder(false)
                                ->native(false),
                            TextInput::make('slug')
                                ->label(__('recipe.fields.slug'))
                                ->required()
                                ->maxLength(255),
                            Toggle::make('is_primary')
                                ->label(__('recipe.fields.is_primary'))
                                ->default(true),
                        ])
                        ->columns(3)
                        ->itemLabel(fn (array $state): ?string => isset($state['locale'], $state['slug'])
                            ? "{$state['locale']} / {$state['slug']}"
                            : null)
                        ->addActionLabel(__('recipe.actions.add_slug'))
                        ->defaultItems(0),
                ]),

                Tab::make('revisions')->label(__('recipe.tabs.revisions'))->schema([
                    Repeater::make('revisions')
                        ->relationship()
                        ->hiddenLabel()
                        ->schema([
                            Section::make()->schema([
                                Select::make('locale')
                                    ->label(__('revision.fields.locale'))
                                    ->options(fn (?Model $record): array => SupportedLocales::optionsIncluding($record?->locale))
                                    ->default(fn (): string => SupportedLocales::preferred())
                                    ->required()
                                    ->selectablePlaceholder(false)
                                    ->native(false),

                                TextInput::make('version_number')
                                    ->label(__('revision.fields.version_number'))
                                    ->required()
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(1),

                                Select::make('status')
                                    ->label(__('revision.fields.status'))
                                    ->required()
                                    ->options([
                                        'draft' => __('revision.status.draft'),
                                        'published' => __('revision.status.published'),
                                        'archived' => __('revision.status.archived'),
                                    ])
                                    ->default('draft')
                                    ->native(false),

                                TextInput::make('title')
                                    ->label(__('revision.fields.title'))
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpanFull(),

                                Textarea::make('description')
                                    ->label(__('revision.fields.description'))
                                    ->rows(3)
                                    ->columnSpanFull(),

                                Textarea::make('notes')
                                    ->label(__('revision.fields.notes'))
                                    ->rows(2)
                                    ->columnSpanFull(),

                                TextInput::make('servings')
                                    ->label(__('revision.fields.servings'))
                                    ->numeric()
                                    ->nullable(),
                                TextInput::make('prep_time_minutes')
                                    ->label(__('revision.fields.prep_time'))
                                    ->numeric()
                                    ->nullable(),
                                TextInput::make('cook_time_minutes')
                                    ->label(__('revision.fields.cook_time'))
                                    ->numeric()
                                    ->nullable(),
                            ])->columns(3),
                        ])
                        ->defaultItems(0)
                        ->itemLabel(fn (array $state): ?string => isset($state['version_number'])
                            ? trim(($state['locale'] ?? '').' v'.$state['version_number'].' — '.__('revision.status.'.($state['status'] ?? 'draft')))
                            : null)
                        ->addActionLabel(__('recipe.actions.add_revision'))
                        ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                            $data['created_by_user_id'] = Auth::id();

                            return $data;
                        })
                        ->extraItemActions([
                            Action::make('editContent')
                                ->label(__('recipe.actions.edit_content'))
                                ->icon(Heroicon::OutlinedPencilSquare)
                                ->action(function (array $arguments, Repeater $component, Component $livewire): void {
                                    $record = $component->getCachedExistingRecords()[$arguments['item']] ?? null;

                                    if (! $record) {
                                        Notification::make()
                                            ->title(__('recipe.notifications.save_first.title'))
                                            ->body(__('recipe.notifications.save_first.body'))
                                            ->warning()
                                            ->send();

                                        return;
                                    }

                                    $livewire->redirect(RecipeRevisionResource::getUrl('edit', ['record' => $record]));
                                }),
                        ]),
                ]),
            ])->columnSpanFull(),
        ]);
    }

    /**
     * The user's families, plus the one the recipe is already in should they have left it.
     *
     * @return array<int, string>
     */
    private static function familyOptions(?Recipe $record): array
    {
        $options = Auth::user()?->allTeams()->pluck('name', 'id')->all() ?? [];

        if ($record?->team && ! isset($options[$record->team_id])) {
            $options[$record->team_id] = $record->team->name;
        }

        return $options;
    }
}
