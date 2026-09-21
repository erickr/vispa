<?php

namespace App\Filament\Resources\Ingredients\Schemas;

use App\Models\Ingredient;
use App\Models\User;
use App\Support\SupportedLocales;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Unique;

class IngredientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                self::uniqueName(
                    TextInput::make('canonical_name')
                        ->label(__('ingredient.fields.canonical_name'))
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    ignoreRecord: true,
                ),
            ]),

            Section::make(__('ingredient.translations.heading'))
                ->description(__('ingredient.translations.description'))
                ->schema([
                    Repeater::make('translations')
                        ->relationship()
                        ->hiddenLabel()
                        ->schema([
                            Select::make('locale')
                                ->label(__('ingredient.fields.locale'))
                                ->options(fn (?Model $record): array => SupportedLocales::optionsIncluding($record?->locale))
                                ->default(fn (): string => SupportedLocales::preferred())
                                ->required()
                                ->selectablePlaceholder(false)
                                ->native(false),
                            TextInput::make('name')
                                ->label(__('ingredient.fields.name'))
                                ->required()
                                ->maxLength(255),
                        ])
                        ->columns(2)
                        ->defaultItems(0)
                        ->itemLabel(fn (array $state): ?string => $state['locale'] ?? null)
                        ->addActionLabel(__('ingredient.translations.add')),
                ]),
        ]);
    }

    /**
     * A name must be unique among what the ingredient's owner can see: the shared catalog plus
     * their own. Other users' private ingredients neither block the name nor leak through it.
     * Pass `$ignoreRecord` only where the form's record is the ingredient itself.
     */
    public static function uniqueName(TextInput $input, bool $ignoreRecord = false): TextInput
    {
        return $input
            ->unique(
                table: 'ingredients',
                column: 'canonical_name',
                ignoreRecord: $ignoreRecord,
                modifyRuleUsing: function (Unique $rule, ?Model $record): Unique {
                    $user = Auth::user();
                    $ownerId = $record instanceof Ingredient
                        ? $record->owner_user_id
                        : ($user instanceof User && ! $user->isCatalogAdmin() ? $user->getKey() : null);

                    return $rule->where(fn (QueryBuilder $query) => $query
                        ->whereNull('owner_user_id')
                        ->when($ownerId, fn (QueryBuilder $query) => $query->orWhere('owner_user_id', $ownerId)));
                },
            )
            ->validationMessages(['unique' => __('ingredient.validation.name_taken')]);
    }
}
