<?php

namespace App\Filament\Pages;

use App\Models\Household;
use App\Models\HouseholdInvitation;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Laravel\Jetstream\Contracts\CreatesTeams;
use Laravel\Jetstream\Contracts\DeletesTeams;
use Laravel\Jetstream\Contracts\InvitesTeamMembers;
use Laravel\Jetstream\Contracts\RemovesTeamMembers;
use Laravel\Jetstream\Contracts\UpdatesTeamNames;

/**
 * The user's current household: who is in it, who has been invited, and — for the owner — inviting,
 * removing and renaming. Jetstream still does the work (its actions and TeamPolicy); this page
 * is only its face inside the panel, in place of Jetstream's own Livewire views.
 */
class MyHousehold extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'household';

    protected string $view = 'filament.pages.my-household';

    public static function getNavigationLabel(): string
    {
        return __('household.menu');
    }

    public function mount(): void
    {
        abort_unless($this->household() !== null, 404);
    }

    public function getTitle(): string|Htmlable
    {
        return $this->household()->name;
    }

    public function getSubheading(): ?string
    {
        return __('household.page.subheading');
    }

    public function household(): ?Household
    {
        return $this->user()->currentTeam;
    }

    public function isOwner(): bool
    {
        return $this->user()->ownsTeam($this->household());
    }

    /**
     * The owner first, then everyone else by name.
     *
     * @return Collection<int, User>
     */
    public function members(): Collection
    {
        $household = $this->household();

        return $household->allUsers()
            ->sortBy(fn (User $user): string => ($user->is($household->owner) ? '0' : '1').mb_strtolower($user->name))
            ->values();
    }

    /**
     * @return Collection<int, TeamInvitation>
     */
    public function invitations(): Collection
    {
        return $this->household()->teamInvitations()->orderBy('email')->get();
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->inviteAction(),
            $this->switchAction(),
            ActionGroup::make([
                $this->renameAction(),
                $this->createAction(),
                $this->leaveAction(),
                $this->deleteAction(),
            ]),
        ];
    }

    public function inviteAction(): Action
    {
        return Action::make('invite')
            ->label(__('household.actions.invite'))
            ->icon(Heroicon::OutlinedUserPlus)
            ->visible(fn (): bool => $this->isOwner())
            ->modalDescription(__('household.actions.invite_description'))
            ->schema([
                TextInput::make('email')
                    ->label(__('household.fields.email'))
                    ->email()
                    ->required()
                    ->rules([
                        fn () => function (string $attribute, mixed $value, \Closure $fail): void {
                            if ($this->household()->hasUserWithEmail((string) $value)) {
                                $fail(__('household.validation.already_member'));
                            } elseif ($this->household()->teamInvitations()->where('email', $value)->exists()) {
                                $fail(__('household.validation.already_invited'));
                            }
                        },
                    ]),
            ])
            ->modalSubmitActionLabel(__('household.actions.send_invitation'))
            ->action(function (array $data): void {
                $this->jetstream(fn () => app(InvitesTeamMembers::class)
                    ->invite($this->user(), $this->household(), $data['email']));

                Notification::make()->title(__('household.notifications.invited', ['email' => $data['email']]))->success()->send();
            });
    }

    public function switchAction(): Action
    {
        return Action::make('switch')
            ->label(__('household.actions.switch'))
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->color('gray')
            ->visible(fn (): bool => $this->user()->allTeams()->count() > 1)
            ->schema([
                Select::make('household')
                    ->label(__('household.fields.household'))
                    ->options(fn (): array => $this->user()->allTeams()->pluck('name', 'id')->all())
                    ->default(fn (): ?int => $this->household()?->getKey())
                    ->selectablePlaceholder(false)
                    ->native(false)
                    ->required(),
            ])
            ->action(function (array $data): void {
                $household = $this->user()->allTeams()->firstWhere('id', (int) $data['household']);

                abort_unless($household && $this->user()->switchTeam($household), 403);

                $this->redirect(static::getUrl());
            });
    }

    public function renameAction(): Action
    {
        return Action::make('rename')
            ->label(__('household.actions.rename'))
            ->icon(Heroicon::OutlinedPencilSquare)
            ->visible(fn (): bool => $this->isOwner())
            ->fillForm(fn (): array => ['name' => $this->household()->name])
            ->schema([
                TextInput::make('name')->label(__('household.fields.name'))->required()->maxLength(255),
            ])
            ->action(function (array $data): void {
                $this->jetstream(fn () => app(UpdatesTeamNames::class)
                    ->update($this->user(), $this->household(), ['name' => $data['name']]));

                $this->redirect(static::getUrl());
            });
    }

    public function createAction(): Action
    {
        return Action::make('create')
            ->label(__('household.actions.create'))
            ->icon(Heroicon::OutlinedPlusCircle)
            ->modalDescription(__('household.actions.create_description'))
            ->schema([
                TextInput::make('name')->label(__('household.fields.name'))->required()->maxLength(255),
            ])
            ->action(function (array $data): void {
                // Jetstream's CreateTeam switches the user into the new household.
                $this->jetstream(fn () => app(CreatesTeams::class)->create($this->user(), ['name' => $data['name']]));

                $this->redirect(static::getUrl());
            });
    }

    public function leaveAction(): Action
    {
        return Action::make('leave')
            ->label(__('household.actions.leave'))
            ->icon(Heroicon::OutlinedArrowLeftStartOnRectangle)
            ->color('danger')
            ->visible(fn (): bool => ! $this->isOwner())
            ->requiresConfirmation()
            ->modalDescription(__('household.actions.leave_description'))
            ->action(function (): void {
                $household = $this->household();

                $this->jetstream(fn () => app(RemovesTeamMembers::class)->remove($this->user(), $household, $this->user()));
                $this->backToOwnHousehold();

                Notification::make()->title(__('household.notifications.left', ['household' => $household->name]))->success()->send();
                $this->redirect(static::getUrl());
            });
    }

    public function deleteAction(): Action
    {
        return Action::make('delete')
            ->label(__('household.actions.delete'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (): bool => $this->isOwner() && ! $this->household()->personal_household)
            ->requiresConfirmation()
            ->modalDescription(__('household.actions.delete_description'))
            ->action(function (): void {
                $household = $this->household();

                // Not Jetstream's ValidateTeamDeletion: it reads `personal_team`, which is
                // `personal_household` here.
                Gate::forUser($this->user())->authorize('delete', $household);
                abort_if($household->personal_household, 403);

                app(DeletesTeams::class)->delete($household);
                $this->backToOwnHousehold();

                Notification::make()->title(__('household.notifications.deleted', ['household' => $household->name]))->success()->send();
                $this->redirect(static::getUrl());
            });
    }

    public function removeMemberAction(): Action
    {
        return Action::make('removeMember')
            ->label(__('household.actions.remove'))
            ->icon(Heroicon::OutlinedUserMinus)
            ->color('danger')
            ->link()
            ->visible(fn (): bool => $this->isOwner())
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments): string => __('household.actions.remove_heading', [
                'name' => $this->household()->users()->find($arguments['user'] ?? null)?->name,
            ]))
            ->modalDescription(__('household.actions.remove_description'))
            ->action(function (array $arguments): void {
                $member = $this->household()->users()->findOrFail($arguments['user'] ?? null);

                $this->jetstream(fn () => app(RemovesTeamMembers::class)->remove($this->user(), $this->household(), $member));
            });
    }

    public function cancelInvitationAction(): Action
    {
        return Action::make('cancelInvitation')
            ->label(__('household.actions.cancel_invitation'))
            ->color('gray')
            ->link()
            ->visible(fn (): bool => $this->isOwner())
            ->action(function (array $arguments): void {
                $this->household()->teamInvitations()->whereKey($arguments['invitation'] ?? null)->delete();
            });
    }

    private function user(): User
    {
        /** @var User */
        return Auth::user();
    }

    /** Leaving or deleting the current household puts the user back in their own. */
    private function backToOwnHousehold(): void
    {
        $user = $this->user()->refresh();

        if ($own = $user->personalTeam()) {
            $user->switchTeam($own);
        }
    }

    /**
     * Jetstream's actions validate into their own error bags, which a modal can't show; turn a
     * refusal into a notification instead and stop the action.
     */
    private function jetstream(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (ValidationException $e) {
            Notification::make()
                ->title(collect($e->errors())->flatten()->first() ?? __('household.notifications.failed'))
                ->danger()
                ->send();

            throw new Halt;
        }
    }
}
