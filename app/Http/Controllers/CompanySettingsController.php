<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Spec §17.1 (Company Profile & Legal Details), §17.2 (Salesperson),
 * §17.3 (Defaults), §17.4 (Margin Presets). One page, grouped sections.
 */
class CompanySettingsController extends Controller
{
    public function edit()
    {
        // Superadmins don't belong to a tenant — bounce them to the
        // platform-level settings page where the platform-wide defaults live.
        if (auth()->user()?->role === User::ROLE_SUPERADMIN) {
            return redirect()->route('admin.settings.edit');
        }
        $company = Company::findOrFail(auth()->user()->company_id);

        // Live USD→EUR reference rate (ECB daily, via FxRateService) shown
        // read-only so dealers can see what conversion quotes will use.
        $usdEur = app(\App\Services\FxRateService::class)->rate('USD', 'EUR');

        return view('company.settings', compact('company', 'usdEur'));
    }

    public function update(Request $request)
    {
        if (auth()->user()?->role === User::ROLE_SUPERADMIN) {
            return redirect()->route('admin.settings.edit');
        }
        $company = Company::findOrFail(auth()->user()->company_id);

        $validated = $request->validate([
            // §17.1
            'name'              => ['required', 'string', 'max:150'],
            'legal_form'        => ['nullable', 'string', 'max:50'],
            'siren'             => ['nullable', 'string', 'max:50'],
            'vat_number'        => ['nullable', 'string', 'max:50'],
            'address'           => ['nullable', 'string', 'max:500'],
            'logo'              => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            // §17.2
            'salesperson_name'  => ['nullable', 'string', 'max:100'],
            'salesperson_phone' => ['nullable', 'string', 'max:30'],
            'salesperson_email' => ['nullable', 'email', 'max:150'],
            // §17.3
            'default_vat_rate'     => ['required', 'numeric', 'min:0', 'max:100'],
            'default_margin_pct'   => ['required', 'numeric', 'min:0', 'max:100'],
            'default_display_mode' => ['required', 'in:HT,TTC'],
            'timezone'             => ['required', 'timezone'],
            // §17.4
            'margin_presets.hull'         => ['nullable', 'numeric', 'min:0', 'max:100'],
            'margin_presets.engine'       => ['nullable', 'numeric', 'min:0', 'max:100'],
            'margin_presets.options'      => ['nullable', 'numeric', 'min:0', 'max:100'],
            'margin_presets.custom_items' => ['nullable', 'numeric', 'min:0', 'max:100'],
            // Automatic follow-up ("Relances"). exclude_unless keeps the saved
            // delay values untouched when the feature is toggled off.
            'follow_up_enabled'     => ['nullable', 'boolean'],
            'follow_up_delay_value' => ['exclude_unless:follow_up_enabled,1', 'required', 'integer', 'min:1', 'max:365'],
            'follow_up_delay_unit'  => ['exclude_unless:follow_up_enabled,1', 'required', 'in:days,weeks,months'],
        ], [], [
            'logo'                  => __('Logo'),
            'follow_up_delay_value' => __('follow-up delay'),
            'follow_up_delay_unit'  => __('follow-up unit'),
        ]);

        // Dealership logo: uploaded file replaces (and deletes) the previous
        // one. Stored on the public disk so the settings page can preview it;
        // PDFs read the file directly. Removal is instant via removeLogo().
        unset($validated['logo']);

        if ($request->hasFile('logo')) {
            if ($company->logo_path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($company->logo_path);
            }
            $validated['logo_path'] = $request->file('logo')->store('company-logos', 'public');
        }

        // Watermark for the auto follow-up: on the OFF→ON transition, only
        // quotes sent from this moment on become eligible — enabling the
        // feature must never blast follow-ups for a backlog of old quotes.
        if (($validated['follow_up_enabled'] ?? false) && ! $company->follow_up_enabled) {
            $validated['follow_up_enabled_at'] = now();
        }

        $company->update($validated);

        return redirect()->route('company.settings')->with('status', __('Company settings saved.'));
    }

    /**
     * Instantly remove the dealership logo (its own one-click button on the
     * settings page — no "Save changes" round-trip needed).
     */
    public function removeLogo()
    {
        if (auth()->user()?->role === User::ROLE_SUPERADMIN) {
            return redirect()->route('admin.settings.edit');
        }
        $company = Company::findOrFail(auth()->user()->company_id);

        if ($company->logo_path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($company->logo_path);
            $company->update(['logo_path' => null]);
        }

        return redirect()->route('company.settings')->with('status', __('Logo removed.'));
    }
}
