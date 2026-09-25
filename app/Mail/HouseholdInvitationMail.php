<?php

namespace App\Mail;

use App\Models\HouseholdInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * Jetstream's invitation mail, but pointing at our accept route and in household wording.
 */
class HouseholdInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public HouseholdInvitation $invitation) {}

    public function build(): static
    {
        return $this->markdown('mail.household-invitation', [
            'acceptUrl' => URL::signedRoute('households.invitations.accept', ['invitation' => $this->invitation]),
        ])->subject(__('household.invitation.subject', ['household' => $this->invitation->household->name]));
    }
}
