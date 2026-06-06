<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Invitation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The invite email: "X invited you to the household Stockroom", with the
 * single-use registration link and its expiry. Sent on demand to a bare
 * address (the invitee has no account yet), synchronously like the
 * maintenance digest — no queue-worker dependency on the NAS.
 *
 * Rendered in the INVITER's locale via ->locale(): the invitee's
 * preference is unknowable, and a household most likely shares one.
 */
class InvitationInvite extends Notification
{
    public function __construct(public readonly Invitation $invitation) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inviter = $this->invitation->creator?->name ?? config('app.name');

        return (new MailMessage)
            ->subject(__('members.mail.subject', ['name' => $inviter]))
            ->line(__('members.mail.intro', ['name' => $inviter]))
            ->action(__('members.mail.action'), route('register', $this->invitation->token))
            ->line(__('members.mail.expiry', ['days' => Invitation::LIFETIME_DAYS]));
    }
}
