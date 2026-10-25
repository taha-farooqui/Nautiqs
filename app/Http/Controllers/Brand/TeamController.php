<?php

namespace App\Http\Controllers\Brand;

use App\Http\Controllers\Controller;
use App\Mail\TeamInvitation as TeamInvitationMail;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * The brand account's own people. Every manager has the same rights, so
 * there are no roles here — just invite, resend, revoke and (de)activate.
 * Invitations have no company, so TenantScope is bypassed and every query
 * is pinned to the manager's brand_account_id instead.
 */
class TeamController extends Controller
{
    public function index()
    {
        $accId = $this->accountId();

        return view('brand.team', [
            'members' => User::where('role', User::ROLE_BRAND_MANAGER)->where('brand_account_id', $accId)->orderBy('name')->get(),
            'invites' => $this->invites()->whereNull('accepted_at')->orderBy('created_at', 'desc')->get(),
        ]);
    }

    public function invite(Request $request)
    {
        $data = $request->validate([
            'name'  => 'required|string|max:120',
            'email' => 'required|email|max:200',
        ]);
        $email = mb_strtolower(trim($data['email']));
        if (User::where('email', $email)->exists()) {
            return back()->withErrors(['email' => __('An account with this email already exists.')]);
        }
        if (UserInvitation::withoutGlobalScopes()->where('email', $email)->whereNull('accepted_at')->exists()) {
            return back()->withErrors(['email' => __('An invitation is already pending for this email.')]);
        }

        $invite = UserInvitation::create([
            'company_id'         => null,
            'brand_account_id'   => $this->accountId(),
            'email'              => $email,
            'name'               => trim($data['name']),
            'role'               => User::ROLE_BRAND_MANAGER,
            'token'              => Str::random(64),
            'expires_at'         => now()->addDays(7),
            'invited_by_user_id' => (string) auth()->id(),
            'invited_by_name'    => auth()->user()->name,
        ]);
        $this->send($invite);

        return back()->with('status', __('Invitation sent to :email.', ['email' => $email]));
    }

    public function resend(string $id)
    {
        $invite = $this->invites()->where('_id', $id)->whereNull('accepted_at')->firstOrFail();
        $invite->update(['expires_at' => now()->addDays(7)]);
        $this->send($invite);

        return back()->with('status', __('Invitation re-sent to :email.', ['email' => $invite->email]));
    }

    public function revoke(string $id)
    {
        $this->invites()->where('_id', $id)->whereNull('accepted_at')->firstOrFail()->delete();

        return back()->with('status', __('Invitation revoked.'));
    }

    public function toggle(string $userId)
    {
        $user = User::where('role', User::ROLE_BRAND_MANAGER)->where('brand_account_id', $this->accountId())->where('_id', $userId)->firstOrFail();
        if ((string) $user->_id === (string) auth()->id()) {
            return back()->withErrors(['user' => __('You cannot deactivate your own account.')]);
        }
        $active = $user->is_active === false;
        $user->update(['is_active' => $active]);

        return back()->with('status', $active
            ? __(':name has been reactivated.', ['name' => $user->name])
            : __(':name has been deactivated.', ['name' => $user->name]));
    }

    private function invites()
    {
        return UserInvitation::withoutGlobalScopes()->where('brand_account_id', $this->accountId());
    }

    private function send(UserInvitation $invite): void
    {
        try {
            Mail::to($invite->email)->send(new TeamInvitationMail($invite));
        } catch (\Throwable $e) {
            Log::warning('Brand team invitation email failed', ['invite_id' => (string) $invite->_id, 'error' => $e->getMessage()]);
        }
    }

    private function accountId(): string
    {
        return (string) auth()->user()->brand_account_id;
    }
}
