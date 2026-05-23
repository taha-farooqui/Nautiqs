<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Company;
use App\Models\Quote;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\CompanyProvisioner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

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
            'company_name'     => 'required|string|max:150',
            'admin_name'       => 'required|string|max:255',
            'admin_email'      => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class.',email'],
            'password'         => ['required', 'confirmed', Password::min(8)],
            'send_credentials' => 'nullable|boolean',
        ]);

        $user = User::create([
            'name'              => $data['admin_name'],
            'email'             => $data['admin_email'],
            'password'          => Hash::make($data['password']),
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

        // Optional welcome email with the credentials. Wrapped in a
        // try/catch so a Brevo outage doesn't 500 the whole dealer-
        // create flow — the dealer can always be reached out of band.
        $emailSent = false;
        $emailError = null;
        if ((bool) ($data['send_credentials'] ?? false)) {
            try {
                \Illuminate\Support\Facades\Mail::to($data['admin_email'])
                    ->send(new \App\Mail\DealerWelcomeMail($company, $user, $data['password']));
                $emailSent = true;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Dealer welcome email failed', [
                    'company_id' => (string) $company->_id,
                    'to'         => $data['admin_email'],
                    'error'      => $e->getMessage(),
                ]);
                $emailError = $e->getMessage();
            }
        }

        $statusKey = $emailSent
            ? ':name created — credentials emailed to :email.'
            : ($emailError
                ? ':name created — but the welcome email failed to send. Share the password manually.'
                : ':name created — they can log in with :email.');

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
