<?php

namespace App\Http\Controllers;

use App\Filament\Pages\MyHousehold;
use App\Models\HouseholdInvitation;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Jetstream\Contracts\AddsTeamMembers;

/**
 * Stands in for Jetstream's own accept route, which adds whoever owns the invited address —
 * not the person who clicked — leaves them in their old household and reports back with a banner
 * the panel never shows. Here the invitation has to be accepted from the invited account, the
 * new member lands in the household they just joined, and the panel tells them so.
 */
class AcceptHouseholdInvitation extends Controller
{
    public function __invoke(Request $request, int $invitation): RedirectResponse
    {
        $invitation = HouseholdInvitation::with('household.owner')->findOrFail($invitation);
        $user = $request->user();
        $household = $invitation->household;

        if (strcasecmp((string) $user->email, (string) $invitation->email) !== 0) {
            Notification::make()
                ->title(__('household.invitation.wrong_account_title'))
                ->body(__('household.invitation.wrong_account_body', ['email' => $invitation->email, 'household' => $household->name]))
                ->danger()
                ->persistent()
                ->send();

            return redirect()->to(MyHousehold::getUrl());
        }

        if (! $user->belongsToTeam($household)) {
            app(AddsTeamMembers::class)->add($household->owner, $household, $invitation->email, $invitation->role);
        }

        $invitation->delete();
        $user->refresh()->switchTeam($household);

        Notification::make()
            ->title(__('household.invitation.joined_title', ['household' => $household->name]))
            ->body(__('household.invitation.joined_body'))
            ->success()
            ->send();

        return redirect()->to(MyHousehold::getUrl());
    }
}
