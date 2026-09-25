<?php

namespace App\Mail;

use App\Models\TeamInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * Jetstream's invitation mail, but pointing at our accept route and in family wording.
 */
class FamilyInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public TeamInvitation $invitation) {}

    public function build(): static
    {
        return $this->markdown('mail.family-invitation', [
            'acceptUrl' => URL::signedRoute('families.invitations.accept', ['invitation' => $this->invitation]),
        ])->subject(__('family.invitation.subject', ['family' => $this->invitation->team->name]));
    }
}
