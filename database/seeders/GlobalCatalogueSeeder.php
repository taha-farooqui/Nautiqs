<?php

namespace Database\Seeders;

use App\Models\GlobalBoatModel;
use App\Models\GlobalBoatVariant;
use App\Models\GlobalBrand;
use App\Models\GlobalOption;
use Illuminate\Database\Seeder;

/**
 * Seeds the platform-level catalogue (§3 + §4.1) with three brands, six models
 * and representative variants/options so the quote builder has real data to
 * work with. Idempotent — safe to re-run.
 */
class GlobalCatalogueSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            [
                'brand' => 'Brig',
                'models' => [
                    ['code' => 'EAGLE10', 'name' => 'Eagle 10', 'margin' => 12, 'variants' => [
                        ['name' => 'Eagle 10 — 250HP',   'price' => 68900,  'cost' => 52000, 'currency' => 'EUR',
                         'equipment' => ['Bimini top', 'Sun deck cushions', 'Stainless bow ladder', 'Navigation lights']],
                        ['name' => 'Eagle 10 — 2x 200HP','price' => 89500,  'cost' => 68000, 'currency' => 'EUR',
                         'equipment' => ['Bimini top', 'Sun deck cushions', 'Stainless bow ladder', 'Navigation lights', 'Dual engine controls']],
                    ], 'options' => [
                        ['cat' => 'Electronics', 'label' => 'Garmin GPS chartplotter 9"', 'price' => 2450, 'cost' => 1600],
                        ['cat' => 'Electronics', 'label' => 'VHF radio fixed installation', 'price' => 680, 'cost' => 420],
                        ['cat' => 'Comfort',     'label' => 'Teak deck finish',              'price' => 5200, 'cost' => 3200],
                        ['cat' => 'Comfort',     'label' => 'Refrigerator 40L',              'price' => 980,  'cost' => 620],
                        ['cat' => 'Safety',      'label' => 'Fire suppression system',       'price' => 1450, 'cost' => 900],
                    ]],
                    ['code' => 'FALCON570','name' => 'Falcon 570', 'margin' => 14, 'variants' => [
                        ['name' => 'Falcon 570 — 100HP', 'price' => 28900, 'cost' => 21000, 'currency' => 'EUR',
                         'equipment' => ['Storage lockers', 'Bow roller', 'Nav lights']],
                    ], 'options' => [
                        ['cat' => 'Electronics', 'label' => 'Sonar / fishfinder',  'price' => 890, 'cost' => 520],
                        ['cat' => 'Comfort',     'label' => 'Bow cushion set',     'price' => 620, 'cost' => 380],
                    ]],
                ],
            ],
            [
                'brand' => 'Jeanneau',
                'models' => [
                    ['code' => 'CC75', 'name' => 'Cap Camarat 7.5', 'margin' => 11, 'variants' => [
                        ['name' => 'Cap Camarat 7.5 WA — 250HP',  'price' => 112900, 'cost' => 89000, 'currency' => 'EUR',
                         'equipment' => ['Sun bed conversion', 'Bimini', 'Cockpit shower', 'Hot water']],
                        ['name' => 'Cap Camarat 7.5 BR — 2x 150HP','price' => 128500, 'cost' => 102000, 'currency' => 'EUR',
                         'equipment' => ['Sun bed conversion', 'Bimini', 'Cockpit shower', 'Hot water', 'Dual engines']],
                    ], 'options' => [
                        ['cat' => 'CC Configuration', 'label' => 'Centre console T-top',       'price' => 4800, 'cost' => 3100],
                        ['cat' => 'CC Configuration', 'label' => 'Rod holders x6',             'price' => 520,  'cost' => 320],
                        ['cat' => 'Electronics',      'label' => 'Raymarine Axiom 12" plotter','price' => 3850, 'cost' => 2600],
                        ['cat' => 'Electronics',      'label' => 'Autopilot EV-100',            'price' => 2950, 'cost' => 2000],
                        ['cat' => 'Comfort',          'label' => 'Premium upholstery package',  'price' => 2400, 'cost' => 1500],
                    ]],
                    ['code' => 'SO410', 'name' => 'Sun Odyssey 410', 'margin' => 10, 'variants' => [
                        ['name' => 'Sun Odyssey 410 — Standard', 'price' => 289000, 'cost' => 232000, 'currency' => 'EUR',
                         'equipment' => ['Full batten mainsail', 'Self-tacking jib', 'Bow thruster', 'Solar panel 100W']],
                    ], 'options' => [
                        ['cat' => 'Sails',       'label' => 'Code 0 furling sail', 'price' => 6800, 'cost' => 4500],
                        ['cat' => 'Electronics', 'label' => 'B&G Zeus3S 12" MFD',  'price' => 4900, 'cost' => 3300],
                        ['cat' => 'Comfort',     'label' => 'Teak cockpit',        'price' => 5200, 'cost' => 3400],
                    ]],
                ],
            ],
            [
                'brand' => 'Quicksilver',
                'models' => [
                    ['code' => 'ACTIV555', 'name' => 'Activ 555', 'margin' => 15, 'variants' => [
                        ['name' => 'Activ 555 Open — 80HP',  'price' => 28150, 'cost' => 22000, 'currency' => 'EUR',
                         'equipment' => ['Sun pad', 'Swim ladder', 'Navigation lights']],
                        ['name' => 'Activ 555 Cabin — 115HP','price' => 34900, 'cost' => 27000, 'currency' => 'EUR',
                         'equipment' => ['Sun pad', 'Swim ladder', 'Navigation lights', 'Sleeping cabin']],
                    ], 'options' => [
                        ['cat' => 'Electronics', 'label' => 'Simrad GO5 plotter', 'price' => 980,  'cost' => 620],
                        ['cat' => 'Comfort',     'label' => 'Bimini top',         'price' => 590,  'cost' => 360],
                        ['cat' => 'Safety',      'label' => 'Life raft 6-person', 'price' => 1280, 'cost' => 820],
                    ]],
                    ['code' => 'SG250', 'name' => 'Seagame 250', 'margin' => 13, 'variants' => [
                        ['name' => 'Seagame 250 — 200HP',    'price' => 56400, 'cost' => 44000, 'currency' => 'EUR',
                         'equipment' => ['Stern platform', 'Bow thruster', 'Hydraulic steering']],
                    ], 'options' => [
                        ['cat' => 'Electronics', 'label' => 'Radar dome 24"',     'price' => 3400, 'cost' => 2200],
                        ['cat' => 'Comfort',     'label' => 'Cockpit refrigerator', 'price' => 1180, 'cost' => 720],
                    ]],
                ],
            ],
        ];

        foreach ($data as $brandData) {
            $brand = GlobalBrand::updateOrCreate(
                ['name' => $brandData['brand']],
                [
                    'is_active'     => true,
                    'display_order' => 0,
                    'description'   => null,
                ]
            );

            foreach ($brandData['models'] as $modelData) {
                $model = GlobalBoatModel::updateOrCreate(
                    ['code' => $modelData['code']],
                    [
                        'brand_id'           => (string) $brand->_id,
                        'name'               => $modelData['name'],
                        'default_margin_pct' => $modelData['margin'],
                        'is_archived'        => false,
                    ]
                );

                // Wipe & re-seed variants/options on each run for determinism.
                GlobalBoatVariant::where('model_id', (string) $model->_id)->delete();
                GlobalOption::where('model_id', (string) $model->_id)->delete();

                foreach ($modelData['variants'] as $v) {
                    GlobalBoatVariant::create([
                        'model_id'           => (string) $model->_id,
                        'name'               => $v['name'],
                        'base_price'         => $v['price'],
                        'cost'               => $v['cost'],
                        'currency'           => $v['currency'],
                        'included_equipment' => collect($v['equipment'])->map(fn ($e) => ['label' => $e, 'type' => 'standard'])->all(),
                        'is_archived'        => false,
                    ]);
                }

                foreach ($modelData['options'] as $i => $o) {
                    GlobalOption::create([
                        'model_id'    => (string) $model->_id,
                        'category'    => $o['cat'],
                        'label'       => $o['label'],
                        'price'       => $o['price'],
                        'cost'        => $o['cost'],
                        'currency'    => 'EUR',
                        'position'    => $i,
                        'is_archived' => false,
                    ]);
                }
            }
        }
    }
}
