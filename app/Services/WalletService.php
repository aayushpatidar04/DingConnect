<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletLedger;
use Illuminate\Support\Facades\DB;

class WalletService
{
    protected function resolveUserAndWallet(int $userId): array
    {
        $wallet = DB::table('wallets')->where('user_id', $userId)->first();
        if (!$wallet) {
            throw new \RuntimeException('Wallet not found for user ' . $userId);
        }
        return [
            'userId' => $userId,
            'walletId' => (int) $wallet->id,
            'balance' => (float) $wallet->balance,
        ];
    }

    public function getWallet($user): ?Wallet
    {
        if ($user instanceof User) {
            return Wallet::firstOrCreate(['user_id' => $user->id], [
                'balance' => 0,
                'currency' => config('platform.wallet.currency', 'GBP'),
            ]);
        }

        return Wallet::where('user_id', $user)->first();
    }

    public function getBalance(int $userId): float
    {
        $wallet = DB::table('wallets')->where('user_id', $userId)->first();
        return $wallet ? (float) $wallet->balance : 0.0;
    }

    public function getAvailableBalance(Wallet $wallet): float
    {
        $ledger = DB::table('wallet_ledgers')
            ->where('wallet_id', $wallet->id)
            ->orderByDesc('created_at')
            ->first();

        return $ledger ? max(0, (float) $ledger->balance_after) : (float) $wallet->balance;
    }

    /**
     * Hold (freeze) an amount from wallet — used before recharge to prevent double-spending
     */
    public function hold(int $userId, float $amount, ?int $transactionId = null, ?string $description = null): void
    {
        $info = $this->resolveUserAndWallet($userId);
        $balanceBefore = $info['balance'];

        if ($balanceBefore < $amount) {
            throw new \RuntimeException('Insufficient balance for hold');
        }

        DB::table('wallets')->where('user_id', $userId)->decrement('balance', $amount);
        $this->ledger($info, $transactionId, 'hold', $amount, $description ?? 'Amount held', $balanceBefore, $balanceBefore - $amount);
    }

    /**
     * Release a hold back to the wallet — used when transaction fails
     */
    public function releaseHold(int $userId, float $amount, ?int $transactionId = null, ?string $description = null): void
    {
        $info = $this->resolveUserAndWallet($userId);
        $balanceBefore = $info['balance'];

        DB::table('wallets')->where('user_id', $userId)->increment('balance', $amount);
        $this->ledger($info, $transactionId, 'release', $amount, $description ?? 'Hold released', $balanceBefore, $balanceBefore + $amount);
    }

    /**
     * Deduct from wallet permanently — used when transaction succeeds
     */
    public function debit(int $userId, float $amount, ?int $transactionId = null, ?string $description = null): void
    {
        $info = $this->resolveUserAndWallet($userId);
        $balanceBefore = $info['balance'];

        if ($balanceBefore < $amount) {
            throw new \RuntimeException('Insufficient balance');
        }

        DB::table('wallets')->where('user_id', $userId)->decrement('balance', $amount);
        $this->ledger($info, $transactionId, 'debit', $amount, $description ?? 'Debit', $balanceBefore, $balanceBefore - $amount);
    }

    /**
     * Credit to wallet — used when loading wallet via payment gateway or admin credit
     */
    public function credit(int $userId, float $amount, ?int $transactionId = null, ?string $description = null): void
    {
        $info = $this->resolveUserAndWallet($userId);
        $balanceBefore = $info['balance'];

        DB::table('wallets')->where('user_id', $userId)->increment('balance', $amount);
        $this->ledger($info, $transactionId, 'credit', $amount, $description ?? 'Wallet credit', $balanceBefore, $balanceBefore + $amount);
    }

    /**
     * Refund to wallet — used when a successful transaction needs to be reversed
     */
    public function refund(int $userId, float $amount, ?int $transactionId = null, ?string $description = null): void
    {
        $info = $this->resolveUserAndWallet($userId);
        $balanceBefore = $info['balance'];

        DB::table('wallets')->where('user_id', $userId)->increment('balance', $amount);
        $this->ledger($info, $transactionId, 'refund', $amount, $description ?? 'Refund', $balanceBefore, $balanceBefore + $amount);
    }

    protected function ledger(array $info, ?int $referenceId, string $type, float $amount, string $description, float $balanceBefore, float $balanceAfter): void
    {
        DB::table('wallet_ledgers')->insert([
            'wallet_id'      => $info['walletId'],
            'transaction_id' => $referenceId,
            'type'           => $type,
            'amount'         => $amount,
            'balance_before' => $balanceBefore,
            'balance_after'  => $balanceAfter,
            'description'    => $description,
            'ip_address'     => request()->ip(),
            'user_agent'     => request()->userAgent() ?? null,
            'created_at'     => now(),
        ]);
    }
}
