<?php

namespace App\Services\Wallet;

use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Payment\Contracts\PaymentGatewayInterface;
use App\Services\Payment\Drivers\PayUDriver;
use Illuminate\Support\Str;

class WalletService
{
    private PaymentGatewayInterface $gateway;

    public function __construct()
    {
        $this->gateway = $this->resolveGateway();
    }

    /**
     * Resolve payment gateway from .env.
     * PAYMENT_DRIVER=payu     → PayUDriver (now)
     * PAYMENT_DRIVER=razorpay → RazorpayDriver (future)
     */
    private function resolveGateway(): PaymentGatewayInterface
    {
        return match (config('payu.driver', 'payu')) {
            'payu'  => new PayUDriver(),
            default => new PayUDriver(),
        };
    }

    /**
     * Initiate wallet recharge — returns PayU payment URL + params.
     */
    public function initiateRecharge(User $user, float $amount): array
    {
        // Minimum recharge amount
        if ($amount < 100) {
            throw new \Exception('Minimum recharge amount is ₹100.');
        }

        // Generate unique transaction ID
        $txnId = 'TXN' . strtoupper(Str::random(12)) . time();

        // Create pending transaction
        WalletTransaction::create([
            'user_id'         => (string) $user->_id,
            'type'            => 'credit',
            'amount'          => $amount,
            'balance_before'  => $user->wallet_balance,
            'balance_after'   => $user->wallet_balance + $amount,
            'description'     => 'Wallet recharge via ' . $this->gateway->getGatewayName(),
            'reference_id'    => $txnId,
            'payment_gateway' => $this->gateway->getGatewayName(),
            'status'          => 'pending',
        ]);

        // Get payment URL from gateway
        return $this->gateway->initiatePayment([
            'txnid'       => $txnId,
            'amount'      => $amount,
            'productinfo' => 'AeroTrek Wallet Recharge',
            'firstname'   => $user->name,
            'email'       => $user->email,
            'phone'       => $user->phone,
            'udf1'        => (string) $user->_id,  // store user_id for webhook
            'udf2'        => $txnId,
            'surl'        => config('app.url') . '/api/v1/wallet/payment/success',
            'furl'        => config('app.url') . '/api/v1/wallet/payment/failure',
        ]);
    }

    /**
     * Handle PayU webhook callback — credit wallet on success.
     */
    public function handleCallback(array $callbackData): bool
    {
        // Verify payment authenticity
        if (! $this->gateway->verifyPayment($callbackData)) {
            return false;
        }

        $txnId = $callbackData['txnid'];

        // Find pending transaction
        $transaction = WalletTransaction::where('reference_id', $txnId)
            ->where('status', 'pending')
            ->first();

        if (! $transaction) {
            return false;
        }

        // Find user
        $user = User::find($transaction->user_id);
        if (! $user) {
            return false;
        }

        // Credit wallet
        $newBalance = $user->wallet_balance + $transaction->amount;
        $user->update(['wallet_balance' => $newBalance]);

        // Update transaction
        $transaction->update([
            'status'           => 'completed',
            'balance_after'    => $newBalance,
            'gateway_response' => $callbackData,
        ]);

        return true;
    }

    /**
     * Handle failed payment.
     */
    public function handleFailure(array $callbackData): void
    {
        $txnId = $callbackData['txnid'] ?? null;
        if (! $txnId) return;

        WalletTransaction::where('reference_id', $txnId)
            ->where('status', 'pending')
            ->update([
                'status'           => 'failed',
                'gateway_response' => $callbackData,
            ]);
    }

    /**
     * Deduct from wallet — used when booking shipment.
     */
    public function deduct(User $user, float $amount, string $description, string $referenceId): WalletTransaction
    {
        if ($user->wallet_balance < $amount) {
            throw new \Exception('Insufficient wallet balance.');
        }

        $balanceBefore = $user->wallet_balance;
        $newBalance    = $balanceBefore - $amount;

        // Update wallet balance
        $user->update(['wallet_balance' => $newBalance]);

        // Record transaction
        return WalletTransaction::create([
            'user_id'         => (string) $user->_id,
            'type'            => 'debit',
            'amount'          => $amount,
            'balance_before'  => $balanceBefore,
            'balance_after'   => $newBalance,
            'description'     => $description,
            'reference_id'    => $referenceId,
            'payment_gateway' => 'system',
            'status'          => 'completed',
        ]);
    }

    /**
     * Credit wallet manually — admin top up.
     */
    public function credit(User $user, float $amount, string $description): WalletTransaction
    {
        $balanceBefore = $user->wallet_balance;
        $newBalance    = $balanceBefore + $amount;

        $user->update(['wallet_balance' => $newBalance]);

        return WalletTransaction::create([
            'user_id'         => (string) $user->_id,
            'type'            => 'credit',
            'amount'          => $amount,
            'balance_before'  => $balanceBefore,
            'balance_after'   => $newBalance,
            'description'     => $description,
            'reference_id'    => 'MANUAL_' . time(),
            'payment_gateway' => 'manual',
            'status'          => 'completed',
        ]);
    }

    /**
     * Get wallet balance.
     */
    public function getBalance(User $user): float
    {
        return (float) $user->wallet_balance;
    }
}