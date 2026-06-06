<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * The invite email: "X invited you to the household Stockroom", with the
 * single-use registration link and its expiry. Sent on demand to a bare
 * address (the invitee has no account yet).
 *
 * QUEUED — the admin's "create invite" request shouldn't block on SMTP;
 * the prod compose runs a dedicated queue worker. A transport failure
 * lands in failed_jobs rather than in the admin's face, which is the
 * accepted trade-off; the copyable link on the Members page is the
 * fallback either way.
 *
 * Rendered in the INVITER's locale via ->locale(): the invitee's
 * preference is unknowable, and a household most likely shares one.
 */
class InvitationInvite extends Notification implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /**
     * An invite revoked before the worker picks the job up is gone on
     * purpose — drop the send silently instead of recording a failed job.
     */
    public bool $deleteWhenMissingModels = true;

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
