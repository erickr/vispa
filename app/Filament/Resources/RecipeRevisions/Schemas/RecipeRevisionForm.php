<?php

namespace App\Filament\Resources\RecipeRevisions\Schemas;

use App\Filament\Resources\Ingredients\Schemas\IngredientForm;
use App\Models\Ingredient;
use App\Models\RecipeRevision;
use App\Models\RecipeRevisionIngredient;
use App\Models\Unit;
use App\Support\SupportedLocales;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmptyState;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class RecipeRevisionForm
{
    /** @var array<int, string>|null Ingredient names, primed once per request for the line labels. */
    protected static ?array $ingredientNames = null;

    /** @var array<int, string>|null Unit codes, same. */
    protected static ?array $unitCodes = null;

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            // Where the recipe came from, in front of everything else — for a saved link this is
            // often the whole recipe.
            Callout::make(fn (?RecipeRevision $record): string => $record?->recipe?->sourceHost() ?? __('revision.source.saved_link'))
                ->description(fn (?RecipeRevision $record): string => $record?->source_credit
                    ?: __('revision.source.description'))
                ->icon(Heroicon::OutlinedLink)
                ->color('info')
                ->visible(fn (?RecipeRevision $record): bool => filled($record?->recipe?->source_url))
                ->footerActions([
                    Action::make('openSource')
                        ->label(__('revision.source.open'))
                        ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                        ->link()
                        ->url(fn (?RecipeRevision $record): ?string => $record?->recipe?->source_url)
                        ->openUrlInNewTab(),
                ])
                ->columnSpanFull(),

            Tabs::make()->tabs([

                Tab::make('details')->label(__('revision.tabs.details'))->schema([
                    Select::make('locale')
                        ->label(__('revision.fields.locale'))
                        ->options(fn (?RecipeRevision $record): array => SupportedLocales::optionsIncluding($record?->locale))
                        ->default(fn (): string => SupportedLocales::preferred())
                        ->required()
                        ->selectablePlaceholder(false)
                        ->native(false),

                    TextInput::make('version_number')
                        ->label(__('revision.fields.version_number'))
                        ->required()
                        ->numeric()
                        ->minValue(1),

                    Select::make('status')
                        ->label(__('revision.fields.status'))
                        ->required()
                        ->options([
                            'draft' => __('revision.status.draft'),
                            'published' => __('revision.status.published'),
                            'archived' => __('revision.status.archived'),
                        ])
                        ->native(false),

                    TextInput::make('title')
                        ->label(__('revision.fields.title'))
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Textarea::make('description')->label(__('revision.fields.description'))->rows(3)->columnSpanFull(),
                    Textarea::make('notes')->label(__('revision.fields.notes'))->rows(2)->columnSpanFull(),

                    // The link itself lives on the recipe (stable across locales); this is the
                    // prose credit for this locale.
                    TextInput::make('source_credit')
                        ->label(__('revision.fields.source_credit'))
                        ->placeholder(__('revision.fields.source_credit_placeholder'))
                        ->maxLength(255)
                        ->helperText(__('revision.fields.source_credit_helper'))
                        ->columnSpanFull(),

                    TextInput::make('servings')->label(__('revision.fields.servings'))->numeric()->nullable(),
                    TextInput::make('prep_time_minutes')->label(__('revision.fields.prep_time'))->numeric()->nullable(),
                    TextInput::make('cook_time_minutes')->label(__('revision.fields.cook_time'))->numeric()->nullable(),
                ])->columns(3),

                Tab::make('ingredients')->label(__('revision.tabs.ingredients'))->schema([
                    // Ingredients are optional. A link-only recipe says so instead of showing an
                    // empty form and implying something is missing.
                    EmptyState::make(__('revision.empty.ingredients.heading'))
                        ->description(fn (?RecipeRevision $record): string => filled($record?->recipe?->source_url)
                            ? __('revision.empty.ingredients.with_source')
                            : __('revision.empty.ingredients.without_source'))
                        ->icon(Heroicon::OutlinedListBullet)
                        ->visible(fn (Get $get): bool => blank($get('ingredientGroups'))),

                    Repeater::make('ingredientGroups')
                        ->relationship()
                        ->hiddenLabel()
                        ->orderColumn('sort_order')
                        ->schema([
                            TextInput::make('title')
                                ->label(__('revision.fields.group_title'))
                                ->placeholder(__('revision.fields.group_title_placeholder'))
                                ->maxLength(255),

                            Repeater::make('ingredients')
                                ->relationship()
                                ->hiddenLabel()
                                ->orderColumn('sort_order')
                                // Reads left to right the way the line reads: 150 | g | butter.
                                ->schema([
                                    TextInput::make('quantity')
                                        ->label(__('revision.fields.quantity'))
                                        ->placeholder(__('revision.fields.quantity_placeholder'))
                                        ->dehydrateStateUsing(fn ($state) => self::parseQuantity($state)),

                                    Select::make('unit_id')
                                        ->label(__('revision.fields.unit'))
                                        ->relationship('unit', 'code')
                                        ->searchable()
                                        ->preload()
                                        ->nullable(),

                                    Select::make('ingredient_id')
                                        ->label(__('revision.fields.ingredient'))
                                        // Shared ingredients plus the editor's household's. The saved pick stays
                                        // resolvable even if it's no longer visible — the saved one, not the
                                        // submitted one, or any id typed in would pass as a valid option.
                                        ->relationship(
                                            name: 'ingredient',
                                            titleAttribute: 'canonical_name',
                                            modifyQueryUsing: fn ($query, ?Model $record) => $query->visibleTo(
                                                Auth::user(),
                                                $record instanceof RecipeRevisionIngredient ? $record->ingredient_id : null,
                                            ),
                                        )
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                        // Add a missing ingredient without leaving the editor; it's private to the author.
                                        ->createOptionForm([
                                            IngredientForm::uniqueName(
                                                TextInput::make('canonical_name')
                                                    ->label(__('revision.fields.ingredient_name'))
                                                    ->required()
                                                    ->maxLength(255)
                                            ),
                                        ])
                                        ->columnSpan(3),

                                    Toggle::make('optional')->label(__('revision.fields.optional'))->inline(false),

                                    TextInput::make('preparation_note')
                                        ->label(__('revision.fields.preparation_note'))
                                        ->placeholder(__('revision.fields.preparation_note_placeholder'))
                                        ->maxLength(255)
                                        ->columnSpanFull(),
                                ])
                                ->columns(6)
                                ->mutateRelationshipDataBeforeCreateUsing(
                                    fn (array $data, Component $livewire): array => self::withRevisionId($data, $livewire)
                                )
                                ->mutateRelationshipDataBeforeSaveUsing(
                                    fn (array $data, Component $livewire): array => self::withRevisionId($data, $livewire)
                                )
                                // Collapsed, each line reads as prose — "150 g butter, softened".
                                // Expanding one gives back the amount / unit / ingredient fields.
                                ->collapsible()
                                ->collapsed()
                                ->itemLabel(fn (array $state): string => self::ingredientLine($state))
                                ->addActionLabel(__('revision.actions.add_ingredient'))
                                ->defaultItems(0),
                        ])
                        ->itemLabel(fn (array $state): ?string => $state['title'] ?? __('revision.items.ungrouped'))
                        ->addActionLabel(__('revision.actions.add_ingredient_group'))
                        ->defaultItems(0)
                        ->collapsible(),
                ]),

                Tab::make('instructions')->label(__('revision.tabs.instructions'))->schema([
                    EmptyState::make(__('revision.empty.instructions.heading'))
                        ->description(fn (?RecipeRevision $record): string => filled($record?->recipe?->source_url)
                            ? __('revision.empty.instructions.with_source')
                            : __('revision.empty.instructions.without_source'))
                        ->icon(Heroicon::OutlinedSparkles)
                        ->visible(fn (Get $get): bool => blank($get('instructionSections'))),

                    Repeater::make('instructionSections')
                        ->relationship()
                        ->hiddenLabel()
                        ->orderColumn('sort_order')
                        ->schema([
                            TextInput::make('title')
                                ->label(__('revision.fields.section_title'))
                                ->placeholder(__('revision.fields.section_title_placeholder'))
                                ->maxLength(255),

                            Repeater::make('steps')
                                ->relationship()
                                ->hiddenLabel()
                                ->orderColumn('sort_order')
                                ->schema([
                                    Textarea::make('instruction_text')
                                        ->label(__('revision.fields.step'))
                                        ->required()
                                        ->rows(2)
                                        ->columnSpan(3),

                                    TextInput::make('timer_seconds')
                                        ->label(__('revision.fields.timer_seconds'))
                                        ->numeric()
                                        ->minValue(0)
                                        ->nullable(),
                                ])
                                ->columns(4)
                                ->mutateRelationshipDataBeforeCreateUsing(
                                    fn (array $data, Component $livewire): array => self::withRevisionId($data, $livewire)
                                )
                                ->mutateRelationshipDataBeforeSaveUsing(
                                    fn (array $data, Component $livewire): array => self::withRevisionId($data, $livewire)
                                )
                                ->addActionLabel(__('revision.actions.add_step'))
                                ->defaultItems(0),
                        ])
                        ->itemLabel(fn (array $state): ?string => $state['title'] ?? __('revision.items.steps'))
                        ->addActionLabel(__('revision.actions.add_instruction_section'))
                        ->defaultItems(0)
                        ->collapsible(),
                ]),

                Tab::make('photos')->label(__('revision.tabs.photos'))->schema([
                    EmptyState::make(__('revision.empty.photos.heading'))
                        ->description(__('revision.empty.photos.description'))
                        ->icon(Heroicon::OutlinedPhoto)
                        ->visible(fn (Get $get): bool => blank($get('images'))),

                    Repeater::make('images')
                        ->relationship()
                        ->hiddenLabel()
                        ->orderColumn('sort_order')
                        ->schema([
                            FileUpload::make('path')
                                ->hiddenLabel()
                                ->image()
                                ->imageEditor()
                                ->disk('public')
                                ->directory('recipe-images')
                                ->visibility('public')
                                ->maxSize(8192)
                                ->required()
                                ->columnSpan(2),

                            TextInput::make('alt_text')
                                ->label(__('revision.fields.alt_text'))
                                ->placeholder(__('revision.fields.alt_text_placeholder'))
                                ->helperText(__('revision.fields.alt_text_helper'))
                                ->maxLength(255),

                            Toggle::make('is_cover')
                                ->label(__('revision.fields.is_cover'))
                                ->helperText(__('revision.fields.is_cover_helper'))
                                ->inline(false),
                        ])
                        ->columns(4)
                        ->addActionLabel(__('revision.actions.add_photo'))
                        ->defaultItems(0)
                        ->reorderableWithDragAndDrop(),
                ]),

            ])->columnSpanFull(),
        ]);
    }

    /**
     * Ingredients and steps carry their own recipe_revision_id (NOT NULL) in addition to the
     * nullable group_id / section_id the nested relationship sets. Backfill it from the page record.
     */
    protected static function withRevisionId(array $data, Component $livewire): array
    {
        $data['recipe_revision_id'] = $livewire->getRecord()->getKey();

        return $data;
    }

    /**
     * A collapsed ingredient row, written the way it reads on paper: "150 g butter, softened".
     */
    public static function ingredientLine(array $state): string
    {
        $name = self::ingredientName($state['ingredient_id'] ?? null);

        if ($name === null) {
            return __('revision.items.new_ingredient');
        }

        return RecipeRevisionIngredient::formatLine(
            RecipeRevisionIngredient::formatQuantity($state['quantity'] ?? null),
            self::unitCode($state['unit_id'] ?? null),
            $name,
            $state['preparation_note'] ?? null,
            (bool) ($state['optional'] ?? false),
        );
    }

    /**
     * Catalog lookups for the labels. The bulk map is primed once per request, but a miss falls
     * back to a single-row query — an ingredient created from inside the editor has to label
     * itself correctly without waiting for a page reload.
     */
    protected static function ingredientName(mixed $id): ?string
    {
        if (blank($id)) {
            return null;
        }

        self::$ingredientNames ??= Ingredient::query()->pluck('canonical_name', 'id')->all();

        return self::$ingredientNames[$id] ??= Ingredient::query()->whereKey($id)->value('canonical_name');
    }

    protected static function unitCode(mixed $id): ?string
    {
        if (blank($id)) {
            return null;
        }

        self::$unitCodes ??= Unit::query()->pluck('code', 'id')->all();

        return self::$unitCodes[$id] ??= Unit::query()->whereKey($id)->value('code');
    }

    /**
     * Accept decimals ("1.5") and simple fractions ("1/2", "1 1/2") for quantity columns,
     * which are stored as decimal(10,3).
     */
    public static function parseQuantity(mixed $state): ?float
    {
        if ($state === null) {
            return null;
        }

        $value = trim((string) $state);

        if ($value === '') {
            return null;
        }

        // "1 1/2" -> whole + fraction
        if (preg_match('#^(\d+)\s+(\d+)/(\d+)$#', $value, $m)) {
            return (int) $m[1] + ((int) $m[3] !== 0 ? (int) $m[2] / (int) $m[3] : 0);
        }

        // "1/2"
        if (preg_match('#^(\d+)/(\d+)$#', $value, $m)) {
            return (int) $m[2] !== 0 ? (int) $m[1] / (int) $m[2] : null;
        }

        return is_numeric($value) ? (float) $value : null;
    }
}
