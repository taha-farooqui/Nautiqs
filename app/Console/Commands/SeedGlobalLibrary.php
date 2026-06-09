<?php

namespace App\Console\Commands;

use App\Models\GlobalEquipment;
use App\Models\GlobalOptionItem;
use Illuminate\Console\Command;

/**
 * Idempotently populate the platform-provided global library: equipment
 * and options that every dealer sees out of the box. Dealers can still add
 * their own (private to them).
 *
 * NOTE: engines are intentionally NOT seeded here. Engines are dealer-owned
 * only — each dealership adds or imports its own — so there is no global
 * engine library. (Run `engines:purge-global` once to clear any rows left
 * over from older deploys.)
 *
 * Run after every deploy — duplicates are skipped via natural-key match.
 */
class SeedGlobalLibrary extends Command
{
    protected $signature = 'library:seed';
    protected $description = 'Seed the platform-provided global equipment / options';

    public function handle(): int
    {
        $this->seedEquipment();
        $this->seedOptions();
        $this->info('Done.');
        return self::SUCCESS;
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
