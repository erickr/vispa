<?php

namespace App\Filament\Pages;

use App\Models\Team;
use App\Models\TeamInvitation;
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
use Illuminate\Validation\ValidationException;
use Laravel\Jetstream\Actions\ValidateTeamDeletion;
use Laravel\Jetstream\Contracts\CreatesTeams;
use Laravel\Jetstream\Contracts\DeletesTeams;
use Laravel\Jetstream\Contracts\InvitesTeamMembers;
use Laravel\Jetstream\Contracts\RemovesTeamMembers;
use Laravel\Jetstream\Contracts\UpdatesTeamNames;

/**
 * The user's current family: who is in it, who has been invited, and — for the owner — inviting,
 * removing and renaming. Jetstream still does the work (its actions and TeamPolicy); this page
 * is only its face inside the panel, in place of Jetstream's own Livewire views.
 */
class Family extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'family';

    protected string $view = 'filament.pages.family';

    public static function getNavigationLabel(): string
    {
        return __('family.menu');
    }

    public function mount(): void
    {
        abort_unless($this->family() !== null, 404);
    }

    public function getTitle(): string|Htmlable
    {
        return $this->family()->name;
    }

    public function getSubheading(): ?string
    {
        return __('family.page.subheading');
    }

    public function family(): ?Team
    {
        return $this->user()->currentTeam;
    }

    public function isOwner(): bool
    {
        return $this->user()->ownsTeam($this->family());
    }

    /**
     * The owner first, then everyone else by name.
     *
     * @return Collection<int, User>
     */
    public function members(): Collection
    {
        $family = $this->family();

        return $family->allUsers()
            ->sortBy(fn (User $user): string => ($user->is($family->owner) ? '0' : '1').mb_strtolower($user->name))
            ->values();
    }

    /**
     * @return Collection<int, TeamInvitation>
     */
    public function invitations(): Collection
    {
        return $this->family()->teamInvitations()->orderBy('email')->get();
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
            ->label(__('family.actions.invite'))
            ->icon(Heroicon::OutlinedUserPlus)
            ->visible(fn (): bool => $this->isOwner())
            ->modalDescription(__('family.actions.invite_description'))
            ->schema([
                TextInput::make('email')
                    ->label(__('family.fields.email'))
                    ->email()
                    ->required()
                    ->rules([
                        fn () => function (string $attribute, mixed $value, \Closure $fail): void {
                            if ($this->family()->hasUserWithEmail((string) $value)) {
                                $fail(__('family.validation.already_member'));
                            } elseif ($this->family()->teamInvitations()->where('email', $value)->exists()) {
                                $fail(__('family.validation.already_invited'));
                            }
                        },
                    ]),
            ])
            ->modalSubmitActionLabel(__('family.actions.send_invitation'))
            ->action(function (array $data): void {
                $this->jetstream(fn () => app(InvitesTeamMembers::class)
                    ->invite($this->user(), $this->family(), $data['email']));

                Notification::make()->title(__('family.notifications.invited', ['email' => $data['email']]))->success()->send();
            });
    }

    public function switchAction(): Action
    {
        return Action::make('switch')
            ->label(__('family.actions.switch'))
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->color('gray')
            ->visible(fn (): bool => $this->user()->allTeams()->count() > 1)
            ->schema([
                Select::make('family')
                    ->label(__('family.fields.family'))
                    ->options(fn (): array => $this->user()->allTeams()->pluck('name', 'id')->all())
                    ->default(fn (): ?int => $this->family()?->getKey())
                    ->selectablePlaceholder(false)
                    ->native(false)
                    ->required(),
            ])
            ->action(function (array $data): void {
                $family = $this->user()->allTeams()->firstWhere('id', (int) $data['family']);

                abort_unless($family && $this->user()->switchTeam($family), 403);

                $this->redirect(static::getUrl());
            });
    }

    public function renameAction(): Action
    {
        return Action::make('rename')
            ->label(__('family.actions.rename'))
            ->icon(Heroicon::OutlinedPencilSquare)
            ->visible(fn (): bool => $this->isOwner())
            ->fillForm(fn (): array => ['name' => $this->family()->name])
            ->schema([
                TextInput::make('name')->label(__('family.fields.name'))->required()->maxLength(255),
            ])
            ->action(function (array $data): void {
                $this->jetstream(fn () => app(UpdatesTeamNames::class)
                    ->update($this->user(), $this->family(), ['name' => $data['name']]));

                $this->redirect(static::getUrl());
            });
    }

    public function createAction(): Action
    {
        return Action::make('create')
            ->label(__('family.actions.create'))
            ->icon(Heroicon::OutlinedPlusCircle)
            ->modalDescription(__('family.actions.create_description'))
            ->schema([
                TextInput::make('name')->label(__('family.fields.name'))->required()->maxLength(255),
            ])
            ->action(function (array $data): void {
                // Jetstream's CreateTeam switches the user into the new family.
                $this->jetstream(fn () => app(CreatesTeams::class)->create($this->user(), ['name' => $data['name']]));

                $this->redirect(static::getUrl());
            });
    }

    public function leaveAction(): Action
    {
        return Action::make('leave')
            ->label(__('family.actions.leave'))
            ->icon(Heroicon::OutlinedArrowLeftStartOnRectangle)
            ->color('danger')
            ->visible(fn (): bool => ! $this->isOwner())
            ->requiresConfirmation()
            ->modalDescription(__('family.actions.leave_description'))
            ->action(function (): void {
                $family = $this->family();

                $this->jetstream(fn () => app(RemovesTeamMembers::class)->remove($this->user(), $family, $this->user()));
                $this->backToOwnFamily();

                Notification::make()->title(__('family.notifications.left', ['family' => $family->name]))->success()->send();
                $this->redirect(static::getUrl());
            });
    }

    public function deleteAction(): Action
    {
        return Action::make('delete')
            ->label(__('family.actions.delete'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (): bool => $this->isOwner() && ! $this->family()->personal_team)
            ->requiresConfirmation()
            ->modalDescription(__('family.actions.delete_description'))
            ->action(function (): void {
                $family = $this->family();

                $this->jetstream(function () use ($family): void {
                    app(ValidateTeamDeletion::class)->validate($this->user(), $family);
                    app(DeletesTeams::class)->delete($family);
                });
                $this->backToOwnFamily();

                Notification::make()->title(__('family.notifications.deleted', ['family' => $family->name]))->success()->send();
                $this->redirect(static::getUrl());
            });
    }

    public function removeMemberAction(): Action
    {
        return Action::make('removeMember')
            ->label(__('family.actions.remove'))
            ->icon(Heroicon::OutlinedUserMinus)
            ->color('danger')
            ->link()
            ->visible(fn (): bool => $this->isOwner())
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments): string => __('family.actions.remove_heading', [
                'name' => $this->family()->users()->find($arguments['user'] ?? null)?->name,
            ]))
            ->modalDescription(__('family.actions.remove_description'))
            ->action(function (array $arguments): void {
                $member = $this->family()->users()->findOrFail($arguments['user'] ?? null);

                $this->jetstream(fn () => app(RemovesTeamMembers::class)->remove($this->user(), $this->family(), $member));
            });
    }

    public function cancelInvitationAction(): Action
    {
        return Action::make('cancelInvitation')
            ->label(__('family.actions.cancel_invitation'))
            ->color('gray')
            ->link()
            ->visible(fn (): bool => $this->isOwner())
            ->action(function (array $arguments): void {
                $this->family()->teamInvitations()->whereKey($arguments['invitation'] ?? null)->delete();
            });
    }

    private function user(): User
    {
        /** @var User */
        return Auth::user();
    }

    /** Leaving or deleting the current family puts the user back in their own. */
    private function backToOwnFamily(): void
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
                ->title(collect($e->errors())->flatten()->first() ?? __('family.notifications.failed'))
                ->danger()
                ->send();

            throw new Halt;
        }
    }
}
