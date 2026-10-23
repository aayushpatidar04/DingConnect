<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Offer;
use App\Models\OfferRedemption;
use App\Models\Operator;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTopup;
use App\Services\OfferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OfferServiceTest extends TestCase
{
    use RefreshDatabase;

    private Country $country;
    private Operator $vodafone;
    private Operator $ee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->country = Country::create(['name' => 'United Kingdom', 'iso_code' => 'GB', 'iso_code_3' => 'GBR', 'calling_code' => '44', 'currency' => 'GBP', 'is_active' => true]);
        $this->vodafone = Operator::create(['name' => 'Vodafone', 'slug' => 'vodafone', 'provider_code' => 'VF', 'country_id' => $this->country->id, 'is_active' => true]);
        $this->ee = Operator::create(['name' => 'EE', 'slug' => 'ee', 'provider_code' => 'EE', 'country_id' => $this->country->id, 'is_active' => true]);
    }

    private function retailer(): User
    {
        $user = User::factory()->create();
        Wallet::create(['user_id' => $user->id, 'balance' => 0, 'currency' => 'GBP']);

        return $user;
    }

    private function offer(array $attributes = [], array $operators = [], array $retailers = []): Offer
    {
        $offer = Offer::create($attributes + [
            'title' => 'Test offer',
            'type' => 'percent',
            'value' => 20,
            'min_amount' => 0,
            'expires_at' => now()->addDay(),
            'audience' => $retailers ? 'selected' : 'all',
            'is_active' => true,
        ]);

        $offer->operators()->sync(array_map(fn ($o) => $o->id, $operators));
        $offer->retailers()->sync(array_map(fn ($u) => $u->id, $retailers));

        return $offer;
    }

    private function recharge(User $user, Operator $operator, float $amount = 10.0, string $status = 'processing'): Transaction
    {
        return Transaction::create([
            'user_id' => $user->id,
            'mobile_number' => '447700900001',
            'operator_id' => $operator->id,
            'country_id' => $this->country->id,
            'amount' => $amount,
            'currency' => 'GBP',
            'status' => $status,
            'product_type' => 'topup',
            'gateway' => 'ding',
        ]);
    }

    private function eligibleIds(User $user, Operator $operator, float $amount): array
    {
        return app(OfferService::class)->eligibleFor($user, $operator->id, $amount)->pluck('id')->all();
    }

    public function test_offers_are_filtered_by_provider_amount_expiry_and_audience(): void
    {
        $user = $this->retailer();
        $stranger = $this->retailer();

        $vodafoneRange = $this->offer(['min_amount' => 10, 'max_amount' => 30], [$this->vodafone]);
        $anyProvider = $this->offer(['title' => 'Any provider']);
        $eeOnly = $this->offer(['title' => 'EE only'], [$this->ee]);
        $expired = $this->offer(['title' => 'Expired', 'expires_at' => now()->subMinute()], [$this->vodafone]);
        $future = $this->offer(['title' => 'Later', 'starts_at' => now()->addHour()], [$this->vodafone]);
        $paused = $this->offer(['title' => 'Paused', 'is_active' => false], [$this->vodafone]);
        $private = $this->offer(['title' => 'Private'], [$this->vodafone], [$user]);

        // £10 on Vodafone: the range offer, the any-provider offer and the private one (user is on its list)
        $this->assertEqualsCanonicalizing(
            [$vodafoneRange->id, $anyProvider->id, $private->id],
            $this->eligibleIds($user, $this->vodafone, 10)
        );

        // someone who is not on the private list does not get it
        $this->assertEqualsCanonicalizing(
            [$vodafoneRange->id, $anyProvider->id],
            $this->eligibleIds($stranger, $this->vodafone, 10)
        );

        // £5 is below the range offer's minimum; £50 is above its maximum
        $this->assertNotContains($vodafoneRange->id, $this->eligibleIds($user, $this->vodafone, 5));
        $this->assertNotContains($vodafoneRange->id, $this->eligibleIds($user, $this->vodafone, 50));

        // EE: only the EE offer and the any-provider offer
        $this->assertEqualsCanonicalizing([$anyProvider->id, $eeOnly->id], $this->eligibleIds($user, $this->ee, 10));
    }

    public function test_benefit_is_a_percentage_with_an_optional_cap_or_a_fixed_amount(): void
    {
        $this->assertSame(2.00, $this->offer(['value' => 20])->benefitFor(10.0));
        $this->assertSame(5.00, $this->offer(['value' => 20, 'max_cashback' => 5])->benefitFor(50.0));
        $this->assertSame(1.00, $this->offer(['type' => 'fixed', 'value' => 1])->benefitFor(10.0));
    }

    public function test_cashback_is_paid_once_into_the_wallet_when_the_recharge_succeeds(): void
    {
        $service = app(OfferService::class);
        $user = $this->retailer();
        $offer = $this->offer(['value' => 20], [$this->vodafone]);

        $transaction = $this->recharge($user, $this->vodafone, 10.0);
        $service->reserve($user, $offer->id, $transaction);

        // nothing is paid before the recharge succeeds
        $this->assertNull($service->settle($transaction));
        $this->assertSame(0, WalletTopup::count());

        $transaction->update(['status' => 'success']);
        $redemption = $service->settle($transaction);

        $this->assertSame('credited', $redemption->status);
        $this->assertSame(2.00, (float) Wallet::where('user_id', $user->id)->value('balance'));

        $entry = WalletTopup::first();
        $this->assertSame('offer', $entry->payment_gateway);
        $this->assertSame('completed', $entry->status);
        $this->assertSame(2.00, (float) $entry->amount);

        // running it again pays nothing more
        $this->assertNull($service->settle($transaction));
        $this->assertSame(1, WalletTopup::count());
    }

    public function test_the_per_retailer_limit_is_enforced_and_a_failed_recharge_gives_the_use_back(): void
    {
        $service = app(OfferService::class);
        $user = $this->retailer();
        $other = $this->retailer();
        $offer = $this->offer(['per_retailer_limit' => 1], [$this->vodafone]);

        $first = $this->recharge($user, $this->vodafone);
        $service->reserve($user, $offer->id, $first);

        // used up for this retailer (the first recharge is still processing, so it counts)
        $this->assertSame([], $this->eligibleIds($user, $this->vodafone, 10));
        // but not for another retailer
        $this->assertSame([$offer->id], $this->eligibleIds($other, $this->vodafone, 10));

        try {
            $service->reserve($user, $offer->id, $this->recharge($user, $this->vodafone));
            $this->fail('A second use should have been refused.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('offer_id', $e->errors());
        }

        // the first recharge fails: the use comes back
        $first->update(['status' => 'failed']);
        $service->release($first);

        $this->assertSame([$offer->id], $this->eligibleIds($user, $this->vodafone, 10));
        $this->assertSame('void', OfferRedemption::where('transaction_id', $first->id)->value('status'));
    }

    public function test_the_total_limit_covers_all_retailers(): void
    {
        $service = app(OfferService::class);
        $a = $this->retailer();
        $b = $this->retailer();
        $offer = $this->offer(['total_limit' => 1], [$this->vodafone]);

        $service->reserve($a, $offer->id, $this->recharge($a, $this->vodafone));

        $this->assertSame([], $this->eligibleIds($b, $this->vodafone, 10));
    }

    public function test_an_offer_that_does_not_match_the_recharge_cannot_be_reserved(): void
    {
        $service = app(OfferService::class);
        $user = $this->retailer();
        $offer = $this->offer([], [$this->ee]);

        $this->expectException(ValidationException::class);
        $service->reserve($user, $offer->id, $this->recharge($user, $this->vodafone));
    }
}
