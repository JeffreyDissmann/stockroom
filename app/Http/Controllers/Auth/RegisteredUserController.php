<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterInvitedUserRequest;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Show the registration page for a holder of an invite link.
     *
     * An invalid, expired, or already-used token shows a dead-end page rather
     * than the form — there is no open registration without an invite.
     */
    public function create(string $token): Response
    {
        $invitation = Invitation::where('token', $token)->first();

        if (! $invitation || ! $invitation->isPending()) {
            return Inertia::render('auth/InviteInvalid');
        }

        return Inertia::render('auth/Register', [
            'token' => $token,
            'invitedBy' => $invitation->creator?->name,
        ]);
    }

    /**
     * Create the account behind a valid invite, then sign the new user in.
     */
    public function store(RegisterInvitedUserRequest $request, string $token): RedirectResponse
    {
        $invitation = Invitation::where('token', $token)->first();

        if (! $invitation || ! $invitation->isPending()) {
            return redirect()->route('login')->withErrors([
                'email' => 'This invite link is no longer valid.',
            ]);
        }

        $user = DB::transaction(function () use ($request, $invitation): User {
            $user = User::create($request->safe()->only(['name', 'email', 'password']));

            // No mail server, so there is nothing to verify against — trust the invite.
            $user->forceFill(['email_verified_at' => now()])->save();

            $invitation->forceFill([
                'accepted_by' => $user->id,
                'accepted_at' => now(),
            ])->save();

            return $user;
        });

        Auth::login($user);

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
