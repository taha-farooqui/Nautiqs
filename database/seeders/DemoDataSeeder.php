<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Company;
use App\Models\GlobalBoatModel;
use App\Models\GlobalBoatVariant;
use App\Models\GlobalBrand;
use App\Models\GlobalOption;
use App\Models\Quote;
use App\Models\QuoteCounter;
use App\Models\User;
use App\Services\QuoteCalculator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Creates a "Demo Marine" dealership with a full workspace of realistic data
 * so the dashboard and lists show something useful right after install.
 * Idempotent — safe to re-run.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $calculator = app(QuoteCalculator::class);

        // Company
        $company = Company::updateOrCreate(
            ['name' => 'Demo Marine'],
            [
                'legal_form'           => 'SAS',
                'siren'                => '812 345 678',
                'vat_number'           => 'FR12812345678',
                'address'              => "12 Quai des Voiles\n13000 Marseille\nFrance",
                'logo_path'            => null,
                'salesperson_name'     => 'Claire Navarro',
                'salesperson_phone'    => '+33 6 12 34 56 78',
                'salesperson_email'    => 'claire@demo-marine.test',
                'default_vat_rate'     => 20.0,
                'default_margin_pct'   => 10.0,
                'default_display_mode' => 'TTC',
                'margin_presets'       => [
                    'hull' => 12.0, 'engine' => 8.0, 'options' => 15.0, 'custom_items' => 10.0,
                ],
                'status'               => 'active',
                'onboarded_at'         => now(),
            ]
        );
        $companyId = (string) $company->_id;

        // Demo user — log in as demo@nautiqs.test / password: demo123!
        User::updateOrCreate(
            ['email' => 'demo@nautiqs.test'],
            [
                'name'              => 'Claire Navarro',
                'password'          => Hash::make('demo123!'),
                'role'              => User::ROLE_TENANT_ADMIN,
                'company_id'        => $companyId,
                'email_verified_at' => now(),
            ]
        );

        // Clear existing demo clients/quotes for this company to keep the
        // seed deterministic.
        Client::withoutGlobalScopes()->where('company_id', $companyId)->delete();
        Quote::withoutGlobalScopes()->where('company_id', $companyId)->delete();
        QuoteCounter::where('company_id', $companyId)->delete();

        // Clients
        $clientsData = [
            ['Pierre',    'Dubois',    'pierre.dubois@example.com',    '+33 6 11 11 11 11', 'Martigues'],
            ['Marine',    'Laurent',   'marine.laurent@example.com',   '+33 6 22 22 22 22', 'Cassis'],
            ['Jean',      'Rousseau',  'jean.rousseau@example.com',    '+33 6 33 33 33 33', 'La Ciotat'],
            ['Sophie',    'Martin',    'sophie.martin@example.com',    '+33 6 44 44 44 44', 'Saint-Tropez'],
            ['Luc',       'Moreau',    'luc.moreau@example.com',       '+33 6 55 55 55 55', 'Bandol'],
            ['Émilie',    'Bernard',   'emilie.bernard@example.com',   '+33 6 66 66 66 66', 'Toulon'],
            ['Thomas',    'Girard',    'thomas.girard@example.com',    '+33 6 77 77 77 77', 'Hyères'],
            ['Camille',   'Lefèvre',   'camille.lefevre@example.com',  '+33 6 88 88 88 88', 'Antibes'],
            ['Nicolas',   'Fontaine',  'nicolas.fontaine@example.com', '+33 6 99 99 99 99', 'Cannes'],
            ['Julie',     'Mercier',   'julie.mercier@example.com',    '+33 6 10 10 10 10', 'Nice'],
            ['Alexandre', 'Chevalier', 'alex.chevalier@example.com',   '+33 6 20 20 20 20', 'Monaco'],
            ['Céline',    'Robert',    'celine.robert@example.com',    '+33 6 30 30 30 30', 'Marseille'],
            ['Antoine',   'Blanchard', 'antoine.blanchard@example.com','+33 6 40 40 40 40', 'Sanary'],
            ['Manon',     'Leroy',     'manon.leroy@example.com',      '+33 6 50 50 50 50', 'Porquerolles'],
            ['Hugo',      'Garnier',   'hugo.garnier@example.com',     '+33 6 60 60 60 60', 'Bormes'],
        ];

        $clients = [];
        foreach ($clientsData as [$first, $last, $email, $phone, $city]) {
            $clients[] = Client::create([
                'company_id'    => $companyId,
                'first_name'    => $first,
                'last_name'     => $last,
                'company_name'  => null,
                'email'         => $email,
                'phone'         => $phone,
                'address_line'  => random_int(1, 99) . ' rue des Navires',
                'postal_code'   => sprintf('%05d', random_int(13000, 13999)),
                'city'          => $city,
                'country'       => 'France',
                'internal_notes'=> null,
            ]);
        }

        // Quotes — generate across statuses and the last ~90 days
        $variants = GlobalBoatVariant::all()->keyBy(fn ($v) => (string) $v->_id)->all();
        if (empty($variants)) {
            $this->command?->warn('No global variants seeded — skipping demo quotes.');
            return;
        }
        $models = GlobalBoatModel::all()->keyBy(fn ($m) => (string) $m->_id)->all();
        $brands = GlobalBrand::all()->keyBy(fn ($b) => (string) $b->_id)->all();

        $statusMix = [
            Quote::STATUS_DRAFT => 6,
            Quote::STATUS_SENT  => 10,
            Quote::STATUS_WON   => 6,
            Quote::STATUS_LOST  => 3,
        ];

        $year = (int) date('Y');
        $optionsByModel = GlobalOption::all()->groupBy('model_id');

        foreach ($statusMix as $status => $count) {
            for ($i = 0; $i < $count; $i++) {
                $variant = $variants[array_rand($variants)];
                $model   = $models[$variant->model_id] ?? null;
                if (! $model) continue;
                $brand   = $brands[$model->brand_id] ?? null;
                $client  = $clients[array_rand($clients)];

                // Pick 1-3 random options for this model
                $modelOptions = $optionsByModel->get((string) $model->_id, collect());
                $chosenOptions = $modelOptions->shuffle()->take(random_int(1, min(3, $modelOptions->count())))->map(fn ($o) => [
                    'category'     => $o->category,
                    'label'        => $o->label,
                    'quantity'     => 1,
                    'unit_price'   => (float) $o->price,
                    'unit_cost'    => (float) $o->cost,
                    'currency'     => $o->currency ?? 'EUR',
                    'discount_pct' => 0,
                ])->values()->all();

                $customItems = random_int(0, 1) === 1
                    ? [[
                        'category'     => 'custom_items',
                        'label'        => 'Transport & préparation',
                        'amount'       => random_int(800, 2500),
                        'cost'         => null,
                        'discount_pct' => 0,
                    ]]
                    : [];

                $globalDiscount = $status === Quote::STATUS_WON ? random_int(0, 3) : 0;

                $totals = $calculator->compute([
                    'base_price'          => (float) $variant->base_price,
                    'base_cost'           => (float) $variant->cost,
                    'variant_currency'    => $variant->currency ?? 'EUR',
                    'exchange_rate'       => null,
                    'options'             => $chosenOptions,
                    'custom_items'        => $customItems,
                    'category_discounts'  => [],
                    'global_discount_pct' => $globalDiscount,
                    'trade_in_value'      => $status === Quote::STATUS_WON && random_int(0, 1) ? random_int(5000, 25000) : 0,
                    'vat_rate'            => 20.0,
                ], $company);

                $createdAt = now()->subDays(random_int(0, 90))->subHours(random_int(0, 23));
                $number    = QuoteCounter::nextReference($companyId, 'quote', $year);

                $quote = Quote::create([
                    'company_id' => $companyId,
                    'number'     => $number,
                    'status'     => $status,
                    'client_id'  => (string) $client->_id,

                    'client_snapshot' => [
                        'first_name'   => $client->first_name,
                        'last_name'    => $client->last_name,
                        'company_name' => $client->company_name,
                        'email'        => $client->email,
                        'phone'        => $client->phone,
                        'address_line' => $client->address_line,
                        'postal_code'  => $client->postal_code,
                        'city'         => $client->city,
                        'country'      => $client->country,
                    ],

                    'model_id'       => (string) $model->_id,
                    'model_snapshot' => [
                        'code'   => $model->code,
                        'name'   => $model->name,
                        'brand'  => $brand?->name,
                        'source' => 'global',
                    ],
                    'variant_id'       => (string) $variant->_id,
                    'variant_snapshot' => [
                        'name'       => $variant->name,
                        'base_price' => (float) $variant->base_price,
                        'cost'       => (float) $variant->cost,
                        'currency'   => $variant->currency ?? 'EUR',
                    ],

                    'included_equipment'  => $variant->included_equipment ?? [],
                    'options'             => $chosenOptions,
                    'custom_items'        => $customItems,
                    'category_discounts'  => [],
                    'global_discount_pct' => $globalDiscount,
                    'trade_in'            => $totals['trade_in_deduction'] > 0
                        ? ['brand' => 'Bayliner', 'model' => '175', 'year' => '2018', 'engine' => '135HP', 'engine_hours' => 420, 'description' => 'Well maintained', 'value' => $totals['trade_in_deduction']]
                        : null,
                    'currency'            => 'EUR',
                    'exchange_rate'       => null,
                    'exchange_rate_date'  => null,
                    'vat_rate'            => 20.0,
                    'display_mode'        => 'TTC',
                    'totals'              => $totals,
                    'internal_notes'      => null,
                    'sent_at'             => in_array($status, [Quote::STATUS_SENT, Quote::STATUS_WON, Quote::STATUS_LOST]) ? $createdAt->copy()->addHours(2) : null,
                    'won_at'              => $status === Quote::STATUS_WON ? $createdAt->copy()->addDays(5) : null,
                    'lost_at'             => $status === Quote::STATUS_LOST ? $createdAt->copy()->addDays(7) : null,
                    'order_confirmation_number' => null,
                    'order_confirmation_at'     => null,
                    'duplicated_from'     => null,
                ]);

                // Backdate created_at for dashboard charts
                $quote->created_at = $createdAt;
                $quote->updated_at = $createdAt;
                $quote->save();
            }
        }
    }
}
