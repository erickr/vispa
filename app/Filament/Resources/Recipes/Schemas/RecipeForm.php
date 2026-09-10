<?php

namespace App\Filament\Resources\Recipes\Schemas;

use App\Filament\Resources\RecipeRevisions\RecipeRevisionResource;
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
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class RecipeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make()->tabs([

                Tab::make('Recipe')->schema([
                    TextInput::make('default_locale')
                        ->required()
                        ->maxLength(10)
                        ->default('en'),

                    Select::make('visibility')
                        ->required()
                        ->options([
                            'private' => 'Private',
                            'unlisted' => 'Unlisted',
                            'public' => 'Public',
                        ])
                        ->default('private')
                        ->native(false),

                    TextInput::make('source_url')
                        ->label('Source link')
                        ->url()
                        ->maxLength(500)
                        ->placeholder('https://www.ica.se/recept/...')
                        ->helperText('Where it came from. A link on its own is a complete recipe — ingredients and instructions are optional.')
                        ->columnSpanFull(),

                    Select::make('forked_from_recipe_id')
                        ->label('Forked from recipe')
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
                        ->label('Forked from revision')
                        ->relationship('forkedFromRevision', 'uuid')
                        ->searchable()
                        ->preload()
                        ->nullable(),
                ])->columns(2),

                Tab::make('Slugs')->schema([
                    Repeater::make('localeSlugs')
                        ->relationship()
                        ->hiddenLabel()
                        ->schema([
                            TextInput::make('locale')
                                ->required()
                                ->maxLength(10)
                                ->placeholder('en, sv, ...'),
                            TextInput::make('slug')
                                ->required()
                                ->maxLength(255),
                            Toggle::make('is_primary')
                                ->default(true),
                        ])
                        ->columns(3)
                        ->itemLabel(fn (array $state): ?string => isset($state['locale'], $state['slug'])
                            ? "{$state['locale']} / {$state['slug']}"
                            : null)
                        ->addActionLabel('Add slug')
                        ->defaultItems(0),
                ]),

                Tab::make('Revisions')->schema([
                    Repeater::make('revisions')
                        ->relationship()
                        ->hiddenLabel()
                        ->schema([
                            Section::make()->schema([
                                TextInput::make('locale')
                                    ->required()
                                    ->maxLength(10)
                                    ->placeholder('en, sv, ...'),

                                TextInput::make('version_number')
                                    ->required()
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(1),

                                Select::make('status')
                                    ->required()
                                    ->options([
                                        'draft' => 'Draft',
                                        'published' => 'Published',
                                        'archived' => 'Archived',
                                    ])
                                    ->default('draft')
                                    ->native(false),

                                TextInput::make('title')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpanFull(),

                                Textarea::make('description')
                                    ->rows(3)
                                    ->columnSpanFull(),

                                Textarea::make('notes')
                                    ->rows(2)
                                    ->columnSpanFull(),

                                TextInput::make('servings')->numeric()->nullable(),
                                TextInput::make('prep_time_minutes')
                                    ->label('Prep (min)')
                                    ->numeric()
                                    ->nullable(),
                                TextInput::make('cook_time_minutes')
                                    ->label('Cook (min)')
                                    ->numeric()
                                    ->nullable(),
                            ])->columns(3),
                        ])
                        ->defaultItems(0)
                        ->itemLabel(fn (array $state): ?string => isset($state['version_number'])
                            ? trim(($state['locale'] ?? '').' v'.$state['version_number'].' — '.($state['status'] ?? 'draft'))
                            : null)
                        ->addActionLabel('Add revision')
                        ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                            $data['created_by_user_id'] = Auth::id();

                            return $data;
                        })
                        ->extraItemActions([
                            Action::make('editContent')
                                ->label('Edit content')
                                ->icon(Heroicon::OutlinedPencilSquare)
                                ->action(function (array $arguments, Repeater $component, Component $livewire): void {
                                    $record = $component->getCachedExistingRecords()[$arguments['item']] ?? null;

                                    if (! $record) {
                                        Notification::make()
                                            ->title('Save the recipe first')
                                            ->body('Save this new revision before editing its ingredients and instructions.')
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
}
