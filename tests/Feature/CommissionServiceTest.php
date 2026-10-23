<?php

namespace Tests\Feature;

use App\Models\AllowedNumber;
use App\Models\CommissionLevel;
use App\Models\CommissionRate;
use App\Models\Country;
use App\Models\Operator;
use App\Models\RetailerCommission;
use App\Models\RetailerCommissionProfile;
use App\Models\RetailerLevelChange;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTopup;
use App\Services\CommissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommissionServiceTest extends TestCase
{
    use RefreshDatabase;

    private Country $country;
    private Operator $operator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->country = Country::create(['name' => 'United Kingdom', 'iso_code' => 'GB', 'iso_code_3' => 'GBR', 'calling_code' => '44', 'currency' => 'GBP', 'is_active' => true]);
        $this->operator = Operator::create(['name' => 'Test Mobile', 'slug' => 'test-mobile', 'provider_code' => 'TM', 'country_id' => $this->country->id, 'is_active' => true]);
    }

    private function retailer(): User
    {
        $user = User::factory()->create();
        Wallet::create(['user_id' => $user->id, 'balance' => 0, 'currency' => 'GBP']);

        return $user;
    }

    private function sim(User $addedBy, string $mobile): void
    {
        AllowedNumber::create(['user_id' => $addedBy->id, 'mobile' => $mobile, 'serial' => '0', 'active' => true]);
    }

    private function level(string $name, int $min): CommissionLevel
    {
        return CommissionLevel::create(['name' => $name, 'min_connections' => $min, 'sort_order' => $min, 'is_active' => true]);
    }

    private function rate(CommissionLevel $level, float $min, ?float $max, float $first, float $bonus, array $steps): CommissionRate
    {
        $rate = CommissionRate::create([
            'commission_level_id' => $level->id,
            'operator_id' => $this->operator->id,
            'min_amount' => $min,
            'max_amount' => $max,
            'first_topup_amount' => $first,
            'first_bonus_amount' => $bonus,
            'is_active' => true,
        ]);

        foreach ($steps as [$from, $to, $amount]) {
            $rate->steps()->create(['from_sequence' => $from, 'to_sequence' => $to, 'amount' => $amount]);
        }

        return $rate;
    }

    private function topup(User $user, string $mobile, float $amount = 20.0): Transaction
    {
        return Transaction::create([
            'user_id' => $user->id,
            'mobile_number' => $mobile,
            'operator_id' => $this->operator->id,
            'country_id' => $this->country->id,
            'amount' => $amount,
            'currency' => 'GBP',
            'status' => 'success',
            'product_type' => 'topup',
            'gateway' => 'ding',
        ]);
    }

    private function balance(User $user): float
    {
        return (float) Wallet::where('user_id', $user->id)->value('balance');
    }

    public function test_first_then_ongoing_top_ups_pay_until_the_band_ends(): void
    {
        $service = app(CommissionService::class);
        $user = $this->retailer();
        $level = $this->level('Level A', 0);
        $this->rate($level, 0, null, 2.00, 1.00, [[2, 3, 1.00]]);
        $this->sim($user, '447700900001');
        $service->assignLevel($user, $level->id);

        foreach ([3.00, 1.00, 1.00, 0.00] as $i => $expected) {
            $commission = $service->processSuccessfulRecharge($this->topup($user, '447700900001'));
            $this->assertSame($expected, (float) $commission->commission_amount);

            if ($i === 0) {
                // first top-up: two wallet top-up entries, one commission and one bonus
                $entries = WalletTopup::where('user_id', $user->id)->orderBy('id')->get();
                $this->assertSame(['commission', 'bonus'], $entries->pluck('payment_gateway')->all());
                $this->assertSame([2.00, 1.00], $entries->map(fn ($e) => (float) $e->amount)->all());
            }
        }

        // ongoing top-ups add one commission entry each; the skipped 4th adds none
        $entries = WalletTopup::where('user_id', $user->id)->orderBy('id')->get();
        $this->assertCount(4, $entries);
        $this->assertSame(['commission', 'bonus', 'commission', 'commission'], $entries->pluck('payment_gateway')->all());
        $this->assertSame('completed', $entries->last()->status);

        $this->assertSame(5.00, $this->balance($user));
        $this->assertSame('no_ongoing_rate_for_sequence', RetailerCommission::orderByDesc('id')->first()->skip_reason);

        $profile = RetailerCommissionProfile::where('user_id', $user->id)->first();
        $this->assertSame(1, $profile->actual_connections);
    }

    public function test_amount_ranges_pick_the_matching_band(): void
    {
        $service = app(CommissionService::class);
        $user = $this->retailer();
        $level = $this->level('Level A', 0);
        $this->rate($level, 20, null, 2.00, 1.00, [[2, 5, 1.00]]);
        $this->rate($level, 10, 19.99, 1.50, 0.50, [[2, 5, 0.50]]);
        $this->sim($user, '447700900002');
        $this->sim($user, '447700900003');
        $this->sim($user, '447700900004');
        $service->assignLevel($user, $level->id);

        // 1st paying recharge uses the first amounts of the £20+ band
        $this->assertSame(3.00, (float) $service->processSuccessfulRecharge($this->topup($user, '447700900002', 25))->commission_amount);
        // 2nd uses the ongoing amount of the band that matches this amount (£10 band)
        $this->assertSame(0.50, (float) $service->processSuccessfulRecharge($this->topup($user, '447700900003', 10))->commission_amount);
        // below £10 there is no band, so nothing is paid and it does not use up a position
        $this->assertSame('no_rate_for_operator_or_amount', $service->processSuccessfulRecharge($this->topup($user, '447700900004', 5))->skip_reason);

        $profile = RetailerCommissionProfile::where('user_id', $user->id)->first();
        $this->assertSame(2, $profile->commission_topups_count);
        $this->assertSame(3, $profile->actual_connections); // every new number is a connection
    }

    public function test_first_and_ongoing_are_counted_per_retailer_not_per_number(): void
    {
        $service = app(CommissionService::class);
        $user = $this->retailer();
        $level = $this->level('Level A', 0);
        $this->rate($level, 0, null, 2.00, 1.00, [[2, 2, 1.00], [3, 5, 0.50]]);
        $this->sim($user, '447700900021');
        $this->sim($user, '447700900022');
        $service->assignLevel($user, $level->id);

        // 1st recharge, number X: first top-up
        $one = $service->processSuccessfulRecharge($this->topup($user, '447700900021'));
        // 2nd recharge, same number X: ongoing #2
        $two = $service->processSuccessfulRecharge($this->topup($user, '447700900021'));
        // 3rd recharge, new number Y: ongoing #3
        $three = $service->processSuccessfulRecharge($this->topup($user, '447700900022'));

        $this->assertSame(['first', 'ongoing', 'ongoing'], [$one->type, $two->type, $three->type]);
        $this->assertSame([1, 2, 3], [$one->topup_sequence, $two->topup_sequence, $three->topup_sequence]);
        $this->assertSame([3.00, 1.00, 0.50], [(float) $one->commission_amount, (float) $two->commission_amount, (float) $three->commission_amount]);

        $profile = RetailerCommissionProfile::where('user_id', $user->id)->first();
        $this->assertSame(2, $profile->actual_connections); // X and Y, not three
        $this->assertSame(3, $profile->commission_topups_count);
    }

    public function test_a_level_change_restarts_the_first_top_up_position(): void
    {
        $service = app(CommissionService::class);
        $user = $this->retailer();
        $a = $this->level('Level A', 0);
        $b = $this->level('Level B', 50);
        $this->rate($a, 0, null, 1.00, 0, [[2, null, 0.25]]);
        $this->rate($b, 0, null, 4.00, 0, [[2, null, 2.00]]);
        $this->sim($user, '447700900031');
        $service->assignLevel($user, $a->id);

        $this->assertSame(1.00, (float) $service->processSuccessfulRecharge($this->topup($user, '447700900031'))->commission_amount);
        $this->assertSame(0.25, (float) $service->processSuccessfulRecharge($this->topup($user, '447700900031'))->commission_amount);

        $profile = $service->assignLevel($user, $b->id);
        $this->assertSame(0, $profile->commission_topups_count);

        // first top-up again, now at Level B rates
        $this->assertSame(4.00, (float) $service->processSuccessfulRecharge($this->topup($user, '447700900031'))->commission_amount);

        // an admin can also set the position by hand
        $profile = $service->assignLevel($user, $b->id, null, null, true, null, 7);
        $this->assertSame(7, $profile->commission_topups_count);
        $this->assertSame(2.00, (float) $service->processSuccessfulRecharge($this->topup($user, '447700900031'))->commission_amount);
    }

    public function test_retailer_without_a_level_earns_nothing_but_connections_are_counted(): void
    {
        $service = app(CommissionService::class);
        $user = $this->retailer();
        $this->sim($user, '447700900005');

        $commission = $service->processSuccessfulRecharge($this->topup($user, '447700900005'));

        $this->assertSame('no_level', $commission->skip_reason);
        $this->assertSame(0.00, $this->balance($user));
        $profile = RetailerCommissionProfile::where('user_id', $user->id)->first();
        $this->assertSame(1, $profile->actual_connections);
        $this->assertSame(0, $profile->commission_topups_count); // nothing paid, no position used
    }

    public function test_same_transaction_is_never_paid_twice(): void
    {
        $service = app(CommissionService::class);
        $user = $this->retailer();
        $level = $this->level('Level A', 0);
        $this->rate($level, 0, null, 2.00, 0, []);
        $this->sim($user, '447700900006');
        $service->assignLevel($user, $level->id);
        $transaction = $this->topup($user, '447700900006');

        $service->processSuccessfulRecharge($transaction);
        $this->assertNull($service->processSuccessfulRecharge($transaction));

        $this->assertSame(1, RetailerCommission::count());
        $this->assertSame(2.00, $this->balance($user));
    }

    public function test_a_first_top_up_without_bonus_creates_a_single_entry(): void
    {
        $service = app(CommissionService::class);
        $user = $this->retailer();
        $level = $this->level('Level A', 0);
        $this->rate($level, 0, null, 2.00, 0, []);
        $this->sim($user, '447700900008');
        $service->assignLevel($user, $level->id);
        $transaction = $this->topup($user, '447700900008');

        $service->processSuccessfulRecharge($transaction);
        $service->processSuccessfulRecharge($transaction);

        $this->assertSame(['commission'], WalletTopup::where('user_id', $user->id)->pluck('payment_gateway')->all());
        $this->assertSame(2.00, $this->balance($user));
    }

    public function test_only_the_first_retailer_to_recharge_a_number_gets_the_connection(): void
    {
        $service = app(CommissionService::class);
        $owner = $this->retailer();
        $other = $this->retailer();
        $level = $this->level('Level A', 0);
        $this->rate($level, 0, null, 2.00, 0, [[2, null, 1.00]]);
        $this->sim($owner, '447700900007');
        $service->assignLevel($owner, $level->id);
        $service->assignLevel($other, $level->id);

        $service->processSuccessfulRecharge($this->topup($owner, '447700900007'));
        $second = $service->processSuccessfulRecharge($this->topup($other, '447700900007'));

        // the other retailer still earns on their own recharge (their first one)...
        $this->assertSame(2.00, (float) $second->commission_amount);
        $this->assertSame('first', $second->type);
        $this->assertSame(2.00, $this->balance($other));

        // ...but only the first retailer got the connection
        $this->assertSame(1, RetailerCommissionProfile::where('user_id', $owner->id)->value('actual_connections'));
        $this->assertSame(0, RetailerCommissionProfile::where('user_id', $other->id)->value('actual_connections'));
    }

    public function test_changing_level_resets_level_connections_but_never_actual_connections(): void
    {
        $service = app(CommissionService::class);
        $user = $this->retailer();
        $low = $this->level('Level 2', 11);
        $high = $this->level('Level 3', 25);

        $profile = $service->assignLevel($user, $high->id, null, null, true, 4);
        $this->assertSame(25, $profile->level_connections);
        $this->assertSame(4, $profile->actual_connections);

        $profile = $service->assignLevel($user, $low->id);
        $this->assertSame(11, $profile->level_connections);
        $this->assertSame(4, $profile->actual_connections);

        $profile = $service->assignLevel($user, null);
        $this->assertNull($profile->commission_level_id);
        $this->assertFalse($profile->auto_level_enabled);
        $this->assertSame(4, $profile->actual_connections);

        $this->assertSame(
            ['assigned', 'downgrade', 'removed'],
            RetailerLevelChange::where('user_id', $user->id)->orderBy('id')->pluck('change_type')->all()
        );
    }

    public function test_new_connections_upgrade_the_level_automatically_unless_locked(): void
    {
        $service = app(CommissionService::class);
        $auto = $this->retailer();
        $locked = $this->retailer();
        $a = $this->level('Level A', 0);
        $b = $this->level('Level B', 2);
        $this->rate($a, 0, null, 1.00, 0, []);
        $this->rate($b, 0, null, 2.00, 0, []);

        $service->assignLevel($auto, $a->id);
        $service->assignLevel($locked, $a->id, null, null, false);

        foreach (['447700900011', '447700900012'] as $mobile) {
            $this->sim($auto, $mobile);
            $service->processSuccessfulRecharge($this->topup($auto, $mobile));
        }
        foreach (['447700900013', '447700900014'] as $mobile) {
            $this->sim($locked, $mobile);
            $service->processSuccessfulRecharge($this->topup($locked, $mobile));
        }

        $this->assertSame($b->id, RetailerCommissionProfile::where('user_id', $auto->id)->value('commission_level_id'));
        $this->assertSame($a->id, RetailerCommissionProfile::where('user_id', $locked->id)->value('commission_level_id'));
        $this->assertTrue(RetailerLevelChange::where('user_id', $auto->id)->where('change_type', 'auto_upgrade')->exists());

        // the upgrade restarts the position; the locked retailer keeps theirs
        $this->assertSame(0, RetailerCommissionProfile::where('user_id', $auto->id)->value('commission_topups_count'));
        $this->assertSame(1, RetailerCommissionProfile::where('user_id', $locked->id)->value('commission_topups_count'));
    }
}
