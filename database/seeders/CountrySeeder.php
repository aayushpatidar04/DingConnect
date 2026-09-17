<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $countries = [
            ['India', 'IN', 'IND', '+91', 'INR', '🇮🇳'],
            ['United Kingdom', 'GB', 'GBR', '+44', 'GBP', '🇬🇧'],
        ];

        foreach ($countries as [$name, $iso, $iso3, $code, $currency, $flag]) {
            Country::create([
                'name' => $name,
                'iso_code' => $iso,
                'iso_code_3' => $iso3,
                'calling_code' => $code,
                'currency' => $currency,
                'flag_emoji' => $flag,
                'is_active' => true,
            ]);
        }
    }
}
