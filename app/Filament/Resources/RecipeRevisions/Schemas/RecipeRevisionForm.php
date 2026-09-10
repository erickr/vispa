<?php

namespace App\Filament\Resources\RecipeRevisions\Schemas;

use App\Models\Ingredient;
use App\Models\RecipeRevision;
use App\Models\RecipeRevisionIngredient;
use App\Models\Unit;
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
            Callout::make(fn (?RecipeRevision $record): string => $record?->recipe?->sourceHost() ?? 'Saved link')
                ->description(fn (?RecipeRevision $record): string => $record?->source_credit
                    ?: 'The original lives on the web. Copy bits in whenever you feel like it.')
                ->icon(Heroicon::OutlinedLink)
                ->color('info')
                ->visible(fn (?RecipeRevision $record): bool => filled($record?->recipe?->source_url))
                ->footerActions([
                    Action::make('openSource')
                        ->label('Open the original')
                        ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                        ->link()
                        ->url(fn (?RecipeRevision $record): ?string => $record?->recipe?->source_url)
                        ->openUrlInNewTab(),
                ])
                ->columnSpanFull(),

            Tabs::make()->tabs([

                Tab::make('Details')->schema([
                    TextInput::make('locale')
                        ->required()
                        ->maxLength(10)
                        ->placeholder('en, sv, ...'),

                    TextInput::make('version_number')
                        ->required()
                        ->numeric()
                        ->minValue(1),

                    Select::make('status')
                        ->required()
                        ->options([
                            'draft' => 'Draft',
                            'published' => 'Published',
                            'archived' => 'Archived',
                        ])
                        ->native(false),

                    TextInput::make('title')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Textarea::make('description')->rows(3)->columnSpanFull(),
                    Textarea::make('notes')->rows(2)->columnSpanFull(),

                    // The link itself lives on the recipe (stable across locales); this is the
                    // prose credit for this locale.
                    TextInput::make('source_credit')
                        ->label('Who to thank')
                        ->placeholder("Mormor Ingrid's notebook, a friend, a cookbook…")
                        ->maxLength(255)
                        ->helperText('The link itself is on the recipe, under Settings.')
                        ->columnSpanFull(),

                    TextInput::make('servings')->numeric()->nullable(),
                    TextInput::make('prep_time_minutes')->label('Prep (min)')->numeric()->nullable(),
                    TextInput::make('cook_time_minutes')->label('Cook (min)')->numeric()->nullable(),
                ])->columns(3),

                Tab::make('Ingredients')->schema([
                    // Ingredients are optional. A link-only recipe says so instead of showing an
                    // empty form and implying something is missing.
                    EmptyState::make('Nothing listed yet — and that is fine')
                        ->description(fn (?RecipeRevision $record): string => filled($record?->recipe?->source_url)
                            ? 'The ingredients are in the original. Add them here only if you want your own version.'
                            : 'Add ingredients whenever you like. A recipe with just a title is still a recipe.')
                        ->icon(Heroicon::OutlinedListBullet)
                        ->visible(fn (Get $get): bool => blank($get('ingredientGroups'))),

                    Repeater::make('ingredientGroups')
                        ->relationship()
                        ->hiddenLabel()
                        ->orderColumn('sort_order')
                        ->schema([
                            TextInput::make('title')
                                ->label('Group title')
                                ->placeholder('e.g. For the dough (leave blank for ungrouped)')
                                ->maxLength(255),

                            Repeater::make('ingredients')
                                ->relationship()
                                ->hiddenLabel()
                                ->orderColumn('sort_order')
                                // Reads left to right the way the line reads: 150 | g | butter.
                                ->schema([
                                    TextInput::make('quantity')
                                        ->label('Amount')
                                        ->placeholder('1, 1.5, 1/2 ...')
                                        ->dehydrateStateUsing(fn ($state) => self::parseQuantity($state)),

                                    Select::make('unit_id')
                                        ->label('Unit')
                                        ->relationship('unit', 'code')
                                        ->searchable()
                                        ->preload()
                                        ->nullable(),

                                    Select::make('ingredient_id')
                                        ->label('Ingredient')
                                        ->relationship('ingredient', 'canonical_name')
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                        // Add a missing ingredient to the global catalog without leaving the editor.
                                        ->createOptionForm([
                                            TextInput::make('canonical_name')
                                                ->label('Ingredient name')
                                                ->required()
                                                ->maxLength(255)
                                                ->unique('ingredients', 'canonical_name'),
                                        ])
                                        ->columnSpan(3),

                                    Toggle::make('optional')->inline(false),

                                    TextInput::make('preparation_note')
                                        ->label('Prep note')
                                        ->placeholder('finely chopped')
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
                                ->addActionLabel('Add ingredient')
                                ->defaultItems(0),
                        ])
                        ->itemLabel(fn (array $state): ?string => $state['title'] ?? 'Ungrouped')
                        ->addActionLabel('Add ingredient group')
                        ->defaultItems(0)
                        ->collapsible(),
                ]),

                Tab::make('Instructions')->schema([
                    EmptyState::make('No steps written down')
                        ->description(fn (?RecipeRevision $record): string => filled($record?->recipe?->source_url)
                            ? 'The method is in the original — follow the link when you cook it.'
                            : 'Write the method out when you have a moment. It is not required.')
                        ->icon(Heroicon::OutlinedSparkles)
                        ->visible(fn (Get $get): bool => blank($get('instructionSections'))),

                    Repeater::make('instructionSections')
                        ->relationship()
                        ->hiddenLabel()
                        ->orderColumn('sort_order')
                        ->schema([
                            TextInput::make('title')
                                ->label('Section title')
                                ->placeholder('e.g. Preparation (leave blank for a single section)')
                                ->maxLength(255),

                            Repeater::make('steps')
                                ->relationship()
                                ->hiddenLabel()
                                ->orderColumn('sort_order')
                                ->schema([
                                    Textarea::make('instruction_text')
                                        ->label('Step')
                                        ->required()
                                        ->rows(2)
                                        ->columnSpan(3),

                                    TextInput::make('timer_seconds')
                                        ->label('Timer (s)')
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
                                ->addActionLabel('Add step')
                                ->defaultItems(0),
                        ])
                        ->itemLabel(fn (array $state): ?string => $state['title'] ?? 'Steps')
                        ->addActionLabel('Add instruction section')
                        ->defaultItems(0)
                        ->collapsible(),
                ]),

                Tab::make('Photos')->schema([
                    EmptyState::make('No photos yet')
                        ->description('Drop one in when you next make it. Recipes work fine without.')
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
                                ->label('Describe the photo')
                                ->placeholder('Bullar cooling on a rack')
                                ->helperText('Read aloud to anyone using a screen reader.')
                                ->maxLength(255),

                            Toggle::make('is_cover')
                                ->label('Use as cover')
                                ->helperText('Only one photo leads.')
                                ->inline(false),
                        ])
                        ->columns(4)
                        ->addActionLabel('Add a photo')
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
            return 'New ingredient';
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
