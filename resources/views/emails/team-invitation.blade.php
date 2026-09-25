{{-- Jetstream's own copy points at Fortify's registration, which is off here; new members sign up in the panel. --}}
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
