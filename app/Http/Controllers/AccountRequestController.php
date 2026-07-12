<?php

namespace App\Http\Controllers;

use App\Models\AccountRequest;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Public "request an account" flow, reachable from the login page. There is
 * no self-service signup — a request lands in the superadmin's queue
 * (/admin/account-requests) and the account is only created on approval.
 */
class AccountRequestController extends Controller
{
    public function create()
    {
        return view('auth.request-account');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:150'],
            'email'        => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'company_name' => ['required', 'string', 'max:150'],
            'phone'        => ['nullable', 'string', 'max:30'],
            'message'      => ['nullable', 'string', 'max:2000'],
            // Honeypot — real users never fill this hidden field.
            'website'      => ['prohibited'],
        ], [
            'website.prohibited' => __('Something went wrong. Please try again.'),
        ]);

        // An account with this email already exists → point them to login.
        if (User::where('email', $data['email'])->exists()) {
            return back()->withInput()->withErrors([
                'email' => __('An account with this email already exists. Try logging in or use "Forgot password".'),
            ]);
        }

        // One live request per email — a resubmit just confirms receipt
        // instead of stacking duplicates in the admin queue.
        $alreadyPending = AccountRequest::where('email', $data['email'])
            ->where('status', AccountRequest::STATUS_PENDING)
            ->exists();

        if (! $alreadyPending) {
            AccountRequest::create([
                'name'         => $data['name'],
                'email'        => $data['email'],
                'company_name' => $data['company_name'],
                'phone'        => $data['phone'] ?? null,
                'message'      => $data['message'] ?? null,
                'status'       => AccountRequest::STATUS_PENDING,
            ]);
        }

        return redirect()->route('account-request.create')
            ->with('status', __('Thank you! Your request has been received — we will email you as soon as your account is ready.'));
    }
}
