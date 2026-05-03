<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;

/**
 * Spec §17.1 (Company Profile & Legal Details), §17.2 (Salesperson),
 * §17.3 (Defaults), §17.4 (Margin Presets). One page, grouped sections.
 */
class CompanySettingsController extends Controller
{
    public function edit()
    {
        $company = Company::findOrFail(auth()->user()->company_id);
        return view('company.settings', compact('company'));
    }

    public function update(Request $request)
    {
        $company = Company::findOrFail(auth()->user()->company_id);

        $validated = $request->validate([
            // §17.1
            'name'              => ['required', 'string', 'max:150'],
            'legal_form'        => ['nullable', 'string', 'max:50'],
            'siren'             => ['nullable', 'string', 'max:50'],
            'vat_number'        => ['nullable', 'string', 'max:50'],
            'address'           => ['nullable', 'string', 'max:500'],
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
        ]);

        $company->update($validated);

        return redirect()->route('company.settings')->with('status', 'Company settings saved.');
    }
}
