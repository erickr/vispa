{{-- Sent by App\Mail\HouseholdInvitationMail. New members sign up in the panel; Fortify's registration is off. --}}
@component('mail::message')
{{ __('household.invitation.intro', ['household' => $invitation->household->name, 'app' => config('app.name')]) }}

{{ __('household.invitation.no_account', ['app' => config('app.name')]) }}

@component('mail::button', ['url' => route('filament.app.auth.register')])
{{ __('household.invitation.create_account') }}
@endcomponent

{{ __('household.invitation.accept_intro') }}

@component('mail::button', ['url' => $acceptUrl])
{{ __('household.invitation.accept') }}
@endcomponent

{{ __('household.invitation.unexpected') }}
@endcomponent
