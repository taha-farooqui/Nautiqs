<?php

namespace App\Services\Brand;

use App\Mail\BrandDealerInvitation;
use App\Models\BrandAccess;
use App\Models\BrandAccount;
use App\Models\ManagedBrand;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Brand → dealer invitations: who they reach, and the moment an invitation
 * sent to an unknown email finds its dealership.
 */
class BrandInvitations
{
    /**
     * Invite one email. Returns [status, message]: 'sent' | 'skipped'.
     */
    public function invite(BrandAccount $account, string $email, int $level, array $brandIds, ?string $message): array
    {
        $email = mb_strtolower(trim($email));
        $user  = User::where('email', $email)->first();

        if ($user && ! $user->belongsToTenant()) {
            return ['skipped', __(':email is not a dealer account.', ['email' => $email])];
        }
        $companyId = $user?->company_id ? (string) $user->company_id : null;

        $q = BrandAccess::where('brand_account_id', (string) $account->_id);
        $existing = $companyId
            ? (clone $q)->where(fn ($w) => $w->where('company_id', $companyId)->orWhere('email', $email))->orderBy('invited_at', 'desc')->first()
            : (clone $q)->where('email', $email)->orderBy('invited_at', 'desc')->first();

        if ($existing && ($existing->isActive() || $existing->isOpen())) {
            return ['skipped', $existing->isActive()
                ? __(':email is already connected.', ['email' => $email])
                : __(':email already has a pending invitation.', ['email' => $email])];
        }

        $fields = [
            'brand_account_id'  => (string) $account->_id,
            'email'             => $email,
            'company_id'        => $companyId,
            'status'            => $companyId ? BrandAccess::STATUS_PENDING : BrandAccess::STATUS_INVITED,
            'level'             => $level,
            'managed_brand_ids' => array_values($brandIds),
            'message'           => $message ?: null,
            'invited_by_user_id'=> (string) auth()->id(),
            'invited_by_name'   => auth()->user()?->name,
            'invited_at'        => now(),
            'responded_at'      => null,
            'responded_by_user_id' => null,
            'consent_at'        => null,
            'ended_at'          => null,
        ];
        // Invited again after a decline or an end: reuse the record so the
        // dealer's history stays in one place.
        $access = $existing ? tap($existing)->update($fields) : BrandAccess::create($fields);

        $this->sendEmail($access, $account);
        if ($companyId) {
            $this->notifyInvitation($access, $account);
        }

        return ['sent', $email];
    }

    public function sendEmail(BrandAccess $access, BrandAccount $account): void
    {
        try {
            Mail::to($access->email)->send(new BrandDealerInvitation($access, $account, $this->brandNames($access)));
        } catch (\Throwable $e) {
            Log::warning('Brand dealer invitation email failed', ['access_id' => (string) $access->_id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * An invitation sent to an email before it had a dealership: once that
     * email signs in as a dealer, the invitation becomes theirs to answer.
     * Cheap enough to run on every page load (one indexed lookup).
     */
    public function claimFor(User $user): void
    {
        if (! $user->belongsToTenant() || ! $user->company_id) return;

        $open = BrandAccess::where('email', mb_strtolower((string) $user->email))
            ->whereNull('company_id')->where('status', BrandAccess::STATUS_INVITED)->get();
        foreach ($open as $access) {
            $access->update(['company_id' => (string) $user->company_id, 'status' => BrandAccess::STATUS_PENDING]);
            if ($account = BrandAccount::find($access->brand_account_id)) {
                $this->notifyInvitation($access, $account);
            }
        }
    }

    /** Pending invitations for a dealership (answered by its admins). */
    public function pendingFor(string $companyId)
    {
        return BrandAccess::where('company_id', $companyId)->where('status', BrandAccess::STATUS_PENDING)->get();
    }

    public function brandNames(BrandAccess $access): array
    {
        return ManagedBrand::withoutGlobalScopes()->where('brand_account_id', (string) $access->brand_account_id)
            ->orderBy('position')->orderBy('name')->get()
            ->filter(fn ($b) => in_array((string) $b->_id, $access->brandIds(), true))
            ->pluck('name')->values()->all();
    }

    private function notifyInvitation(BrandAccess $access, BrandAccount $account): void
    {
        app(BrandSync::class)->notifyDealer((string) $access->company_id, 'brand.invitation',
            __(':brand wants to share its catalogue with you', ['brand' => $account->name]),
            implode(', ', $this->brandNames($access)),
            route('partner-brands.show', $access->_id));
    }
}
