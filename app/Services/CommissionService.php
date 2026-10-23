<?php

namespace App\Services;

use App\Models\AllowedNumber;
use App\Models\CommissionLevel;
use App\Models\CommissionRate;
use App\Models\CommissionRateStep;
use App\Models\RetailerCommission;
use App\Models\RetailerCommissionProfile;
use App\Models\RetailerConnection;
use App\Models\RetailerLevelChange;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletTopup;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class CommissionService
{
    public function __construct(protected WalletService $wallets)
    {
    }

    public function isEnabled(): bool
    {
        return (bool) Setting::get('commission_enabled', true);
    }

    // =========================================================================
    // PAYOUT
    // =========================================================================

    /**
     * Pay cashback for one successful recharge. Safe to call more than once for the
     * same transaction: the first call settles it, later calls do nothing.
     */
    public function processSuccessfulRecharge(Transaction $transaction): ?RetailerCommission
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $transaction = $transaction->fresh();

        if (! $transaction || $transaction->status !== 'success' || $this->isPinProduct($transaction)) {
            return null;
        }

        if (RetailerCommission::where('transaction_id', $transaction->id)->exists()) {
            return null;
        }

        $allowed = $this->findAllowedNumber($transaction);

        if (! $allowed) {
            return null;
            // return $this->recordSkipped($transaction, 'number_not_in_allowed_list');
        }

        $profile = $this->profileFor((int) $transaction->user_id);
        $connection = $this->connectionFor($allowed, $transaction);

        try {
            return DB::transaction(fn () => $this->settle($transaction, $profile->id, $connection->id));
        } catch (UniqueConstraintViolationException) {
            return null; // another worker settled this transaction first
        }
    }

    protected function settle(Transaction $transaction, int $profileId, int $connectionId): ?RetailerCommission
    {
        $profile = RetailerCommissionProfile::lockForUpdate()->findOrFail($profileId);
        $connection = RetailerConnection::lockForUpdate()->findOrFail($connectionId);

        // Numbers are tracked only to count connections: the retailer who recharges a number
        // for the very first time gets the connection. Later recharges of it add none.
        $isNewConnection = $connection->topups_count === 0;

        if ($isNewConnection) {
            $connection->user_id = $transaction->user_id;
            $connection->first_transaction_id = $transaction->id;
            $connection->connected_at = now();
        }

        $connection->topups_count += 1;
        $connection->save();

        $amount = (float) $transaction->amount;
        $level = $profile->commission_level_id ? CommissionLevel::find($profile->commission_level_id) : null;

        // First / ongoing is counted per retailer across all numbers. Only recharges that
        // actually pay commission use up a position.
        $position = $profile->commission_topups_count + 1;
        $type = $position === 1 ? 'first' : 'ongoing';

        $reason = null;
        $rate = null;
        $topup = 0.0;
        $bonus = 0.0;

        if (! $level || ! $level->is_active) {
            $reason = 'no_level';
        } else {
            $rate = $this->findRate($level, $transaction->operator_id ? (int) $transaction->operator_id : null, $amount);

            if (! $rate) {
                $reason = 'no_rate_for_operator_or_amount';
            } elseif ($position === 1) {
                $topup = (float) $rate->first_topup_amount;
                $bonus = (float) $rate->first_bonus_amount;
            } else {
                $step = $this->findStep($rate, $position);

                if ($step) {
                    $topup = (float) $step->amount;
                } else {
                    $reason = 'no_ongoing_rate_for_sequence'; // all ongoing bands of this level are used up
                }
            }
        }

        $total = round($topup + $bonus, 2);

        if ($reason === null && $total <= 0) {
            $reason = 'zero_commission';
        }

        $credited = $reason === null;

        if ($credited) {
            $profile->commission_topups_count = $position;
        }

        if ($isNewConnection) {
            $profile->actual_connections += 1;

            if ($profile->commission_level_id) {
                $profile->level_connections += 1;
            }
        }

        $profile->save();

        if ($isNewConnection) {
            $this->applyAutoUpgrade($profile);
        }

        if ($credited) {
            $commission = RetailerCommission::create([
                'transaction_id' => $transaction->id,
                'user_id' => $transaction->user_id,
                'retailer_connection_id' => $connection->id,
                'commission_level_id' => $level?->id,
                'commission_rate_id' => $rate?->id,
                'operator_id' => $transaction->operator_id,
                'type' => $credited ? $type : null,
                'topup_sequence' => $credited ? $position : null,
                'recharge_amount' => $amount,
                'topup_amount' => $credited ? $topup : 0,
                'bonus_amount' => $credited ? $bonus : 0,
                'commission_amount' => $credited ? $total : 0,
                'status' => $credited ? 'credited' : 'skipped',
                'skip_reason' => $reason,
                'credited_at' => $credited ? now() : null,
            ]);
        
            $this->creditWallet($commission, $transaction, $level, $topup, $bonus, $type, $position);
        }

        return $commission ?? null;
    }

    /**
     * Put the money in the wallet as entries in the retailer's wallet top-ups list.
     * First top-up of a connection: one "Commission" entry and one "Bonus" entry (a part
     * worth £0 gets no entry). Ongoing top-ups: one "Commission" entry.
     * Each entry is its own completed top-up with its own ledger credit.
     */
    protected function creditWallet(
        RetailerCommission $commission,
        Transaction $transaction,
        ?CommissionLevel $level,
        float $topup,
        float $bonus,
        string $type,
        int $sequence
    ): void {
        $parts = array_filter([
            WalletTopup::GATEWAY_COMMISSION => round($topup, 2),
            WalletTopup::GATEWAY_BONUS => round($bonus, 2),
        ], fn ($amount) => $amount > 0);

        foreach ($parts as $part => $amount) {
            $entry = WalletTopup::create([
                'user_id' => $transaction->user_id,
                'amount' => $amount,
                'currency' => config('platform.wallet.currency', 'GBP'),
                'fee_amount' => 0,
                'fee_percentage' => 0,
                'total_charged' => $amount,
                'payment_method' => 'wallet',
                'payment_gateway' => $part,
                'gateway_order_id' => "commission-{$transaction->id}-{$part}", // unique: one entry per part per recharge
                'payment_response' => [
                    'source' => 'commission',
                    'part' => $part,
                    'type' => $type,
                    'sequence' => $sequence,
                    'level' => $level?->name,
                    'operator' => $transaction->operator?->name,
                    'number' => $transaction->mobile_number,
                    'recharge_transaction_id' => $transaction->id,
                    'retailer_commission_id' => $commission->id,
                ],
                'status' => 'completed',
            ]);

            $this->wallets->credit(
                (int) $transaction->user_id,
                $amount,
                $entry->id, // same convention as Stripe top-ups: the ledger points at the wallet top-up
                sprintf(
                    '%s (%s top-up #%d) - %s %s',
                    $part === WalletTopup::GATEWAY_BONUS ? 'Bonus' : 'Commission',
                    $type,
                    $sequence,
                    $transaction->operator?->name ?? 'operator',
                    $transaction->mobile_number
                )
            );
        }
    }

    protected function recordSkipped(Transaction $transaction, string $reason): ?RetailerCommission
    {
        try {
            return RetailerCommission::create([
                'transaction_id' => $transaction->id,
                'user_id' => $transaction->user_id,
                'operator_id' => $transaction->operator_id,
                'recharge_amount' => (float) $transaction->amount,
                'status' => 'skipped',
                'skip_reason' => $reason,
            ]);
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }

    protected function isPinProduct(Transaction $transaction): bool
    {
        return $transaction->redemption_type === 'ReadReceipt' || $transaction->product_type === 'pin';
    }

    // =========================================================================
    // LOOKUPS
    // =========================================================================

    /**
     * Same matching rules as the recharge number validation (RechargeController@validateNumber).
     */
    protected function findAllowedNumber(Transaction $transaction): ?AllowedNumber
    {
        $raw = (string) $transaction->mobile_number;
        $digits = preg_replace('/[^0-9]/', '', $raw);

        $serialInputs = array_values(array_unique(array_filter([
            preg_replace('/[^A-Za-z0-9\-]/', '', $raw),
            preg_replace('/[^A-Za-z0-9\-]/', '', (string) $transaction->serial_number),
        ], fn ($v) => strlen((string) $v) >= 5)));

        $national = null;

        if (strlen($digits) === 12 && str_starts_with($digits, '44')) {
            $national = substr($digits, 2);
        } elseif (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $national = substr($digits, 1);
        }

        $mobileVariants = $national !== null
            ? ['44' . $national, '0' . $national, $national]
            : (strlen($digits) >= 5 ? [$digits] : []);

        if (! $serialInputs && ! $mobileVariants) {
            return null;
        }

        return AllowedNumber::where('active', true)
            ->where(function ($q) use ($serialInputs, $mobileVariants) {
                if ($serialInputs) {
                    $q->whereIn('serial', $serialInputs);
                }
                if ($mobileVariants) {
                    $q->orWhereIn('mobile', $mobileVariants);
                }
            })
            ->first();
    }

    /**
     * A stable identity for the SIM: its serial when known, otherwise its mobile number.
     * Re-uploading the same row later keeps the same key.
     */
    protected function accountKey(AllowedNumber $allowed): string
    {
        $serial = (string) $allowed->serial;

        if ($serial !== '' && $serial !== '0') {
            return 's:' . strtolower($serial);
        }

        return 'm:' . $allowed->mobile;
    }

    protected function connectionFor(AllowedNumber $allowed, Transaction $transaction): RetailerConnection
    {
        $key = $this->accountKey($allowed);

        try {
            return RetailerConnection::firstOrCreate(['account_key' => $key], [
                'user_id' => $transaction->user_id,
                'allowed_number_id' => $allowed->id,
                'account_number' => mb_substr((string) $transaction->mobile_number, 0, 100),
                'operator_id' => $transaction->operator_id,
                'topups_count' => 0,
            ]);
        } catch (UniqueConstraintViolationException) {
            return RetailerConnection::where('account_key', $key)->firstOrFail();
        }
    }

    public function profileFor(int $userId): RetailerCommissionProfile
    {
        try {
            return RetailerCommissionProfile::firstOrCreate(['user_id' => $userId]);
        } catch (UniqueConstraintViolationException) {
            return RetailerCommissionProfile::where('user_id', $userId)->firstOrFail();
        }
    }

    /**
     * Best matching band: an operator-specific band beats an "all operators" band,
     * and among several matches the one with the highest minimum amount wins.
     */
    public function findRate(CommissionLevel $level, ?int $operatorId, float $amount): ?CommissionRate
    {
        return CommissionRate::with('steps')
            ->where('commission_level_id', $level->id)
            ->where('is_active', true)
            ->where(function ($q) use ($operatorId) {
                if ($operatorId) {
                    $q->where('operator_id', $operatorId)->orWhereNull('operator_id');
                } else {
                    $q->whereNull('operator_id');
                }
            })
            ->where('min_amount', '<=', $amount)
            ->where(function ($q) use ($amount) {
                $q->whereNull('max_amount')->orWhere('max_amount', '>=', $amount);
            })
            ->get()
            ->sortByDesc(fn (CommissionRate $r) => [$r->operator_id ? 1 : 0, (float) $r->min_amount])
            ->first();
    }

    protected function findStep(CommissionRate $rate, int $sequence): ?CommissionRateStep
    {
        return $rate->steps->first(
            fn (CommissionRateStep $s) => $sequence >= $s->from_sequence
                && ($s->to_sequence === null || $sequence <= $s->to_sequence)
        );
    }

    public function nextLevelAfter(?CommissionLevel $current): ?CommissionLevel
    {
        if (! $current) {
            return null;
        }

        return CommissionLevel::where('is_active', true)
            ->where('min_connections', '>', $current->min_connections)
            ->orderBy('min_connections')
            ->first();
    }

    // =========================================================================
    // LEVEL MANAGEMENT
    // =========================================================================

    /**
     * Upgrade a retailer automatically when level connections pass the next level's minimum.
     * Levels never downgrade automatically.
     */
    protected function applyAutoUpgrade(RetailerCommissionProfile $profile): void
    {
        if (! $profile->auto_level_enabled || ! $profile->commission_level_id) {
            return;
        }

        $current = CommissionLevel::find($profile->commission_level_id);

        $target = CommissionLevel::where('is_active', true)
            ->where('min_connections', '<=', $profile->level_connections)
            ->orderByDesc('min_connections')
            ->first();

        if (! $current || ! $target || $target->min_connections <= $current->min_connections) {
            return;
        }

        $profile->commission_level_id = $target->id;
        $profile->commission_topups_count = 0; // new level: the next paying recharge is a first top-up again
        $profile->level_assigned_at = now();
        $profile->save();

        $this->logChange(
            $profile,
            $current->id,
            $target->id,
            'auto_upgrade',
            $profile->level_connections,
            null,
            'Reached ' . $profile->level_connections . ' level connections'
        );
    }

    /**
     * Set (or clear) a retailer's level by hand.
     *
     * - Picking a level resets "level connections" to that level's minimum (both for upgrades
     *   and downgrades). "Actual connections" is never touched unless an opening value is given.
     * - Picking no level clears the level and switches auto-upgrade off.
     * - Any change of level restarts the retailer's first / ongoing position at 0.
     */
    public function assignLevel(
        User $retailer,
        ?int $levelId,
        ?int $adminId = null,
        ?string $reason = null,
        bool $autoLevel = true,
        ?int $actualConnections = null,
        ?int $topupsCount = null,
    ): RetailerCommissionProfile {
        $this->profileFor($retailer->id);

        return DB::transaction(function () use ($retailer, $levelId, $adminId, $reason, $autoLevel, $actualConnections, $topupsCount) {
            $profile = RetailerCommissionProfile::lockForUpdate()->where('user_id', $retailer->id)->firstOrFail();

            $from = $profile->commission_level_id ? CommissionLevel::find($profile->commission_level_id) : null;
            $to = $levelId ? CommissionLevel::findOrFail($levelId) : null;
            $levelConnectionsBefore = $profile->level_connections;
            $changeType = null;

            if ($actualConnections !== null) {
                $profile->actual_connections = max(0, $actualConnections);
            }

            if ($to === null) {
                if ($from !== null) {
                    $profile->commission_level_id = null;
                    $profile->level_connections = 0;
                    $profile->auto_level_enabled = false;
                    $profile->level_assigned_at = now();
                    $changeType = 'removed';
                }
            } elseif ($from === null || $from->id !== $to->id) {
                $changeType = $from === null
                    ? 'assigned'
                    : ($to->min_connections >= $from->min_connections ? 'upgrade' : 'downgrade');

                $profile->commission_level_id = $to->id;
                $profile->level_connections = $to->min_connections;
                $profile->auto_level_enabled = $autoLevel;
                $profile->level_assigned_at = now();
            } elseif ($profile->auto_level_enabled !== $autoLevel) {
                $profile->auto_level_enabled = $autoLevel;
            }

            // Any change of level restarts the first / ongoing position.
            if (in_array($changeType, ['assigned', 'upgrade', 'downgrade', 'removed'], true)) {
                $profile->commission_topups_count = 0;
            }

            // A value typed in by the admin wins over the restart.
            if ($topupsCount !== null) {
                $profile->commission_topups_count = max(0, $topupsCount);
            }

            if ($changeType === null && $profile->isDirty()) {
                $changeType = 'settings';
            }

            $profile->save();

            if ($changeType !== null) {
                $this->logChange(
                    $profile,
                    $from?->id,
                    $to?->id,
                    $changeType,
                    $levelConnectionsBefore,
                    $adminId,
                    $reason
                );
            }

            return $profile;
        });
    }

    protected function logChange(
        RetailerCommissionProfile $profile,
        ?int $fromLevelId,
        ?int $toLevelId,
        string $type,
        int $levelConnectionsBefore,
        ?int $changedBy,
        ?string $reason
    ): void {
        RetailerLevelChange::create([
            'user_id' => $profile->user_id,
            'from_level_id' => $fromLevelId,
            'to_level_id' => $toLevelId,
            'change_type' => $type,
            'actual_connections' => $profile->actual_connections,
            'level_connections_before' => $levelConnectionsBefore,
            'level_connections_after' => $profile->level_connections,
            'changed_by' => $changedBy,
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }
}
