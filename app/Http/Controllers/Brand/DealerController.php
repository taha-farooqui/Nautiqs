<?php

namespace App\Http\Controllers\Brand;

use App\Http\Controllers\Controller;
use App\Models\BrandAccess;
use App\Models\BrandAccount;
use App\Models\Company;
use App\Models\ManagedBrand;
use App\Models\Quote;
use App\Services\Brand\BrandInvitations;
use App\Services\Brand\BrandSync;
use Illuminate\Http\Request;

/**
 * The dealers a brand shares its catalogue with: invite by email (with a
 * price level and the brands to share), change level or brands, revoke.
 */
class DealerController extends Controller
{
    private const TABS = [
        'all'     => null,
        'active'  => [BrandAccess::STATUS_ACTIVE],
        'waiting' => [BrandAccess::STATUS_PENDING],
        'invited' => [BrandAccess::STATUS_INVITED],
        'ended'   => [BrandAccess::STATUS_DECLINED, BrandAccess::STATUS_REVOKED, BrandAccess::STATUS_DISCONNECTED],
    ];

    public function index(Request $request)
    {
        $account = $this->account();
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'all';

        $all = BrandAccess::where('brand_account_id', (string) $account->_id)->orderBy('invited_at', 'desc')->get();
        $counts = collect(self::TABS)->map(fn ($st) => $st ? $all->whereIn('status', $st)->count() : $all->count());
        $accesses = self::TABS[$tab] ? $all->whereIn('status', self::TABS[$tab])->values() : $all;

        $companies = Company::whereIn('_id', $all->pluck('company_id')->filter()->unique()->values()->all())->get()
            ->keyBy(fn ($c) => (string) $c->_id);
        $quoteCounts = Quote::withoutGlobalScopes()->where('brand_account_ids', (string) $account->_id)
            ->get(['company_id'])->countBy(fn ($q) => (string) $q->company_id);

        return view('brand.dealers', [
            'account' => $account, 'tab' => $tab, 'counts' => $counts, 'accesses' => $accesses,
            'companies' => $companies, 'quoteCounts' => $quoteCounts,
            'brands' => ManagedBrand::orderBy('position')->orderBy('name')->get(),
            'levels' => $account->levelsResolved(),
        ]);
    }

    public function invite(Request $request, BrandInvitations $invitations)
    {
        $account = $this->account();
        $data = $request->validate([
            'emails'      => 'required|string|max:5000',
            'level'       => 'required|integer|min:1|max:' . BrandAccount::LEVEL_COUNT,
            'brand_ids'   => 'required|array|min:1',
            'brand_ids.*' => 'string',
            'message'     => 'nullable|string|max:1000',
        ]);
        $brandIds = $this->ownBrandIds($data['brand_ids']);
        if (! $brandIds) {
            return back()->withInput()->withErrors(['brand_ids' => __('Choose at least one brand to share.')]);
        }

        $emails = collect(preg_split('/[\s,;]+/', $data['emails']))->map(fn ($e) => mb_strtolower(trim($e)))->filter()->unique()->values();
        $sent = []; $skipped = [];
        foreach ($emails as $email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped[] = __(':email is not a valid email.', ['email' => $email]);
                continue;
            }
            [$res, $msg] = $invitations->invite($account, $email, (int) $data['level'], $brandIds, $data['message'] ?? null);
            if ($res === 'sent') {
                $sent[] = $msg;
            } else {
                $skipped[] = $msg;
            }
        }

        $back = redirect()->route('brand.dealers.index');
        if ($sent) {
            $back = $back->with('status', trans_choice('{1}Invitation sent to :list.|[2,*]:count invitations sent: :list.', count($sent), ['count' => count($sent), 'list' => implode(', ', $sent)]));
        }
        if ($skipped) {
            $back = $back->withErrors($skipped);
        }

        return $back;
    }

    public function update(string $id, Request $request)
    {
        $account = $this->account();
        $access = $this->access($id);
        $data = $request->validate([
            'level'       => 'required|integer|min:1|max:' . BrandAccount::LEVEL_COUNT,
            'brand_ids'   => 'nullable|array',
            'brand_ids.*' => 'string',
        ]);
        $brandIds = array_key_exists('brand_ids', $data) ? $this->ownBrandIds($data['brand_ids'] ?? []) : $access->brandIds();
        if (! $brandIds) {
            return back()->withErrors(['brand_ids' => __('Share at least one brand, or revoke the dealer.')]);
        }

        $access->update(['level' => (int) $data['level'], 'managed_brand_ids' => $brandIds]);
        if ($access->isActive()) {
            app(BrandSync::class)->syncAccess($access, $account);
        }

        return back()->with('status', __('Dealer updated.'));
    }

    public function revoke(string $id)
    {
        $account = $this->account();
        $access = $this->access($id);
        $wasActive = $access->isActive();

        $access->update(['status' => BrandAccess::STATUS_REVOKED, 'ended_at' => now()]);
        if ($wasActive) {
            $sync = app(BrandSync::class);
            $sync->detachAccess($access);
            $sync->notifyDealer((string) $access->company_id, 'brand.revoked',
                __(':brand ended the catalogue sharing', ['brand' => $account->name]),
                __('Its boats are no longer offered in your quotes. Quotes already made are unchanged.'),
                route('partner-brands.index'));
        }

        return back()->with('status', $wasActive ? __('Access revoked. The catalogue is hidden from this dealer.') : __('Invitation cancelled.'));
    }

    public function resend(string $id, BrandInvitations $invitations)
    {
        $access = $this->access($id);
        abort_unless($access->isOpen(), 404);
        $access->update(['invited_at' => now()]);
        $invitations->sendEmail($access, $this->account());

        return back()->with('status', __('Invitation re-sent to :email.', ['email' => $access->email]));
    }

    /* ------------------------------------------------------------ helpers */

    private function account(): BrandAccount
    {
        return auth()->user()->brandAccount;
    }

    private function access(string $id): BrandAccess
    {
        return BrandAccess::where('brand_account_id', (string) $this->account()->_id)->where('_id', $id)->firstOrFail();
    }

    /** Keep only ids of this account's brands. */
    private function ownBrandIds(array $ids): array
    {
        $own = ManagedBrand::all()->map(fn ($b) => (string) $b->_id)->all();

        return array_values(array_intersect(array_unique(array_map('strval', $ids)), $own));
    }
}
