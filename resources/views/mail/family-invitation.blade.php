{{-- Sent by App\Mail\FamilyInvitation. New members sign up in the panel; Fortify's registration is off. --}}
@component('mail::message')
{{ __('family.invitation.intro', ['family' => $invitation->team->name, 'app' => config('app.name')]) }}

{{ __('family.invitation.no_account', ['app' => config('app.name')]) }}

@component('mail::button', ['url' => route('filament.app.auth.register')])
{{ __('family.invitation.create_account') }}
@endcomponent

{{ __('family.invitation.accept_intro') }}

@component('mail::button', ['url' => $acceptUrl])
{{ __('family.invitation.accept') }}
@endcomponent

{{ __('family.invitation.unexpected') }}
@endcomponent
