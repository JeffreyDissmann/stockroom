<?php

declare(strict_types=1);

namespace App\Http\Controllers\Household;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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
                ->get(['id', 'name', 'email', 'created_at'])
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'joined_human' => $user->created_at?->diffForHumans(),
                    'is_self' => $user->id === $request->user()?->id,
                ])
                ->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:100'],
        ]);

        Invitation::create([
            'token' => Invitation::generateToken(),
            'label' => $validated['label'] ?? null,
            'created_by' => $request->user()->id,
            'expires_at' => now()->addDays(Invitation::LIFETIME_DAYS),
        ]);

        return back();
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
     * @return array{id: int, label: string|null, url: string, created_human: string|null, expires_human: string|null, created_by: string|null}
     */
    private function presentInvitation(Invitation $invitation): array
    {
        return [
            'id' => $invitation->id,
            'label' => $invitation->label,
            'url' => route('register', $invitation->token),
            'created_human' => $invitation->created_at?->diffForHumans(),
            'expires_human' => $invitation->expires_at->diffForHumans(),
            'created_by' => $invitation->creator?->name,
        ];
    }
}
