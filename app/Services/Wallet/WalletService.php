<?php

namespace App\Services\Wallet;

use App\Models\User;
use App\Models\WalletRecharge;
use App\Services\Payment\Contracts\PaymentGatewayInterface;
use App\Services\Payment\Drivers\PayUDriver;
use Bavix\Wallet\Models\Transaction;
use Illuminate\Support\Str;

class WalletService
{
    private PaymentGatewayInterface $gateway;

    public function __construct()
    {
        $this->gateway = new PayUDriver();
    }

    public function initiateRecharge(User $user, float $amount): array
    {
        if ($amount < 100) {
            throw new \Exception('Minimum recharge amount is ₹100.');
        }

        $txnId = 'TXN' . strtoupper(Str::random(12)) . time();

        WalletRecharge::create([
            'user_id' => $user->id,
            'txn_id'  => $txnId,
            'amount'  => $amount,
            'status'  => 'pending',
        ]);

        return $this->gateway->initiatePayment([
            'txnid'       => $txnId,
            'amount'      => $amount,
            'productinfo' => 'AeroTrek Wallet Recharge',
            'firstname'   => $user->name,
            'email'       => $user->email,
            'phone'       => $user->phone,
            'udf1'        => $user->id,
            'udf2'        => $txnId,
            'surl'        => config('app.url') . '/api/v1/wallet/payment/success',
            'furl'        => config('app.url') . '/api/v1/wallet/payment/failure',
        ]);
    }

    public function handleCallback(array $callbackData): bool
    {
        if (! $this->gateway->verifyPayment($callbackData)) {
            return false;
        }

        $txnId = $callbackData['txnid'];

        $recharge = WalletRecharge::where('txn_id', $txnId)
            ->where('status', 'pending')
            ->first();

        if (! $recharge) {
            return false;
        }

        $user = User::find($recharge->user_id);
        if (! $user) {
            return false;
        }

        $user->deposit(intval($recharge->amount * 100), [
            'description'     => 'Wallet recharge via ' . $this->gateway->getGatewayName(),
            'reference_id'    => $txnId,
            'payment_gateway' => $this->gateway->getGatewayName(),
        ]);

        $recharge->update([
            'status'           => 'completed',
            'gateway_response' => $callbackData,
        ]);

        return true;
    }

    public function handleFailure(array $callbackData): void
    {
        $txnId = $callbackData['txnid'] ?? null;
        if (! $txnId) return;

        WalletRecharge::where('txn_id', $txnId)
            ->where('status', 'pending')
            ->update([
                'status'           => 'failed',
                'gateway_response' => $callbackData,
            ]);
    }

    public function deduct(User $user, float $amount, string $description, string $referenceId): Transaction
    {
        return $user->withdraw(intval($amount * 100), [
            'description'     => $description,
            'reference_id'    => $referenceId,
            'payment_gateway' => 'system',
        ]);
    }

    public function credit(User $user, float $amount, string $description): Transaction
    {
        return $user->deposit(intval($amount * 100), [
            'description'     => $description,
            'reference_id'    => 'MANUAL_' . time(),
            'payment_gateway' => 'manual',
        ]);
    }

    public function getBalance(User $user): float
    {
        return (float) $user->balanceFloat;
    }
}
