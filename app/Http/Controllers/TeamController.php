<?php

namespace App\Http\Controllers;

use App\Mail\TeamInvitation as TeamInvitationMail;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Multi-user team management for a dealership. Reachable only by
 * tenant_admin (enforced at the route level). Lists active members,
 * pending invites, and exposes invite / revoke / resend / role-change /
 * deactivate / activate actions.
 */
class TeamController extends Controller
{
    public function index()
    {
        $companyId = (string) auth()->user()->company_id;

        // Active members — both admins and salespeople. Sort admins first
        // (role asc → 'tenant_admin' < 'tenant_user' lexicographically), then
        // by name. Done in PHP since laravel-mongodb doesn't support
        // multi-column orderBy reliably.
        $members = User::where('company_id', $companyId)
            ->whereIn('role', [User::ROLE_TENANT_ADMIN, User::ROLE_TENANT_USER])
            ->get()
            ->sortBy([
                ['role', 'asc'],
                ['name', 'asc'],
            ])
            ->values();

        // Pending invites (not yet accepted, not expired). Show expired ones
        // too so the admin sees them and can resend.
        $invites = UserInvitation::where('company_id', $companyId)
            ->whereNull('accepted_at')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('team.index', compact('members', 'invites'));
    }

    public function invite(Request $request)
    {
        $data = $request->validate([
            'name'  => 'required|string|max:120',
            'email' => 'required|email|max:200',
            'role'  => 'required|in:' . User::ROLE_TENANT_ADMIN . ',' . User::ROLE_TENANT_USER,
        ]);

        $companyId = (string) auth()->user()->company_id;

        // Email uniqueness across the platform — Laravel auth assumes a
        // global unique email. Reject if it's already in use.
        if (User::where('email', $data['email'])->exists()) {
            return back()->withErrors(['email' => __('An account with this email already exists.')]);
        }

        // Reject if there's already a pending invite for this email at this
        // company. Admin can resend the existing one instead.
        $existing = UserInvitation::where('company_id', $companyId)
            ->where('email', $data['email'])
            ->whereNull('accepted_at')
            ->first();
        if ($existing) {
            return back()->withErrors(['email' => __('An invitation is already pending for this email.')]);
        }

        $invite = UserInvitation::create([
            'company_id'         => $companyId,
            'email'              => $data['email'],
            'name'               => $data['name'],
            'role'               => $data['role'],
            'token'              => Str::random(64),
            'expires_at'         => now()->addDays(7),
            'invited_by_user_id' => (string) auth()->id(),
            'invited_by_name'    => auth()->user()->name,
        ]);

        $this->sendInviteEmail($invite);

        return back()->with('status', __('Invitation sent to :email.', ['email' => $invite->email]));
    }

    public function resend(string $id)
    {
        $invite = UserInvitation::findOrFail($id);
        if ($invite->accepted_at) {
            return back()->withErrors(['invite' => __('This invitation has already been accepted.')]);
        }
        // Refresh the expiry so the link keeps working another 7 days.
        $invite->update(['expires_at' => now()->addDays(7)]);
        $this->sendInviteEmail($invite);
        return back()->with('status', __('Invitation re-sent to :email.', ['email' => $invite->email]));
    }

    public function revoke(string $id)
    {
        $invite = UserInvitation::findOrFail($id);
        if ($invite->accepted_at) {
            return back()->withErrors(['invite' => __('This invitation has already been accepted.')]);
        }
        $invite->delete();
        return back()->with('status', __('Invitation revoked.'));
    }

    public function deactivate(string $userId)
    {
        $user = $this->findTeammate($userId);
        if ((string) $user->_id === (string) auth()->id()) {
            return back()->withErrors(['user' => __('You cannot deactivate your own account.')]);
        }
        $user->update(['is_active' => false]);
        return back()->with('status', __(':name has been deactivated.', ['name' => $user->name]));
    }

    public function activate(string $userId)
    {
        $user = $this->findTeammate($userId);
        $user->update(['is_active' => true]);
        return back()->with('status', __(':name has been reactivated.', ['name' => $user->name]));
    }

    public function updateRole(string $userId, Request $request)
    {
        $data = $request->validate([
            'role' => 'required|in:' . User::ROLE_TENANT_ADMIN . ',' . User::ROLE_TENANT_USER,
        ]);
        $user = $this->findTeammate($userId);
        if ((string) $user->_id === (string) auth()->id()) {
            return back()->withErrors(['user' => __('You cannot change your own role. Ask another admin.')]);
        }
        $user->update(['role' => $data['role']]);
        return back()->with('status', __('Role updated.'));
    }

    /**
     * Look a user up by id and confirm they belong to the current admin's
     * company. Cross-tenant access is impossible because of the global
     * scope, but the explicit check guards against accidental superadmin
     * leakage if that ever lands in the same controller.
     */
    private function findTeammate(string $userId): User
    {
        $user = User::findOrFail($userId);
        if ((string) $user->company_id !== (string) auth()->user()->company_id) {
            abort(404);
        }
        return $user;
    }

    private function sendInviteEmail(UserInvitation $invite): void
    {
        try {
            Mail::to($invite->email)->send(new TeamInvitationMail($invite));
        } catch (\Throwable $e) {
            // Log but don't fail the action — the admin can resend later.
            Log::warning('Team invitation email failed', [
                'invite_id' => (string) $invite->_id,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
