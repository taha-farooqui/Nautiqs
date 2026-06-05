<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\DealerWelcomeMail;
use App\Models\Client;
use App\Models\Company;
use App\Models\Quote;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\CompanyProvisioner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Str;

/**
 * Spec §4.3 — superadmin dealer (tenant) management. List all subscribing
 * dealerships, drill into a single one for profile + stats + users,
 * toggle suspended status.
 *
 * "Dealer" is the user-facing name; the underlying model is still
 * Company (spec terminology).
 */
class DealerController extends Controller
{
    public function index(Request $request)
    {
        $q      = trim((string) $request->query('q', ''));
        $status = $request->query('status', 'all');

        $query = Company::orderBy('created_at', 'desc');

        if ($q !== '') {
            $regex = ['$regex' => preg_quote($q, '/'), '$options' => 'i'];
            $query->where(function ($w) use ($regex) {
                $w->whereRaw(['name'              => $regex])
                  ->orWhereRaw(['siren'           => $regex])
                  ->orWhereRaw(['vat_number'      => $regex])
                  ->orWhereRaw(['salesperson_email' => $regex]);
            });
        }

        if ($status === 'active') {
            $query->where('status', '!=', 'suspended');
        } elseif ($status === 'suspended') {
            $query->where('status', 'suspended');
        }

        $dealers = $query->paginate(20)->withQueryString();

        // Decorate each row with cheap aggregates.
        $ids = collect($dealers->items())->pluck('_id')->map(fn ($i) => (string) $i);
        $userCounts = User::whereIn('company_id', $ids->all())
            ->get(['company_id'])
            ->groupBy(fn ($u) => (string) $u->company_id)
            ->map->count();
        $quoteCounts = Quote::whereIn('company_id', $ids->all())
            ->get(['company_id'])
            ->groupBy(fn ($q) => (string) $q->company_id)
            ->map->count();

        // Primary user per dealer = tenant_admin if any, otherwise oldest user.
        // Used as the Contact column on the list — clearer than the
        // company's salesperson_email which is a PDF/email-from field.
        $allUsers = User::whereIn('company_id', $ids->all())
            ->orderBy('created_at')
            ->get(['name', 'email', 'role', 'company_id']);
        $primaryByCompany = $allUsers->groupBy(fn ($u) => (string) $u->company_id)
            ->map(fn ($users) => $users->sortByDesc(fn ($u) => $u->role === User::ROLE_TENANT_ADMIN ? 1 : 0)->first());

        foreach ($dealers as $d) {
            $cid = (string) $d->_id;
            $d->_users_count  = $userCounts->get($cid, 0);
            $d->_quotes_count = $quoteCounts->get($cid, 0);
            $d->_primary_user = $primaryByCompany->get($cid);
        }

        $totals = [
            'all'       => Company::count(),
            'active'    => Company::where('status', '!=', 'suspended')->count(),
            'suspended' => Company::where('status', 'suspended')->count(),
        ];

        return view('admin.dealers.index', compact('dealers', 'q', 'status', 'totals'));
    }

    public function create()
    {
        return view('admin.dealers.create');
    }

    /**
     * Provision a new dealership and its initial admin user. Replaces the
     * removed public /register flow: same shape (User → CompanyProvisioner
     * creates the Company) but launched by the superadmin from
     * /admin/dealers/create instead of a self-service signup page.
     */
    public function store(Request $request, CompanyProvisioner $provisioner)
    {
        $data = $request->validate([
            'company_name'    => 'required|string|max:150',
            'admin_name'      => 'required|string|max:255',
            'admin_email'     => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class.',email'],
            'send_setup_link' => 'nullable|boolean',
        ]);

        // The superadmin never sets a password. We mint a strong random one
        // so the account hash is valid, but it is never shown or sent — the
        // dealer sets their own via the setup link (or "Forgot password").
        $plainPassword = Str::password(24);

        $user = User::create([
            'name'              => $data['admin_name'],
            'email'             => $data['admin_email'],
            'password'          => Hash::make($plainPassword),
            'role'              => User::ROLE_TENANT_ADMIN,
            'company_id'        => null,
            'email_verified_at' => now(),   // superadmin-created = trusted, skip verification
        ]);

        $company = $provisioner->forNewUser($user);
        $company->update(['name' => $data['company_name']]);

        AuditLogger::record('dealer.create',
            target: $company,
            after: ['company_name' => $data['company_name'], 'admin_email' => $data['admin_email']],
            targetLabel: $company->name);

        // Optional account-setup email. Instead of a readable password we send
        // a single-use password-setup link (a password-reset token via the
        // standard broker), so the dealer chooses their own password. Wrapped
        // in a try/catch so a mail outage doesn't 500 the create flow.
        $sendSetup  = (bool) ($data['send_setup_link'] ?? false);
        $emailSent  = false;
        $emailError = null;
        if ($sendSetup) {
            try {
                $token = PasswordBroker::broker()->createToken($user);
                $setupUrl = route('password.reset', ['token' => $token, 'email' => $user->email]);

                Mail::to($user->email)->send(new DealerWelcomeMail($company, $user, $setupUrl));
                $emailSent = true;
            } catch (\Throwable $e) {
                Log::warning('Dealer setup email failed', [
                    'company_id' => (string) $company->_id,
                    'to'         => $user->email,
                    'error'      => $e->getMessage(),
                ]);
                $emailError = $e->getMessage();
            }
        }

        if ($emailSent) {
            $statusKey = ':name created — an account setup link was emailed to :email.';
        } elseif ($emailError) {
            $statusKey = ':name created — but the setup email failed to send. Ask them to use “Forgot password” on the login page.';
        } else {
            $statusKey = ':name created — ask :email to use “Forgot password” on the login page to set their password.';
        }

        return redirect()->route('admin.dealers.show', $company->_id)
            ->with('status', __($statusKey, [
                'name'  => $company->name,
                'email' => $data['admin_email'],
            ]));
    }

    public function show(string $id)
    {
        $dealer = Company::where('_id', $id)->firstOrFail();

        $users = User::where('company_id', $id)->orderBy('created_at')->get();

        $now           = now();
        $startOfMonth  = $now->copy()->startOfMonth();
        $stats = [
            'quotes_total'    => Quote::where('company_id', $id)->count(),
            'quotes_month'    => Quote::where('company_id', $id)->where('created_at', '>=', $startOfMonth)->count(),
            'clients_total'   => Client::where('company_id', $id)->count(),
            'last_activity'   => Quote::where('company_id', $id)->orderBy('created_at', 'desc')->value('created_at'),
            'revenue_won_ytd' => (float) Quote::where('company_id', $id)
                ->where('status', Quote::STATUS_WON)
                ->where('won_at', '>=', $now->copy()->startOfYear())
                ->get(['totals'])
                ->sum(fn ($q) => (float) ($q->totals['total_ht'] ?? 0)),
        ];

        return view('admin.dealers.show', compact('dealer', 'users', 'stats'));
    }

    public function suspend(string $id)
    {
        $dealer = Company::where('_id', $id)->firstOrFail();
        $before = ['status' => $dealer->status];
        $dealer->update(['status' => 'suspended']);

        AuditLogger::record('dealer.suspend', target: $dealer, before: $before, after: ['status' => 'suspended']);

        return back()->with('status', __(':name has been suspended.', ['name' => $dealer->name]));
    }

    public function reactivate(string $id)
    {
        $dealer = Company::where('_id', $id)->firstOrFail();
        $before = ['status' => $dealer->status];
        $dealer->update(['status' => 'active']);

        AuditLogger::record('dealer.reactivate', target: $dealer, before: $before, after: ['status' => 'active']);

        return back()->with('status', __(':name has been reactivated.', ['name' => $dealer->name]));
    }
}
