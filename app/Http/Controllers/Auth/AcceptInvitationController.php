<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

/**
 * Public flow for an invitee to claim their account. The token in the URL
 * is the only credential — single-use, 7-day TTL, scoped to the invite's
 * email so the invitee can't accidentally invite themselves under a
 * different address.
 */
class AcceptInvitationController extends Controller
{
    public function show(string $token)
    {
        $invite = $this->resolveInvite($token);
        return view('auth.accept-invitation', compact('invite'));
    }

    public function store(string $token, Request $request)
    {
        $invite = $this->resolveInvite($token);

        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        // Race condition: if someone signed up with this email between the
        // invite send and the accept, refuse — the admin will need to
        // resolve manually.
        if (User::where('email', $invite->email)->exists()) {
            abort(409, 'An account with this email already exists. Ask your administrator to remove the conflict.');
        }

        $user = User::create([
            'name'                   => $invite->name,
            'email'                  => $invite->email,
            'password'               => $data['password'],
            'role'                   => $invite->role,
            'company_id'             => $invite->company_id,
            'is_active'              => true,
            'invited_by_user_id'     => $invite->invited_by_user_id,
            'invitation_accepted_at' => now(),
            // Invitees skip email verification — they proved access by
            // clicking the link in the invite email.
            'email_verified_at'      => now(),
        ]);

        $invite->update(['accepted_at' => now()]);

        Auth::login($user);

        return redirect()->route('dashboard')
            ->with('status', __('Welcome to :company.', [
                'company' => $user->company?->name ?? config('app.name'),
            ]));
    }

    /**
     * Look up + validate the invite. 404 when missing, 410 (Gone) when the
     * invite has been consumed or expired so we surface a clearer error
     * than a blanket "Not Found".
     */
    private function resolveInvite(string $token): UserInvitation
    {
        $invite = UserInvitation::withoutGlobalScope(\App\Models\Scopes\TenantScope::class)
            ->where('token', $token)
            ->first();

        if (! $invite) abort(404);
        if ($invite->accepted_at) abort(410, 'This invitation has already been used.');
        if ($invite->expires_at && $invite->expires_at->isPast()) {
            abort(410, 'This invitation has expired. Ask the admin to send a new one.');
        }
        return $invite;
    }
}
