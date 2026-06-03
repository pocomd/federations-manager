<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Exceptions\InvalidInvitationException;
use App\Mail\InvitationMail;
use App\Models\Entity;
use App\Models\Federation;
use App\Models\Invitation;
use App\Models\SystemPreference;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class InvitationService
{
    public function create(
        string $email,
        User $invitedBy,
        Federation $federation,
        ?Entity $entity = null,
    ): Invitation {
        $hours = (int) SystemPreference::get('invitation_expiry_hours', 72);

        $invitation = Invitation::create([
            'email'         => $email,
            'token'         => Str::random(64),
            'invited_by'    => $invitedBy->id,
            'federation_id' => $federation->id,
            'entity_id'     => $entity?->id,
            'expires_at'    => now()->addHours($hours),
        ]);

        Mail::to($email)->send(new InvitationMail($invitation));

        return $invitation;
    }

    public function validateToken(string $token): Invitation
    {
        $invitation = Invitation::where('token', $token)
            ->with(['federation', 'entity'])
            ->first();

        if (! $invitation) {
            throw new InvalidInvitationException('This invitation link is invalid or does not exist.');
        }

        if ($invitation->accepted_at !== null) {
            throw new InvalidInvitationException('This invitation has already been used.');
        }

        if ($invitation->revoked_at !== null) {
            throw new InvalidInvitationException('This invitation has been revoked. Please contact your Federation Manager.');
        }

        if ($invitation->isExpired()) {
            throw new InvalidInvitationException('This invitation link has expired. Please contact your Federation Manager.');
        }

        return $invitation;
    }
}
