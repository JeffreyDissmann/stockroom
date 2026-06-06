<?php

declare(strict_types=1);

namespace App\Http\Controllers\Household;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\InvitationInvite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class InvitationController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('household/Members', [
            'invitations' => Invitation::pending()
                ->with('creator:id,name')
                ->latest()
                ->get()
                ->map(fn (Invitation $invitation): array => $this->presentInvitation($invitation))
                ->values(),
            'members' => User::query()
                ->oldest()
                ->get(['id', 'name', 'email', 'created_at', 'is_admin'])
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'joined_human' => $user->created_at?->diffForHumans(),
                    'is_self' => $user->id === $request->user()?->id,
                    'is_admin' => $user->is_admin,
                ])
                ->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:100'],
            // Optional: with an address the invite is also emailed; without
            // one the copy-paste-the-link flow works exactly as before.
            'email' => ['nullable', 'string', 'lowercase', 'email', 'max:255'],
        ]);

        $invitation = Invitation::create([
            'token' => Invitation::generateToken(),
            'label' => $validated['label'] ?? null,
            'email' => $validated['email'] ?? null,
            'created_by' => $request->user()->id,
            'expires_at' => now()->addDays(Invitation::LIFETIME_DAYS),
        ]);

        if ($invitation->email !== null) {
            return $this->send($request, $invitation);
        }

        return back();
    }

    /**
     * Email (or re-email) a pending invite to its stored address. The
     * invite exists either way — an SMTP hiccup must not eat it, so a
     * transport failure surfaces as a flash and the admin keeps the
     * copyable link.
     */
    private function send(Request $request, Invitation $invitation): RedirectResponse
    {
        try {
            Notification::route('mail', $invitation->email)
                ->notify((new InvitationInvite($invitation->loadMissing('creator')))
                    ->locale($request->user()->locale ?? config('app.locale')));
        } catch (Throwable $e) {
            report($e);

            return back()->with('invitation_mail', 'failed');
        }

        return back()->with('invitation_mail', 'sent');
    }

    /**
     * Revoke a pending invite link so it can no longer be used.
     */
    public function destroy(Invitation $invitation): RedirectResponse
    {
        abort_unless($invitation->isPending(), 403);

        $invitation->delete();

        return back();
    }

    /**
     * Re-mail a pending invite to its stored address.
     */
    public function resend(Request $request, Invitation $invitation): RedirectResponse
    {
        // No stored address = the UI never offered this (the button only
        // renders for emailed invites) — a crafted call, hard stop.
        abort_if($invitation->email === null, 403);

        // No longer pending = a stale page (accepted/expired while open).
        // A validation error redirects back and the refreshed pending list
        // simply no longer contains the invite.
        if (! $invitation->isPending()) {
            throw ValidationException::withMessages([
                'invitation' => __('members.resend_unavailable'),
            ]);
        }

        return $this->send($request, $invitation);
    }

    /**
     * @return array{id: int, label: string|null, email: string|null, url: string, created_human: string|null, expires_human: string|null, created_by: string|null}
     */
    private function presentInvitation(Invitation $invitation): array
    {
        return [
            'id' => $invitation->id,
            'label' => $invitation->label,
            'email' => $invitation->email,
            'url' => route('register', $invitation->token),
            'created_human' => $invitation->created_at?->diffForHumans(),
            'expires_human' => $invitation->expires_at->diffForHumans(),
            'created_by' => $invitation->creator?->name,
        ];
    }
}
