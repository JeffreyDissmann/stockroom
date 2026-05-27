<?php

declare(strict_types=1);

namespace App\Http\Controllers\Household;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    /**
     * Promote a member to admin or demote them back to a regular user.
     *
     * Admins cannot change their own role — that protection guarantees the
     * acting admin always remains, so the household can never be left without one.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 403, 'You cannot change your own role.');

        $validated = $request->validate([
            'is_admin' => ['required', 'boolean'],
        ]);

        $user->update(['is_admin' => $validated['is_admin']]);

        return back();
    }

    /**
     * Remove a member's account. Their pending invites go with them (FK cascade);
     * the shared inventory is household-wide and untouched.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 403, 'You cannot remove your own account here.');

        $user->delete();

        return back();
    }
}
