<?php

namespace App\Http\Controllers\API\V1\Wallet;

use App\Http\Controllers\Controller;
use App\Models\WalletTransaction;
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

        $transactions = WalletTransaction::where('user_id', (string) $user->_id)
            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return $this->successResponse(data: [
            'transactions'   => $transactions,
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