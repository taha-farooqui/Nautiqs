<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;

/**
 * Creates a fresh Company for a newly registered user with sensible
 * defaults so they have a functional workspace from day one (spec §5
 * onboarding). The user can then edit company details via /settings/company.
 */
class CompanyProvisioner
{
    public function forNewUser(User $user): Company
    {
        $company = Company::create([
            'name'                 => $user->name . "'s Dealership",
            'legal_form'           => null,
            'siren'                => null,
            'vat_number'           => null,
            'address'              => null,
            'logo_path'            => null,
            'salesperson_name'     => $user->name,
            'salesperson_phone'    => null,
            'salesperson_email'    => $user->email,
            'default_vat_rate'     => 20.0,
            'default_margin_pct'   => 10.0,
            'default_display_mode' => 'TTC',
            'margin_presets'       => [
                'hull'         => 12.0,
                'engine'       => 8.0,
                'options'      => 15.0,
                'custom_items' => 10.0,
            ],
            'status'               => 'active',
            'onboarded_at'         => null,
        ]);

        $user->company_id = (string) $company->_id;
        $user->save();

        return $company;
    }
}
