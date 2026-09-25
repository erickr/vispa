<?php

namespace App\Providers;

use App\Actions\Jetstream\AddTeamMember;
use App\Actions\Jetstream\CreateTeam;
use App\Actions\Jetstream\DeleteTeam;
use App\Actions\Jetstream\DeleteUser;
use App\Actions\Jetstream\InviteTeamMember;
use App\Actions\Jetstream\RemoveTeamMember;
use App\Actions\Jetstream\UpdateTeamName;
use App\Models\Household;
use App\Models\HouseholdInvitation;
use App\Models\HouseholdMembership;
use Illuminate\Support\ServiceProvider;
use Laravel\Jetstream\Jetstream;

class JetstreamServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // No roles: every household member can do the same things with recipes and ingredients,
        // and only the household's owner manages who is in it (HouseholdPolicy). With no roles
        // registered, Jetstream skips roles when adding and inviting members.

        // Households are Jetstream's teams under our own names; see App\Models\Household.
        Jetstream::useTeamModel(Household::class);
        Jetstream::useMembershipModel(HouseholdMembership::class);
        Jetstream::useTeamInvitationModel(HouseholdInvitation::class);

        Jetstream::createTeamsUsing(CreateTeam::class);
        Jetstream::updateTeamNamesUsing(UpdateTeamName::class);
        Jetstream::addTeamMembersUsing(AddTeamMember::class);
        Jetstream::inviteTeamMembersUsing(InviteTeamMember::class);
        Jetstream::removeTeamMembersUsing(RemoveTeamMember::class);
        Jetstream::deleteTeamsUsing(DeleteTeam::class);
        Jetstream::deleteUsersUsing(DeleteUser::class);
    }
}
