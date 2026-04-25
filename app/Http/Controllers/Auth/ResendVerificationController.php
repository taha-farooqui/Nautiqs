<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ResendVerificationController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $email = $request->session()->get('unverified_email');

        if (! $email) {
            return redirect()->route('login');
        }

        $user = User::where('email', $email)->first();

        if ($user && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        $request->session()->forget('unverified_email');

        return redirect()->route('login')->with(
            'status',
            'A fresh verification link has been sent to ' . $email . '.'
        );
    }
}
