<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Handle the email verification link clicked from an email. The route is
 * intentionally NOT behind 'auth' middleware — the signed URL itself is the
 * proof of identity (it embeds the user id and a hash only Laravel can
 * generate). This means clicking the link works from any browser session,
 * including a logged-out one or a fresh device.
 *
 * Flow:
 *   1. Look up the user by {id} from the URL
 *   2. Verify the {hash} matches sha1(email) — the standard Laravel scheme
 *   3. Mark email as verified if not already
 *   4. Log the user in (so the dashboard works post-verification)
 *   5. Redirect to /dashboard?verified=1
 */
class VerifyEmailController extends Controller
{
    public function __invoke(Request $request, string $id, string $hash): RedirectResponse
    {
        $user = User::find($id);

        // Bad link — user gone, or hash doesn't match the user's email.
        if (! $user || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return redirect()->route('login')->withErrors([
                'email' => 'This verification link is invalid. Please request a new one.',
            ]);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        // Log the user in so the dashboard route's 'auth' middleware passes.
        // Skipped if they're already logged in as someone else (rare).
        if (! Auth::check() || Auth::id() !== $user->_id) {
            Auth::login($user);
        }

        return redirect()->route('dashboard')->with('status', 'Email verified — welcome aboard!');
    }
}
