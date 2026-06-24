<?php

namespace App\Filament\Resources\RecipeRevisions\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Livewire\Component;

class RecipeRevisionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
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

                    TextInput::make('servings')->numeric()->nullable(),
                    TextInput::make('prep_time_minutes')->label('Prep (min)')->numeric()->nullable(),
                    TextInput::make('cook_time_minutes')->label('Cook (min)')->numeric()->nullable(),
                ])->columns(3),

                Tab::make('Ingredients')->schema([
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
                                ->schema([
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
                                        ]),

                                    TextInput::make('quantity')
                                        ->placeholder('1, 1.5, 1/2 ...')
                                        ->dehydrateStateUsing(fn ($state) => self::parseQuantity($state)),

                                    Select::make('unit_id')
                                        ->label('Unit')
                                        ->relationship('unit', 'code')
                                        ->searchable()
                                        ->preload()
                                        ->nullable(),

                                    Toggle::make('optional')->inline(false),

                                    TextInput::make('preparation_note')
                                        ->label('Prep note')
                                        ->placeholder('finely chopped')
                                        ->maxLength(255)
                                        ->columnSpanFull(),
                                ])
                                ->columns(4)
                                ->mutateRelationshipDataBeforeCreateUsing(
                                    fn (array $data, Component $livewire): array => self::withRevisionId($data, $livewire)
                                )
                                ->mutateRelationshipDataBeforeSaveUsing(
                                    fn (array $data, Component $livewire): array => self::withRevisionId($data, $livewire)
                                )
                                ->addActionLabel('Add ingredient')
                                ->defaultItems(0),
                        ])
                        ->itemLabel(fn (array $state): ?string => $state['title'] ?? 'Ungrouped')
                        ->addActionLabel('Add ingredient group')
                        ->defaultItems(0)
                        ->collapsible(),
                ]),

                Tab::make('Instructions')->schema([
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
