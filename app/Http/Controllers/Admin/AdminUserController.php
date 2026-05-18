<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Wallet\WalletService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    use ApiResponse;

    public function __construct(private WalletService $walletService) {}

    public function index(Request $request): JsonResponse
    {
        $query = User::where('is_admin', false)
            ->withCount('shipments')
            ->with('wallet');

        if ($request->search) {
            $term = trim($request->search);
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('email', 'like', "%{$term}%");
            });
        }

        if ($request->kyc_status) {
            $query->where('kyc_status', $request->kyc_status);
        }

        $paginator = $query->orderBy('created_at', 'desc')->paginate(20);

        $paginator->getCollection()->transform(fn($user) => $this->formatUser($user));

        return $this->successResponse(data: ['users' => $paginator]);
    }

    public function show(string $id): JsonResponse
    {
        $user = User::where('is_admin', false)
            ->withCount('shipments')
            ->with('wallet')
            ->findOrFail($id);

        return $this->successResponse(data: ['user' => $this->formatUser($user)]);
    }

    public function walletCredit(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:500000'],
            'note'   => ['nullable', 'string', 'max:255'],
        ]);

        $user        = User::where('is_admin', false)->findOrFail($id);
        $description = $request->note
            ? 'Admin credit: ' . $request->note
            : 'Admin wallet credit';

        $this->walletService->credit($user, (float) $request->amount, $description);

        return $this->successResponse(
            message: '₹' . number_format($request->amount, 2) . ' added to wallet.',
            data:    ['wallet_balance' => (float) $user->wallet->fresh()->balanceFloat]
        );
    }

    private function formatUser(User $user): array
    {
        return [
            'id'              => $user->id,
            'name'            => $user->name,
            'email'           => $user->email,
            'phone'           => $user->phone,
            'account_type'    => $user->account_type,
            'company_name'    => $user->company_name,
            'kyc_status'      => $user->kyc_status,
            'email_verified'  => $user->email_verified,
            'shipment_limit'  => $user->shipment_limit,
            'shipments_count' => $user->shipments_count ?? 0,
            'wallet_balance'  => (float) ($user->wallet?->balanceFloat ?? 0),
            'created_at'      => $user->created_at?->toISOString(),
        ];
    }
}
