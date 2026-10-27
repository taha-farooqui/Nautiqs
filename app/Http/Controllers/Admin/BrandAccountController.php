<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\TeamInvitation as TeamInvitationMail;
use App\Models\BrandAccess;
use App\Models\BrandAccount;
use App\Models\ManagedBrand;
use App\Models\User;
use App\Models\UserInvitation;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Brand accounts (manufacturers and importers) — created by the platform
 * admin only. Each account manages one or more brands of one kind and has
 * brand-manager users, invited by email.
 */
class BrandAccountController extends Controller
{
    public function index()
    {
        $accounts = BrandAccount::orderBy('name')->get();
        $ids = $accounts->map(fn ($a) => (string) $a->_id)->all();

        $brands   = ManagedBrand::withoutGlobalScopes()->whereIn('brand_account_id', $ids)->orderBy('name')->get()->groupBy('brand_account_id');
        $managers = User::whereIn('brand_account_id', $ids)->get()->groupBy('brand_account_id');
        $invites  = UserInvitation::withoutGlobalScopes()->whereIn('brand_account_id', $ids)->whereNull('accepted_at')->get()->groupBy('brand_account_id');
        $accesses = BrandAccess::whereIn('brand_account_id', $ids)->get()->groupBy('brand_account_id');

        return view('admin.brand-accounts.index', compact('accounts', 'brands', 'managers', 'invites', 'accesses'));
    }

    public function create()
    {
        return view('admin.brand-accounts.create', ['globalBrands' => ManagedBrand::availableGlobalNames()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:160',
            'kind'          => ['required', Rule::in(BrandAccount::KINDS)],
            'brands'        => 'required|string|max:2000',
            'manager_name'  => 'required|string|max:120',
            'manager_email' => 'required|email|max:200',
        ]);

        $brandNames = $this->parseBrandNames($data['brands']);
        if (! $brandNames) {
            return back()->withInput()->withErrors(['brands' => __('Enter at least one brand.')]);
        }
        if ($taken = $this->takenBrandNames($brandNames)) {
            return back()->withInput()->withErrors(['brands' => trans_choice('{1}:names is already being managed.|[2,*]:names are already being managed.', count($taken), ['names' => implode(', ', $taken)])]);
        }
        $email = mb_strtolower(trim($data['manager_email']));
        if (User::where('email', $email)->exists()) {
            return back()->withInput()->withErrors(['manager_email' => __('An account with this email already exists.')]);
        }

        $account = BrandAccount::create([
            'name'               => trim($data['name']),
            'kind'               => $data['kind'],
            'levels'             => BrandAccount::defaultLevels($data['kind']),
            'is_active'          => true,
            'created_by_user_id' => (string) auth()->id(),
        ]);
        foreach ($brandNames as $i => $name) {
            ManagedBrand::create(ManagedBrand::fromName($name) + ['brand_account_id' => (string) $account->_id, 'position' => $i, 'is_active' => true]);
        }
        $this->invite($account, $data['manager_name'], $email);

        AuditLogger::record('brand_account.create', target: $account, after: ['name' => $account->name, 'kind' => $account->kind, 'brands' => $brandNames]);

        return redirect()->route('admin.brand-accounts.show', $account->_id)
            ->with('status', __('Brand account created. Invitation sent to :email.', ['email' => $email]));
    }

    public function show(string $id)
    {
        $account  = BrandAccount::findOrFail($id);
        $brands   = ManagedBrand::withoutGlobalScopes()->where('brand_account_id', $id)->orderBy('position')->orderBy('name')->get();
        $managers = User::where('brand_account_id', $id)->orderBy('name')->get();
        $invites  = UserInvitation::withoutGlobalScopes()->where('brand_account_id', $id)->whereNull('accepted_at')->orderBy('created_at', 'desc')->get();
        $accesses = BrandAccess::where('brand_account_id', $id)->orderBy('invited_at', 'desc')->get();
        $companies = \App\Models\Company::whereIn('_id', $accesses->pluck('company_id')->filter()->unique()->values()->all())->get()->keyBy(fn ($c) => (string) $c->_id);

        // Catalogue size per brand, and quotes made with this account's products.
        $itemClass = $account->isEngine() ? \App\Models\BrandEngine::class : \App\Models\BrandModel::class;
        $itemCounts = $itemClass::withoutGlobalScopes()->where('brand_account_id', $id)->where('is_archived', '!=', true)
            ->get(['managed_brand_id'])->countBy(fn ($m) => (string) $m->managed_brand_id);
        $quoteCount = \App\Models\Quote::withoutGlobalScopes()->where('brand_account_ids', $id)->count();

        $globalBrands = ManagedBrand::availableGlobalNames();

        return view('admin.brand-accounts.show', compact('account', 'brands', 'managers', 'invites', 'accesses', 'companies', 'itemCounts', 'quoteCount', 'globalBrands'));
    }

    public function update(string $id, Request $request)
    {
        $account = BrandAccount::findOrFail($id);
        $data = $request->validate(['name' => 'required|string|max:160']);
        $account->update(['name' => trim($data['name'])]);

        return back()->with('status', __('Brand account updated.'));
    }

    public function toggle(string $id)
    {
        $account = BrandAccount::findOrFail($id);
        $account->update(['is_active' => ! $account->is_active]);
        AuditLogger::record($account->is_active ? 'brand_account.reactivate' : 'brand_account.suspend', target: $account);

        return back()->with('status', $account->is_active ? __('Brand account reactivated.') : __('Brand account suspended. Its managers can no longer sign in to the portal.'));
    }

    public function addBrand(string $id, Request $request)
    {
        $account = BrandAccount::findOrFail($id);
        $data = $request->validate(['brand' => 'required|string|max:120']);
        $name = trim($data['brand']);
        if ($this->takenBrandNames([$name])) {
            return back()->withErrors(['brand' => __(':names is already being managed.', ['names' => $name])]);
        }
        $count = ManagedBrand::withoutGlobalScopes()->where('brand_account_id', $id)->count();
        ManagedBrand::create(ManagedBrand::fromName($name) + ['brand_account_id' => $id, 'position' => $count, 'is_active' => true]);

        return back()->with('status', __('Brand :name added.', ['name' => $name]));
    }

    public function renameBrand(string $id, string $brandId, Request $request)
    {
        $brand = ManagedBrand::withoutGlobalScopes()->where('brand_account_id', $id)->where('_id', $brandId)->firstOrFail();
        $data = $request->validate(['name' => 'required|string|max:120']);
        $name = trim($data['name']);
        if (mb_strtolower($name) !== mb_strtolower((string) $brand->name) && $this->takenBrandNames([$name])) {
            return back()->withErrors(['brand' => __(':names is already being managed.', ['names' => $name])]);
        }
        $brand->update(['name' => $name]);
        // Dealers see the new name on their next sync.
        app(\App\Services\Brand\BrandSync::class)->syncAccount(BrandAccount::findOrFail($id), notify: false);

        return back()->with('status', __('Brand renamed.'));
    }

    public function inviteManager(string $id, Request $request)
    {
        $account = BrandAccount::findOrFail($id);
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
        $this->invite($account, $data['name'], $email);

        return back()->with('status', __('Invitation sent to :email.', ['email' => $email]));
    }

    public function resendInvite(string $id, string $inviteId)
    {
        $invite = UserInvitation::withoutGlobalScopes()->where('brand_account_id', $id)->where('_id', $inviteId)->whereNull('accepted_at')->firstOrFail();
        $invite->update(['expires_at' => now()->addDays(7)]);
        $this->sendInvite($invite);

        return back()->with('status', __('Invitation re-sent to :email.', ['email' => $invite->email]));
    }

    /* ------------------------------------------------------------ helpers */

    private function invite(BrandAccount $account, string $name, string $email): void
    {
        $invite = UserInvitation::create([
            'company_id'         => null,
            'brand_account_id'   => (string) $account->_id,
            'email'              => $email,
            'name'               => trim($name),
            'role'               => User::ROLE_BRAND_MANAGER,
            'token'              => Str::random(64),
            'expires_at'         => now()->addDays(7),
            'invited_by_user_id' => (string) auth()->id(),
            'invited_by_name'    => auth()->user()->name,
        ]);
        $this->sendInvite($invite);
    }

    private function sendInvite(UserInvitation $invite): void
    {
        try {
            Mail::to($invite->email)->send(new TeamInvitationMail($invite));
        } catch (\Throwable $e) {
            Log::warning('Brand manager invitation email failed', ['invite_id' => (string) $invite->_id, 'error' => $e->getMessage()]);
        }
    }

    /** "Nordvik, Azura" or one per line → unique trimmed names. */
    private function parseBrandNames(string $raw): array
    {
        $names = [];
        foreach (preg_split('/[\r\n,;]+/', $raw) as $n) {
            $n = trim($n);
            if ($n !== '' && ! in_array(mb_strtolower($n), array_map('mb_strtolower', $names), true)) {
                $names[] = mb_substr($n, 0, 120);
            }
        }
        return $names;
    }

    /** Names already managed by some account (case-insensitive). */
    private function takenBrandNames(array $names): array
    {
        $existing = ManagedBrand::withoutGlobalScopes()->get(['name'])->map(fn ($b) => mb_strtolower(trim((string) $b->name)))->all();

        return array_values(array_filter($names, fn ($n) => in_array(mb_strtolower(trim($n)), $existing, true)));
    }
}
