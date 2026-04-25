<?php

namespace App\Http\Controllers\API\V1\Wallet;

use App\Http\Controllers\Controller;
use App\Services\Wallet\WalletService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class WalletController extends Controller
{
    use ApiResponse;

    public function __construct(
        private WalletService $walletService
    ) {}

    /**
     * GET /api/v1/wallet
     * Get wallet balance.
     */
    public function balance(): JsonResponse
    {
        $user = JWTAuth::user();

        return $this->successResponse(data: [
            'wallet_balance' => $this->walletService->getBalance($user),
            'currency'       => 'INR',
        ]);
    }

    /**
     * GET /api/v1/wallet/transactions
     * Get transaction history.
     */
    public function transactions(Request $request): JsonResponse
    {
        $user = JWTAuth::user();

        $transactions = $user->transactions()
            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $formatted = $transactions->through(fn($txn) => [
            'id'           => $txn->uuid,
            'type'         => $txn->type,
            'amount'       => $txn->amount / 100,
            'description'  => $txn->meta['description'] ?? null,
            'reference_id' => $txn->meta['reference_id'] ?? null,
            'gateway'      => $txn->meta['payment_gateway'] ?? null,
            'status'       => $txn->confirmed ? 'completed' : 'pending',
            'created_at'   => $txn->created_at?->toISOString(),
        ]);

        return $this->successResponse(data: [
            'transactions'   => $formatted,
            'wallet_balance' => $this->walletService->getBalance($user),
        ]);
    }

    /**
     * POST /api/v1/wallet/recharge
     * Initiate wallet recharge via PayU.
     */
    public function recharge(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => ['required', 'numeric', 'min:100', 'max:100000'],
        ]);

        $user = JWTAuth::user();

        try {
            $paymentData = $this->walletService->initiateRecharge(
                user:   $user,
                amount: (float) $request->amount
            );

            return $this->successResponse(
                data:    $paymentData,
                message: 'Payment initiated. Redirect user to payment_url.'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * POST /api/v1/wallet/payment/success
     * PayU success webhook — called by PayU after payment.
     * NOTE: This is a public route — no JWT needed.
     */
    public function paymentSuccess(Request $request): JsonResponse
    {
        $success = $this->walletService->handleCallback($request->all());

        if ($success) {
            return $this->successResponse(message: 'Wallet credited successfully.');
        }

        return $this->errorResponse('Payment verification failed.', 400);
    }

    /**
     * POST /api/v1/wallet/payment/failure
     * PayU failure webhook.
     * NOTE: This is a public route — no JWT needed.
     */
    public function paymentFailure(Request $request): JsonResponse
    {
        $this->walletService->handleFailure($request->all());
        return $this->errorResponse('Payment failed.', 400);
    }
}