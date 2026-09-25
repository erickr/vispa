<?php

namespace App\Http\Controllers;

use App\Filament\Resources\Recipes\RecipeResource;
use App\Models\TeamInvitation;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Jetstream\Contracts\AddsTeamMembers;

/**
 * Stands in for Jetstream's own accept route, which adds whoever owns the invited address —
 * not the person who clicked — leaves them in their old family and reports back with a banner
 * the panel never shows. Here the invitation has to be accepted from the invited account, the
 * new member lands in the family they just joined, and the panel tells them so.
 */
class AcceptFamilyInvitation extends Controller
{
    public function __invoke(Request $request, int $invitation): RedirectResponse
    {
        $invitation = TeamInvitation::with('team.owner')->findOrFail($invitation);
        $user = $request->user();
        $family = $invitation->team;

        if (strcasecmp((string) $user->email, (string) $invitation->email) !== 0) {
            Notification::make()
                ->title(__('family.invitation.wrong_account_title'))
                ->body(__('family.invitation.wrong_account_body', ['email' => $invitation->email, 'family' => $family->name]))
                ->danger()
                ->persistent()
                ->send();

            return redirect()->to(RecipeResource::getUrl('index'));
        }

        if (! $user->belongsToTeam($family)) {
            app(AddsTeamMembers::class)->add($family->owner, $family, $invitation->email, $invitation->role);
        }

        $invitation->delete();
        $user->refresh()->switchTeam($family);

        Notification::make()
            ->title(__('family.invitation.joined_title', ['family' => $family->name]))
            ->body(__('family.invitation.joined_body'))
            ->success()
            ->send();

        return redirect()->to(RecipeResource::getUrl('index'));
    }
}
