<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\DealerWelcomeMail;
use App\Models\AccountRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\CompanyProvisioner;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Str;

/**
 * Superadmin review queue for public registration requests. Approving one
 * provisions the dealership exactly like /admin/dealers/create (user with a
 * random password + CompanyProvisioner) and always emails the requester a
 * single-use account-setup link — never a readable password.
 */
class AccountRequestController extends Controller
{
    public function index()
    {
        $pending = AccountRequest::where('status', AccountRequest::STATUS_PENDING)
            ->orderBy('created_at', 'asc')
            ->get();

        $handled = AccountRequest::where('status', '!=', AccountRequest::STATUS_PENDING)
            ->orderBy('handled_at', 'desc')
            ->limit(50)
            ->get();

        return view('admin.account-requests.index', compact('pending', 'handled'));
    }

    public function approve(string $id, CompanyProvisioner $provisioner)
    {
        $req = AccountRequest::where('_id', $id)->firstOrFail();

        if (! $req->isPending()) {
            return back()->withErrors(['request' => __('This request has already been handled.')]);
        }

        // The email may have been registered since the request came in
        // (e.g. the superadmin created the dealer manually).
        if (User::where('email', $req->email)->exists()) {
            $req->update([
                'status'          => AccountRequest::STATUS_REJECTED,
                'handled_at'      => now(),
                'handled_by_name' => auth()->user()->name,
            ]);
            return back()->withErrors(['request' => __('An account with :email already exists — request closed.', ['email' => $req->email])]);
        }

        // Same provisioning as Admin\DealerController::store — random password
        // that is never shown; the requester sets their own via the setup link.
        $user = User::create([
            'name'              => $req->name,
            'email'             => $req->email,
            'password'          => Hash::make(Str::password(24)),
            'role'              => User::ROLE_TENANT_ADMIN,
            'company_id'        => null,
            'email_verified_at' => now(),
        ]);

        $company = $provisioner->forNewUser($user);
        $company->update(['name' => $req->company_name]);

        AuditLogger::record('account_request.approve',
            target: $company,
            after: ['company_name' => $req->company_name, 'admin_email' => $req->email],
            targetLabel: $company->name);

        // Account-created email with the single-use setup link. A mail outage
        // must not undo the approval — the dealer can still use "Forgot
        // password", and the flash tells the superadmin what happened.
        $emailError = null;
        try {
            $token = PasswordBroker::broker()->createToken($user);
            $setupUrl = route('password.reset', ['token' => $token, 'email' => $user->email]);

            Mail::to($user->email)->send(new DealerWelcomeMail($company, $user, $setupUrl));
        } catch (\Throwable $e) {
            Log::warning('Account-request approval email failed', [
                'request_id' => (string) $req->_id,
                'to'         => $req->email,
                'error'      => $e->getMessage(),
            ]);
            $emailError = $e->getMessage();
        }

        $req->update([
            'status'             => AccountRequest::STATUS_APPROVED,
            'handled_at'         => now(),
            'handled_by_name'    => auth()->user()->name,
            'created_company_id' => (string) $company->_id,
        ]);

        return redirect()->route('admin.account-requests.index')
            ->with('status', $emailError
                ? __(':name approved — but the email failed to send. Ask :email to use "Forgot password" on the login page.', ['name' => $req->company_name, 'email' => $req->email])
                : __(':name approved — an account setup link was emailed to :email.', ['name' => $req->company_name, 'email' => $req->email]));
    }

    public function reject(string $id)
    {
        $req = AccountRequest::where('_id', $id)->firstOrFail();

        if (! $req->isPending()) {
            return back()->withErrors(['request' => __('This request has already been handled.')]);
        }

        $req->update([
            'status'          => AccountRequest::STATUS_REJECTED,
            'handled_at'      => now(),
            'handled_by_name' => auth()->user()->name,
        ]);

        AuditLogger::record('account_request.reject',
            target: $req,
            after: ['email' => $req->email, 'company_name' => $req->company_name],
            targetLabel: $req->company_name);

        return back()->with('status', __('Request from :email rejected.', ['email' => $req->email]));
    }
}
