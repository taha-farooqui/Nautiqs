<?php

namespace App\Http\Controllers;

use App\Models\BrandAccess;
use App\Models\BrandAccount;
use App\Models\CompanyBrand;
use App\Models\Engine;
use App\Models\User;
use App\Services\Brand\BrandInvitations;
use App\Services\Brand\BrandSync;
use Illuminate\Http\Request;

/**
 * The dealer's side of the brand portal: invitations to answer, brands they
 * are connected to, and manual catalogues archived when a brand connected.
 * Answering and disconnecting are for the dealership's admins.
 */
class PartnerBrandsController extends Controller
{
    public function index(BrandInvitations $invitations)
    {
        $companyId = $this->companyId();
        $invitations->claimFor(auth()->user());

        $accesses = BrandAccess::where('company_id', $companyId)
            ->whereIn('status', [BrandAccess::STATUS_PENDING, BrandAccess::STATUS_ACTIVE])
            ->orderBy('status', 'desc')->orderBy('invited_at', 'desc')->get();
        $accounts = BrandAccount::whereIn('_id', $accesses->pluck('brand_account_id')->unique()->values()->all())->get()
            ->keyBy(fn ($a) => (string) $a->_id);

        $archivedBrands  = CompanyBrand::whereNotNull('archived_by_brand_at')->where('is_active', false)->orderBy('name')->get();
        $archivedEngines = Engine::whereNotNull('archived_by_brand_at')->where('is_archived', true)->get()->groupBy('brand');

        return view('partner-brands.index', [
            'accesses' => $accesses, 'accounts' => $accounts, 'invitations' => $invitations,
            'archivedBrands' => $archivedBrands, 'archivedEngines' => $archivedEngines,
        ]);
    }

    public function show(string $id, BrandInvitations $invitations)
    {
        $invitations->claimFor(auth()->user());
        $access  = $this->access($id);
        $account = BrandAccount::findOrFail($access->brand_account_id);

        return view('partner-brands.show', [
            'access' => $access, 'account' => $account,
            'brandNames' => $invitations->brandNames($access),
            'levelName' => $account->levelName($access->level),
            'duplicates' => $access->status === BrandAccess::STATUS_PENDING ? app(BrandSync::class)->manualDuplicates($access) : [],
        ]);
    }

    public function accept(string $id, Request $request)
    {
        $this->adminOnly();
        $access = $this->access($id);
        abort_unless($access->status === BrandAccess::STATUS_PENDING, 404);
        $request->validate(['consent' => 'accepted'], ['consent.accepted' => __('Please confirm you agree to share quote statistics with this brand.')]);

        $access->update([
            'status' => BrandAccess::STATUS_ACTIVE, 'responded_at' => now(), 'consent_at' => now(),
            'responded_by_user_id' => (string) auth()->id(),
        ]);

        $sync = app(BrandSync::class);
        $archived = $request->input('duplicates', 'archive') === 'archive' ? $sync->archiveManualDuplicates($access) : 0;
        $sync->syncAccess($access);

        $account = BrandAccount::find($access->brand_account_id);
        $this->notifyBrand($access, 'brand.accepted', __(':dealer accepted your invitation', ['dealer' => auth()->user()->company?->name]));

        $msg = __(':brand is now in your catalogue, ready to quote.', ['brand' => $account?->name]);
        if ($archived) {
            $msg .= ' ' . __('Your manual copy was archived (restorable from Partner brands).');
        }

        return redirect()->route($account?->isEngine() ? 'engines.index' : 'catalogue.models')->with('status', $msg);
    }

    public function decline(string $id)
    {
        $this->adminOnly();
        $access = $this->access($id);
        abort_unless($access->status === BrandAccess::STATUS_PENDING, 404);
        $access->update(['status' => BrandAccess::STATUS_DECLINED, 'responded_at' => now(), 'responded_by_user_id' => (string) auth()->id()]);
        $this->notifyBrand($access, 'brand.declined', __(':dealer declined your invitation', ['dealer' => auth()->user()->company?->name]));

        return redirect()->route('partner-brands.index')->with('status', __('Invitation declined.'));
    }

    public function disconnect(string $id)
    {
        $this->adminOnly();
        $access = $this->access($id);
        abort_unless($access->isActive(), 404);
        $access->update(['status' => BrandAccess::STATUS_DISCONNECTED, 'ended_at' => now(), 'responded_by_user_id' => (string) auth()->id()]);
        app(BrandSync::class)->detachAccess($access);
        $this->notifyBrand($access, 'brand.disconnected', __(':dealer disconnected from your catalogue', ['dealer' => auth()->user()->company?->name]));

        return redirect()->route('partner-brands.index')->with('status', __('Disconnected. The brand\'s catalogue is hidden; your quotes are unchanged.'));
    }

    /** Bring back a manual catalogue archived when a brand connected. */
    public function restore(Request $request)
    {
        $this->adminOnly();
        $data = $request->validate(['brand_id' => 'nullable|string', 'engine_brand' => 'nullable|string']);
        if (! empty($data['brand_id'])) {
            $cb = CompanyBrand::whereNotNull('archived_by_brand_at')->where('_id', $data['brand_id'])->firstOrFail();
            $cb->update(['is_active' => true, 'archived_by_brand_at' => null]);
        }
        if (! empty($data['engine_brand'])) {
            foreach (Engine::whereNotNull('archived_by_brand_at')->where('brand', $data['engine_brand'])->get() as $e) {
                $e->update(['is_archived' => false, 'archived_by_brand_at' => null]);
            }
        }

        return back()->with('status', __('Restored.'));
    }

    /* ------------------------------------------------------------ helpers */

    private function companyId(): string
    {
        return (string) auth()->user()->company_id;
    }

    private function access(string $id): BrandAccess
    {
        return BrandAccess::where('company_id', $this->companyId())->where('_id', $id)->firstOrFail();
    }

    private function adminOnly(): void
    {
        abort_unless(auth()->user()->isTenantAdmin(), 403, __('Only an admin of your dealership can do this.'));
    }

    private function notifyBrand(BrandAccess $access, string $type, string $title): void
    {
        foreach (User::where('brand_account_id', (string) $access->brand_account_id)->get(['_id']) as $u) {
            \App\Models\Notification::create([
                'user_id' => (string) $u->_id, 'company_id' => null, 'type' => $type, 'title' => $title,
                'message' => null, 'icon' => 'ri-store-2-line', 'color' => 'primary',
                'link' => route('brand.dealers.index'), 'read_at' => null,
            ]);
        }
    }
}
