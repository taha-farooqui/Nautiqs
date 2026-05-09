<?php

namespace App\Console\Commands;

use App\Models\GlobalEngine;
use App\Models\GlobalEquipment;
use App\Models\GlobalOptionItem;
use Illuminate\Console\Command;

/**
 * Idempotently populate the platform-provided global library: engines,
 * equipment, and options that every dealer sees out of the box. Dealers
 * can still add their own (private to them); the boat-editor pickers
 * merge both tiers into one list.
 *
 * Run after every deploy — duplicates are skipped via natural-key match.
 */
class SeedGlobalLibrary extends Command
{
    protected $signature = 'library:seed';
    protected $description = 'Seed the platform-provided global engines / equipment / options';

    public function handle(): int
    {
        $this->seedEngines();
        $this->seedEquipment();
        $this->seedOptions();
        $this->info('Done.');
        return self::SUCCESS;
    }

    private function seedEngines(): void
    {
        $rows = [
            // Suzuki outboards
            ['Suzuki','DF140B TL/TX',140,'petrol','In-line 4, 4-stroke',14375,20],
            ['Suzuki','DF150A TL/TX',150,'petrol','In-line 4, 4-stroke',15862.50,20],
            ['Suzuki','DF175A TL/TX',175,'petrol','In-line 4, 4-stroke',17254.17,20],
            ['Suzuki','DF200A TL/TX',200,'petrol','V6, 4-stroke',18212.50,20],
            ['Suzuki','DF200AP L/X', 200,'petrol','V6, 4-stroke, dual prop',19779.17,20],
            ['Suzuki','DF250 TX/TXX',250,'petrol','V6, 4-stroke',22391.67,20],
            ['Suzuki','DF300AP X/XX',300,'petrol','V6, 4-stroke, dual prop',24575,20],
            ['Suzuki','DF350A X/XX', 350,'petrol','V6, 4-stroke, dual prop',30408.33,20],
            // Yamaha
            ['Yamaha','F100 LB',     100,'petrol','In-line 4, 1.8L',9990,20],
            ['Yamaha','F150 LB',     150,'petrol','In-line 4, 2.7L',15990,20],
            ['Yamaha','F200 XB',     200,'petrol','In-line 4, 2.8L',19490,20],
            ['Yamaha','F300 XCB',    300,'petrol','V6, 4.2L',24890,20],
            ['Yamaha','F425 XTO',    425,'petrol','V8, 5.6L offshore',39990,20],
            // Mercury Verado
            ['Mercury','Verado 200 L',200,'petrol','In-line 4, 2.0L SC',18290,20],
            ['Mercury','Verado 250 L',250,'petrol','V8, 4.6L',22390,20],
            ['Mercury','Verado 350 XL',350,'petrol','V10, 5.7L',34750,20],
            ['Mercury','Verado 400 R XL',400,'petrol','V8 4.6L race',41500,20],
            // Honda
            ['Honda','BF150 LU',     150,'petrol','In-line 4, 2.4L',14990,20],
            ['Honda','BF250 XU',     250,'petrol','V6, 3.6L',23250,20],
            // Volvo Penta inboards (sail / cabin)
            ['Volvo Penta','D3-200 DPI',200,'diesel','Inline 5, common-rail',34900,20],
            ['Volvo Penta','D4-320 DPI',320,'diesel','Inline 4, 3.7L',48500,20],
            ['Volvo Penta','D6-440 DPI',440,'diesel','Inline 6',66500,20],
            // Tohatsu (compact outboards)
            ['Tohatsu','MFS40A',     40,'petrol','In-line 3, 0.85L',5490,20],
            ['Tohatsu','MFS115A',    115,'petrol','In-line 4, 2.1L',11890,20],
        ];
        $created = 0;
        foreach ($rows as [$brand, $code, $hp, $fuel, $desc, $price, $vat]) {
            $exists = GlobalEngine::where('brand', $brand)->where('code', $code)->exists();
            if ($exists) continue;
            GlobalEngine::create([
                'brand' => $brand, 'code' => $code, 'horsepower' => $hp, 'fuel' => $fuel,
                'description' => $desc, 'price' => $price, 'vat_rate' => $vat,
                'currency' => 'EUR', 'is_active' => true,
            ]);
            $created++;
        }
        $this->info("Engines: {$created} created (" . GlobalEngine::count() . ' total).');
    }

    private function seedEquipment(): void
    {
        $items = [
            'exterior' => [
                'Bimini top','Sun deck cushions','Sun bath cushion (front)','Sun bath cushion (rear)',
                'Cockpit table','Cockpit cushions','Stainless bow ladder','Stainless rear ladder',
                'Hardtop','Bathing platform','Cockpit teak deck','Bow rail','Cockpit lighting',
                'Folding cleats','Steering wheel cover','Bow thruster',
            ],
            'interior' => [
                'V-berth cushions','Saloon table','Galley','Refrigerator','Stove','Sink',
                'Marine head','Holding tank','Electric head','Hot water','Shower',
                'Cabin lights','12V outlets','USB outlets','Storage compartments',
            ],
            'mooring' => [
                'Anchor + chain','Anchor windlass','Mooring lines','Fenders set',
                'Stern thruster','Bow thruster','Rope locker',
            ],
            'sails' => [
                'Mainsail','Genoa','Lazy bag','Lazy jacks','Roller furling',
                'Spinnaker','Code 0','Mast steps',
            ],
            'electronics' => [
                'GPS chartplotter','Marine VHF radio','Depth sounder','Fish finder',
                'Radar','AIS receiver','Autopilot','Stereo system','Speakers','Marine antenna',
            ],
            'electrical' => [
                'Battery + switch','Dual battery system','Battery charger','Shore power inlet',
                'Solar panel','Inverter','Navigation lights','Anchor light','LED courtesy lights',
            ],
            'other' => [
                'Fire suppression system','Bilge pump','Manual bilge pump','Life jackets',
                'Flare kit','First aid kit','Boat cover','Trailer','Documentation pack',
            ],
        ];
        $created = 0;
        foreach ($items as $cat => $labels) {
            foreach ($labels as $label) {
                $exists = GlobalEquipment::where('category', $cat)->where('label', $label)->exists();
                if ($exists) continue;
                GlobalEquipment::create(['category' => $cat, 'label' => $label, 'is_active' => true]);
                $created++;
            }
        }
        $this->info("Equipment: {$created} created (" . GlobalEquipment::count() . ' total).');
    }

    private function seedOptions(): void
    {
        $items = [
            ['Comfort',       'Refrigerator 40L',                  980,  20],
            ['Comfort',       'Refrigerator 65L',                  1450, 20],
            ['Comfort',       'Air conditioning',                  4900, 20],
            ['Comfort',       'Heated cockpit cushions',           1200, 20],
            ['Comfort',       'Cockpit shower (hot/cold)',         690,  20],
            ['Electronics',   'Garmin GPS chartplotter 9"',        2450, 20],
            ['Electronics',   'Garmin GPS chartplotter 12"',       3850, 20],
            ['Electronics',   'VHF radio fixed installation',      680,  20],
            ['Electronics',   'AIS receiver/transponder',          1390, 20],
            ['Electronics',   'Autopilot — entry level',           3290, 20],
            ['Electronics',   'Stereo + 4 speakers',               980,  20],
            ['Safety',        'Fire suppression system',           1450, 20],
            ['Safety',        'Carbon monoxide detector',          290,  20],
            ['Safety',        'Life raft 4-person',                1890, 20],
            ['Safety',        'EPIRB beacon',                      650,  20],
            ['Convenience',   'Anchor windlass — 1000W',           1650, 20],
            ['Convenience',   'Bow thruster',                      4900, 20],
            ['Convenience',   'Stern thruster',                    4500, 20],
            ['Convenience',   'Hydraulic steering',                2890, 20],
            ['Lighting',      'Underwater LED lights (pair)',      1290, 20],
            ['Lighting',      'Cockpit courtesy LED kit',          390,  20],
            ['Configuration', 'Hull colour — premium',             2890, 20],
            ['Configuration', 'Antifouling — first coat',          690,  20],
            ['Configuration', 'Custom upholstery colour',          1290, 20],
        ];
        $created = 0;
        foreach ($items as [$cat, $label, $price, $vat]) {
            $exists = GlobalOptionItem::where('category', $cat)->where('label', $label)->exists();
            if ($exists) continue;
            GlobalOptionItem::create([
                'category' => $cat, 'label' => $label,
                'price' => $price, 'vat_rate' => $vat,
                'currency' => 'EUR', 'is_active' => true,
            ]);
            $created++;
        }
        $this->info("Options: {$created} created (" . GlobalOptionItem::count() . ' total).');
    }
}
