<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CompanyProvisioner;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(CompanyProvisioner $provisioner)
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            return redirect()->route('login')->withErrors([
                'email' => 'Google sign-in failed. Please try again.',
            ]);
        }

        $user = User::where('email', $googleUser->getEmail())->first();

        if ($user) {
            if (empty($user->google_id)) {
                $user->google_id = $googleUser->getId();
                $user->avatar    = $googleUser->getAvatar();
                $user->email_verified_at ??= now();
                $user->save();
            }
        } else {
            $user = User::create([
                'name'              => $googleUser->getName() ?: $googleUser->getNickname() ?: 'User',
                'email'             => $googleUser->getEmail(),
                'password'          => Str::random(40),
                'google_id'         => $googleUser->getId(),
                'avatar'            => $googleUser->getAvatar(),
                'role'              => User::ROLE_TENANT_ADMIN,
                'company_id'        => null,
                'email_verified_at' => now(),
            ]);

            $provisioner->forNewUser($user);
        }

        // Safety net: existing users from before the provisioner existed.
        if (empty($user->company_id)) {
            $provisioner->forNewUser($user);
        }

        Auth::login($user, remember: true);

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
