<x-filament-panels::page>
    @php
        $household = $this->household();
        $owner = $household->owner;
        $invitations = $this->invitations();
    @endphp

    <x-filament::section :heading="__('household.page.members')" :description="__('household.page.members_description')">
        <ul class="divide-y divide-gray-100 dark:divide-white/5">
            @foreach ($this->members() as $member)
                <li class="flex items-center gap-3 py-3 first:pt-0 last:pb-0">
                    <x-filament-panels::avatar.user :user="$member" size="md" />

                    <div class="min-w-0 grow">
                        <p class="truncate font-medium text-gray-950 dark:text-white">
                            {{ $member->name }}
                            @if ($member->is(auth()->user()))
                                <span class="font-normal text-gray-500 dark:text-gray-400">· {{ __('household.page.you') }}</span>
                            @endif
                        </p>
                        <p class="truncate text-sm text-gray-500 dark:text-gray-400">{{ $member->email }}</p>
                    </div>

                    @if ($member->is($owner))
                        <x-filament::badge color="primary">{{ __('household.page.owner') }}</x-filament::badge>
                    @else
                        {{ ($this->removeMemberAction)(['user' => $member->getKey()]) }}
                    @endif
                </li>
            @endforeach
        </ul>
    </x-filament::section>

    @if ($invitations->isNotEmpty())
        <x-filament::section :heading="__('household.page.invitations')" :description="__('household.page.invitations_description')">
            <ul class="divide-y divide-gray-100 dark:divide-white/5">
                @foreach ($invitations as $invitation)
                    <li class="flex items-center gap-3 py-3 first:pt-0 last:pb-0">
                        <x-filament::icon icon="heroicon-o-envelope" class="h-5 w-5 shrink-0 text-gray-400" />
                        <p class="min-w-0 grow truncate text-sm text-gray-950 dark:text-white">{{ $invitation->email }}</p>
                        {{ ($this->cancelInvitationAction)(['invitation' => $invitation->getKey()]) }}
                    </li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif
</x-filament-panels::page>
