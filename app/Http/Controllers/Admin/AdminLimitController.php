<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LimitExtensionRequest;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminLimitController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $requests = LimitExtensionRequest::with('user:id,name,email,shipment_limit')
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(20);

        return $this->successResponse(data: $requests);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'add' => ['nullable', 'integer', 'min:1'],
        ]);

        $limitRequest = LimitExtensionRequest::where('status', 'pending')->findOrFail($id);
        $user         = $limitRequest->user;
        $addAmount    = $request->input('add', 500);

        $user->update(['shipment_limit' => $user->shipment_limit + $addAmount]);
        $limitRequest->update(['status' => 'approved']);

        return $this->successResponse(
            data: [
                'user_id'       => $user->id,
                'new_limit'     => $user->shipment_limit,
                'added'         => $addAmount,
            ],
            message: "Limit extended by {$addAmount}. New limit is {$user->shipment_limit}."
        );
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $limitRequest = LimitExtensionRequest::where('status', 'pending')->findOrFail($id);
        $limitRequest->update([
            'status'           => 'rejected',
            'rejection_reason' => $request->reason,
        ]);

        return $this->successResponse(message: 'Limit extension request rejected.');
    }
}
