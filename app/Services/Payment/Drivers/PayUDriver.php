<?php

namespace App\Services\Payment\Drivers;

use App\Services\Payment\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Facades\Log;

class PayUDriver implements PaymentGatewayInterface
{
    private string $key;
    private string $salt;
    private string $mode;
    private string $paymentUrl;

    public function __construct()
    {
        $this->key        = config('payu.key', '');
        $this->salt       = config('payu.salt', '');
        $this->mode       = config('payu.mode', 'test');
        $this->paymentUrl = $this->mode === 'live'
            ? 'https://secure.payu.in/_payment'
            : 'https://test.payu.in/_payment';
    }

    /**
     * Initiate PayU payment.
     * Returns payment URL + all params frontend needs to POST to PayU.
     *
     * PayU hash formula:
     * sha512(key|txnid|amount|productinfo|firstname|email|udf1|udf2|udf3|udf4|udf5||||||salt)
     */
    public function initiatePayment(array $data): array
    {
        $params = [
            'key'         => $this->key,
            'txnid'       => $data['txnid'],           // unique transaction ID
            'amount'      => number_format($data['amount'], 2, '.', ''),
            'productinfo' => $data['productinfo'] ?? 'Wallet Recharge',
            'firstname'   => $data['firstname'],
            'email'       => $data['email'],
            'phone'       => $data['phone'],
            'surl'        => $data['surl'],             // success URL
            'furl'        => $data['furl'],             // failure URL
            'udf1'        => $data['udf1'] ?? '',       // user defined field (user_id)
            'udf2'        => $data['udf2'] ?? '',
            'udf3'        => '',
            'udf4'        => '',
            'udf5'        => '',
        ];

        $params['hash'] = $this->generateHash($params);

        return [
            'payment_url' => $this->paymentUrl,
            'params'      => $params,
            'gateway'     => $this->getGatewayName(),
        ];
    }

    /**
     * Verify payment after PayU callback.
     */
    public function verifyPayment(array $callbackData): bool
    {
        // Check status
        if (($callbackData['status'] ?? '') !== 'success') {
            return false;
        }

        // Verify hash
        return $this->verifyHash($callbackData);
    }

    /**
     * Generate PayU hash.
     * Formula: sha512(key|txnid|amount|productinfo|firstname|email|udf1|udf2|udf3|udf4|udf5||||||salt)
     */
    public function generateHash(array $data): string
    {
        $hashString = implode('|', [
            $this->key,
            $data['txnid'],
            $data['amount'],
            $data['productinfo'],
            $data['firstname'],
            $data['email'],
            $data['udf1'] ?? '',
            $data['udf2'] ?? '',
            $data['udf3'] ?? '',
            $data['udf4'] ?? '',
            $data['udf5'] ?? '',
            '', '', '', '', '', // empty fields
            $this->salt,
        ]);

        return strtolower(hash('sha512', $hashString));
    }

    /**
     * Verify hash from PayU callback.
     * Reverse formula: sha512(salt|status||||||udf5|udf4|udf3|udf2|udf1|email|firstname|productinfo|amount|txnid|key)
     */
    public function verifyHash(array $data): bool
    {
        $hashString = implode('|', [
            $this->salt,
            $data['status'],
            '', '', '', '', '',  // empty fields
            $data['udf5'] ?? '',
            $data['udf4'] ?? '',
            $data['udf3'] ?? '',
            $data['udf2'] ?? '',
            $data['udf1'] ?? '',
            $data['email'],
            $data['firstname'],
            $data['productinfo'],
            $data['amount'],
            $data['txnid'],
            $this->key,
        ]);

        $calculatedHash = strtolower(hash('sha512', $hashString));
        $receivedHash   = strtolower($data['hash'] ?? '');

        Log::info('PayU hash verification', [
            'match' => $calculatedHash === $receivedHash,
        ]);

        return $calculatedHash === $receivedHash;
    }

    public function getGatewayName(): string
    {
        return 'payu';
    }
}