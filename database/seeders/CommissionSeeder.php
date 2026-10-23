<?php

namespace Database\Seeders;

use App\Models\CommissionLevel;
use App\Models\CommissionRate;
use App\Models\Operator;
use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Starting levels and rates. Everything here can be edited afterwards in Admin > Commission.
 * Levels 2-4 follow the Core Retail commission-scheme screenshots; Level 1 uses placeholder values.
 * Safe to run again: a level that already has rates is left untouched.
 */
class CommissionSeeder extends Seeder
{
    /** Operator name patterns (LIKE) for the providers in the scheme. */
    private const PROVIDERS = [
        '3' => ['3', '3 United%'],
        'O2' => ['O2', 'O2 %'],
        'Vodafone' => ['Vodafone', 'Vodafone %'],
        'EE' => ['EE', 'EE %'],
        'Voxi' => ['Voxi%'],
        'Smarty' => ['Smarty%'],
        'giffgaff' => ['giffgaff%'],
        'Lebara' => ['Lebara%'],
    ];

    public function run(): void
    {
        Setting::firstOrCreate(['key' => 'commission_enabled'], [
            'value' => 'true',
            'type' => 'boolean',
            'group' => 'commission',
            'description' => 'Pay cashback to retailers on successful recharges',
        ]);

        foreach ($this->scheme() as $order => $def) {
            $level = CommissionLevel::firstOrCreate(
                ['name' => $def['name']],
                ['min_connections' => $def['min'], 'sort_order' => $order, 'is_active' => true]
            );

            if ($level->rates()->exists()) {
                continue;
            }

            foreach ($def['rates'] as $provider => $bands) {
                $operators = $this->operatorsFor($provider);

                if ($operators->isEmpty()) {
                    $this->command?->warn("No operator found for {$provider}; skipped. Sync operators, then add its rates in Admin > Commission.");
                    continue;
                }

                foreach ($operators as $operator) {
                    foreach ($bands as [$minAmount, $maxAmount, $topup, $bonus, $from, $to, $ongoing]) {
                        $rate = CommissionRate::create([
                            'commission_level_id' => $level->id,
                            'operator_id' => $operator->id,
                            'min_amount' => $minAmount,
                            'max_amount' => $maxAmount,
                            'first_topup_amount' => $topup,
                            'first_bonus_amount' => $bonus,
                            'is_active' => true,
                        ]);

                        $rate->steps()->create([
                            'from_sequence' => $from,
                            'to_sequence' => $to,
                            'amount' => $ongoing,
                        ]);
                    }
                }
            }
        }
    }

    private function operatorsFor(string $provider)
    {
        $patterns = self::PROVIDERS[$provider];

        return Operator::whereHas('country', fn ($q) => $q->where('iso_code', 'GB'))
            ->where(function ($q) use ($patterns) {
                foreach ($patterns as $pattern) {
                    $q->orWhere('name', 'like', $pattern);
                }
            })
            ->get();
    }

    /**
     * Band format: [min amount, max amount|null, first top-up, first bonus, ongoing from, ongoing to, ongoing amount]
     */
    private function scheme(): array
    {
        $big = fn (array $a20, array $a10) => [
            [20, null, ...$a20],
            [10, 19.99, ...$a10],
        ];

        return [
            [
                'name' => 'Level 1',
                'min' => 0,
                'rates' => [
                    '3' => $big([1.00, 0.50, 2, 10, 0.50], [0.75, 0.25, 2, 10, 0.50]),
                    'O2' => $big([1.00, 0.50, 2, 10, 0.50], [0.75, 0.25, 2, 10, 0.50]),
                    'Vodafone' => $big([1.00, 0.50, 2, 10, 0.50], [0.75, 0.25, 2, 10, 0.50]),
                    'EE' => $big([1.00, 0.50, 2, 10, 0.50], [0.75, 0.25, 2, 10, 0.50]),
                    'Voxi' => [[0, null, 2.00, 0.50, 2, 6, 0.75]],
                    'Smarty' => [[0, null, 0.75, 0.25, 2, 6, 0.25]],
                    'giffgaff' => [[0, null, 0.75, 0.25, 2, 3, 0.50]],
                    'Lebara' => [[0, null, 0.50, 0.25, 2, 10, 0.25]],
                ],
            ],
            [
                'name' => 'Level 2',
                'min' => 6,
                'rates' => [
                    '3' => $big([2.00, 1.00, 2, 10, 1.00], [1.50, 0.50, 2, 10, 1.00]),
                    'O2' => $big([2.00, 1.00, 2, 10, 1.00], [1.50, 0.50, 2, 10, 1.00]),
                    'Vodafone' => $big([2.00, 1.00, 2, 10, 1.00], [1.50, 0.50, 2, 10, 1.00]),
                    'EE' => $big([2.00, 1.00, 2, 10, 1.00], [1.50, 0.50, 2, 10, 1.00]),
                    'Voxi' => [[0, null, 4.00, 1.00, 2, 6, 1.50]],
                    'Smarty' => [[0, null, 1.50, 0.50, 2, 6, 0.50]],
                    'giffgaff' => [[0, null, 1.50, 0.50, 2, 3, 1.00]],
                    'Lebara' => [[0, null, 1.00, 0.50, 2, 10, 0.50]],
                ],
            ],
            [
                'name' => 'Level 3',
                'min' => 11,
                'rates' => [
                    '3' => $big([9.00, 2.00, 2, 4, 5.00], [6.00, 1.00, 2, 4, 5.00]),
                    'O2' => $big([6.00, 2.00, 2, 5, 5.00], [5.00, 1.00, 2, 5, 5.00]),
                    'Vodafone' => $big([5.00, 2.00, 2, 6, 3.00], [4.00, 1.00, 2, 6, 3.00]),
                    'EE' => $big([9.00, 2.00, 2, 4, 3.50], [6.00, 1.00, 2, 4, 3.50]),
                    'Voxi' => [[0, null, 4.00, 1.00, 2, 6, 4.00]],
                    'Smarty' => [[0, null, 3.00, 0.50, 2, 6, 1.50]],
                    'giffgaff' => [[0, null, 3.00, 0.50, 2, 3, 1.50]],
                    'Lebara' => [[0, null, 1.50, 1.00, 2, 6, 0.75]],
                ],
            ],
            [
                'name' => 'Level 4',
                'min' => 25,
                'rates' => [
                    '3' => $big([10.00, 2.50, 2, 5, 5.00], [7.00, 1.00, 2, 5, 5.00]),
                    'O2' => $big([6.00, 2.50, 2, 5, 5.00], [5.00, 1.00, 2, 5, 5.00]),
                    'Vodafone' => $big([6.00, 2.50, 2, 6, 3.00], [5.00, 1.00, 2, 6, 3.00]),
                    'EE' => $big([10.00, 2.50, 2, 4, 3.50], [7.00, 1.00, 2, 4, 3.50]),
                    'Voxi' => [[0, null, 4.00, 1.00, 2, 6, 4.00]],
                    'Smarty' => [[0, null, 3.00, 0.50, 2, 6, 1.75]],
                    'giffgaff' => [[0, null, 3.00, 1.00, 2, 3, 2.00]],
                    'Lebara' => [[0, null, 1.50, 1.00, 2, 6, 0.75]],
                ],
            ],
        ];
    }
}
