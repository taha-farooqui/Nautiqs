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
        return view('admin.brand-accounts.create', [
            'globalBrands' => ManagedBrand::globalBrandNames(),
            'countries'    => BrandAccount::COUNTRIES,
            'currencies'   => BrandAccount::CURRENCIES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:160',
            'kind'          => ['required', Rule::in(BrandAccount::KINDS)],
            'brands'        => 'required|string|max:2000',
            'country'       => ['nullable', Rule::in(array_keys(BrandAccount::COUNTRIES))],
            'region'        => ['nullable', 'string', 'max:40'],
            'currency'      => ['required', Rule::in(BrandAccount::CURRENCIES)],
            'manager_name'  => 'required|string|max:120',
            'manager_email' => 'required|email|max:200',
        ]);

        $brandNames = $this->parseBrandNames($data['brands']);
        if (! $brandNames) {
            return back()->withInput()->withErrors(['brands' => __('Enter at least one brand.')]);
        }

        $country = $data['country'] ?? null;
        $region  = ManagedBrand::normalizeRegion($data['region'] ?? null);
        if ($region !== null && ! $country) {
            return back()->withInput()->withErrors(['region' => __('Choose a country before setting a region.')]);
        }
        if ($conflicts = ManagedBrand::territoryConflicts($brandNames, $country, $region)) {
            return back()->withInput()->withErrors(['brands' => implode(' ', $conflicts)]);
        }

        $email = mb_strtolower(trim($data['manager_email']));
        if (User::where('email', $email)->exists()) {
            return back()->withInput()->withErrors(['manager_email' => __('An account with this email already exists.')]);
        }

        $account = BrandAccount::create([
            'name'               => trim($data['name']),
            'kind'               => $data['kind'],
            'levels'             => BrandAccount::defaultLevels($data['kind']),
            'country'            => $country,
            'region'             => $region,
            'currency'           => $data['currency'],
            'is_active'          => true,
            'created_by_user_id' => (string) auth()->id(),
        ]);
        foreach ($brandNames as $i => $name) {
            ManagedBrand::create(ManagedBrand::fromName($name) + ['brand_account_id' => (string) $account->_id, 'position' => $i, 'is_active' => true]);
        }
        $this->invite($account, $data['manager_name'], $email);

        AuditLogger::record('brand_account.create', target: $account, after: ['name' => $account->name, 'kind' => $account->kind, 'brands' => $brandNames, 'territory' => $account->territoryLabel(), 'currency' => $account->currencyCode()]);

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

        $globalBrands = ManagedBrand::globalBrandNames();
        $countries    = BrandAccount::COUNTRIES;
        $currencies   = BrandAccount::CURRENCIES;

        return view('admin.brand-accounts.show', compact('account', 'brands', 'managers', 'invites', 'accesses', 'companies', 'itemCounts', 'quoteCount', 'globalBrands', 'countries', 'currencies'));
    }

    public function update(string $id, Request $request)
    {
        $account = BrandAccount::findOrFail($id);
        $data = $request->validate([
            'name'     => 'required|string|max:160',
            'country'  => ['sometimes', 'nullable', Rule::in(array_keys(BrandAccount::COUNTRIES))],
            'region'   => ['sometimes', 'nullable', 'string', 'max:40'],
            'currency' => ['sometimes', Rule::in(BrandAccount::CURRENCIES)],
        ]);

        $update = ['name' => trim($data['name'])];

        // The territory card sends this marker; the inline rename does not, so
        // renaming never disturbs the territory.
        if ($request->boolean('_territory')) {
            $country = ($data['country'] ?? null) ? strtoupper($data['country']) : null;
            $region  = ManagedBrand::normalizeRegion($data['region'] ?? null);
            if ($region !== null && ! $country) {
                return back()->withErrors(['territory' => __('Choose a country before setting a region.')]);
            }
            $names = ManagedBrand::withoutGlobalScopes()->where('brand_account_id', $id)->pluck('name')->all();
            if ($conflicts = ManagedBrand::territoryConflicts($names, $country, $region, $id)) {
                return back()->withErrors(['territory' => implode(' ', $conflicts)]);
            }
            $update['country']  = $country;
            $update['region']   = $region;
            $update['currency'] = in_array($data['currency'] ?? null, BrandAccount::CURRENCIES, true) ? $data['currency'] : $account->currencyCode();
        }

        $account->update($update);

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
        if ($this->brandInAccount($id, $name)) {
            return back()->withErrors(['brand' => __('":name" is already in this account.', ['name' => $name])]);
        }
        if ($conflicts = ManagedBrand::territoryConflicts([$name], $account->country, $account->region, $id)) {
            return back()->withErrors(['brand' => reset($conflicts)]);
        }
        $count = ManagedBrand::withoutGlobalScopes()->where('brand_account_id', $id)->count();
        ManagedBrand::create(ManagedBrand::fromName($name) + ['brand_account_id' => $id, 'position' => $count, 'is_active' => true]);

        return back()->with('status', __('Brand :name added.', ['name' => $name]));
    }

    public function renameBrand(string $id, string $brandId, Request $request)
    {
        $account = BrandAccount::findOrFail($id);
        $brand = ManagedBrand::withoutGlobalScopes()->where('brand_account_id', $id)->where('_id', $brandId)->firstOrFail();
        $data = $request->validate(['name' => 'required|string|max:120']);
        $name = trim($data['name']);
        if (mb_strtolower($name) !== mb_strtolower((string) $brand->name)) {
            if ($this->brandInAccount($id, $name, $brandId)) {
                return back()->withErrors(['brand' => __('":name" is already in this account.', ['name' => $name])]);
            }
            if ($conflicts = ManagedBrand::territoryConflicts([$name], $account->country, $account->region, $id)) {
                return back()->withErrors(['brand' => reset($conflicts)]);
            }
        }
        $brand->update(['name' => $name]);
        // Dealers see the new name on their next sync.
        app(\App\Services\Brand\BrandSync::class)->syncAccount($account, notify: false);

        return back()->with('status', __('Brand renamed.'));
    }

    /**
     * Delete a brand from an account. Its own catalogue (models, variants,
     * options or engines) is removed and it is detached from every dealer
     * link. Dealers keep the copies they already received and quotes keep
     * their snapshots, so history is never affected.
     */
    public function deleteBrand(string $id, string $brandId)
    {
        $brand = ManagedBrand::withoutGlobalScopes()->where('brand_account_id', $id)->where('_id', $brandId)->firstOrFail();
        $name  = $brand->name;

        foreach ([
            \App\Models\BrandModel::class, \App\Models\BrandVariant::class, \App\Models\BrandOption::class,
            \App\Models\BrandEngine::class, \App\Models\BrandEngineAccessory::class,
        ] as $model) {
            $model::withoutGlobalScopes()->where('managed_brand_id', $brandId)->delete();
        }

        foreach (\App\Models\BrandAccess::withoutGlobalScopes()->where('brand_account_id', $id)->get() as $access) {
            $kept = array_values(array_filter((array) $access->managed_brand_ids, fn ($x) => (string) $x !== $brandId));
            if (count($kept) !== count((array) $access->managed_brand_ids)) {
                $access->update(['managed_brand_ids' => $kept]);
            }
        }

        $brand->delete();

        return back()->with('status', __('Brand :name deleted.', ['name' => $name]));
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

    /** Whether this account already carries a brand of that name (case-insensitive). */
    private function brandInAccount(string $accountId, string $name, ?string $exceptBrandId = null): bool
    {
        $key = mb_strtolower(trim($name));

        return ManagedBrand::withoutGlobalScopes()->where('brand_account_id', $accountId)
            ->get(['name', '_id'])
            ->contains(fn ($b) => (string) $b->_id !== (string) $exceptBrandId && mb_strtolower(trim((string) $b->name)) === $key);
    }
}
