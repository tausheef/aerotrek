<?php

namespace App\Http\Controllers\API\V1\User;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Services\External\ShiprocketApiService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;

class AddressController extends Controller
{
    use ApiResponse;

    public function __construct(
        private ShiprocketApiService $shiprocket
    ) {}

    // GET /api/v1/user/addresses
    public function index(): JsonResponse
    {
        $addresses = Address::where('user_id', JWTAuth::user()->id)
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->successResponse(data: ['addresses' => $addresses]);
    }

    // POST /api/v1/user/addresses
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'label'         => ['required', 'in:home,office,warehouse,other'],
            'full_name'     => ['required', 'string', 'max:100'],
            'company_name'  => ['nullable', 'string', 'max:150'],
            'address_line1' => ['required', 'string', 'max:100'],
            'address_line2' => ['nullable', 'string', 'max:100'],
            'city'          => ['required', 'string', 'max:50'],
            'state'         => ['required', 'string', 'max:50'],
            'pincode'       => ['required', 'string', 'max:10'],
            'country'       => ['required', 'string', 'max:50'],
            'phone'         => ['required', 'string', 'max:15'],
            'is_default'    => ['boolean'],
        ]);

        $user   = JWTAuth::user();
        $userId = $user->id;

        // If setting as default, unset all others
        if ($request->boolean('is_default')) {
            Address::where('user_id', $userId)->update(['is_default' => false]);
        }

        // If first address, auto set as default
        $isFirst = Address::where('user_id', $userId)->count() === 0;

        $address = Address::create([
            'user_id'       => $userId,
            'label'         => $request->label,
            'full_name'     => $request->full_name,
            'company_name'  => $request->company_name,
            'address_line1' => $request->address_line1,
            'address_line2' => $request->address_line2,
            'city'          => $request->city,
            'state'         => $request->state,
            'pincode'       => $request->pincode,
            'country'       => $request->country,
            'phone'         => $request->phone,
            'is_default'    => $request->boolean('is_default') || $isFirst,
        ]);

        // Try to register this address as a Shiprocket pickup so the user can
        // ship from their own address once they verify the phone OTP. Failures
        // here are non-fatal — the address is still usable for domestic + the
        // booking flow falls back to a verified pickup if this one isn't ready.
        $pickupVerificationRequired = false;
        try {
            $pickup = $this->shiprocket->registerPickupForAddress([
                'id'            => $address->id,
                'full_name'     => $address->full_name,
                'email'         => $user->email,
                'phone'         => $address->phone,
                'address_line1' => $address->address_line1,
                'address_line2' => $address->address_line2 ?? '',
                'city'          => $address->city,
                'state'         => $address->state,
                'country'       => $address->country,
                'pincode'       => $address->pincode,
            ]);

            $address->update([
                'shiprocket_pickup_id'   => $pickup['id'],
                'shiprocket_pickup_name' => $pickup['name'],
                'pickup_verified'        => $pickup['verified'],
                'pickup_verified_at'     => $pickup['verified'] ? now() : null,
            ]);

            $pickupVerificationRequired = ! $pickup['verified'];
        } catch (\Exception $e) {
            Log::warning('Shiprocket pickup registration failed for new address', [
                'address_id' => $address->id,
                'error'      => $e->getMessage(),
            ]);
        }

        return $this->successResponse(
            data: [
                'address'                      => $address->fresh(),
                'pickup_verification_required' => $pickupVerificationRequired,
            ],
            message: $pickupVerificationRequired
                ? 'Address added. Enter the OTP sent to your phone to enable international shipping from this address.'
                : 'Address added.',
            statusCode: 201
        );
    }

    // PUT /api/v1/user/addresses/{id}
    public function update(Request $request, string $id): JsonResponse
    {
        $userId  = JWTAuth::user()->id;
        $address = Address::where('id', $id)
            ->where('user_id', $userId)
            ->firstOrFail();

        $request->validate([
            'label'         => ['sometimes', 'in:home,office,warehouse,other'],
            'full_name'     => ['sometimes', 'string', 'max:100'],
            'company_name'  => ['nullable', 'string', 'max:150'],
            'address_line1' => ['sometimes', 'string', 'max:100'],
            'address_line2' => ['nullable', 'string', 'max:100'],
            'city'          => ['sometimes', 'string', 'max:50'],
            'state'         => ['sometimes', 'string', 'max:50'],
            'pincode'       => ['sometimes', 'string', 'max:10'],
            'country'       => ['sometimes', 'string', 'max:50'],
            'phone'         => ['sometimes', 'string', 'max:15'],
            'is_default'    => ['boolean'],
        ]);

        if ($request->boolean('is_default')) {
            Address::where('user_id', $userId)->update(['is_default' => false]);
        }

        $address->update($request->only([
            'label', 'full_name', 'company_name',
            'address_line1', 'address_line2',
            'city', 'state', 'pincode', 'country',
            'phone', 'is_default',
        ]));

        return $this->successResponse(
            data: ['address' => $address->fresh()],
            message: 'Address updated.'
        );
    }

    // DELETE /api/v1/user/addresses/{id}
    public function destroy(string $id): JsonResponse
    {
        $address = Address::where('id', $id)
            ->where('user_id', JWTAuth::user()->id)
            ->firstOrFail();

        $address->delete();

        return $this->successResponse(message: 'Address deleted.');
    }

    // PUT /api/v1/user/addresses/{id}/default
    public function setDefault(string $id): JsonResponse
    {
        $userId = JWTAuth::user()->id;

        Address::where('user_id', $userId)->update(['is_default' => false]);

        $address = Address::where('id', $id)
            ->where('user_id', $userId)
            ->firstOrFail();

        $address->update(['is_default' => true]);

        return $this->successResponse(message: 'Default address updated.');
    }

    // POST /api/v1/user/addresses/{id}/verify-pickup-otp
    public function verifyPickupOtp(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'otp' => ['required', 'string', 'max:10'],
        ]);

        $address = Address::where('id', $id)
            ->where('user_id', JWTAuth::user()->id)
            ->firstOrFail();

        if (! $address->shiprocket_pickup_id) {
            return $this->errorResponse('No Shiprocket pickup is registered for this address.', 400);
        }

        if ($address->pickup_verified) {
            return $this->successResponse(
                data: ['address' => $address],
                message: 'Pickup already verified.'
            );
        }

        try {
            $verified = $this->shiprocket->verifyPickupOtp(
                (int) $address->shiprocket_pickup_id,
                $request->otp
            );

            if (! $verified) {
                // Fallback: refresh status (user may have used the SMS link already)
                $fresh = $this->shiprocket->refreshPickupStatus($address->shiprocket_pickup_name);
                $verified = $fresh['verified'];
            }

            if (! $verified) {
                return $this->errorResponse('Invalid OTP. Please try again or use the verification link Shiprocket sent.', 400);
            }

            $address->update([
                'pickup_verified'    => true,
                'pickup_verified_at' => now(),
            ]);

            return $this->successResponse(
                data: ['address' => $address->fresh()],
                message: 'Pickup verified successfully!'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('OTP verification failed: ' . $e->getMessage(), 400);
        }
    }

    // POST /api/v1/user/addresses/{id}/resend-pickup-otp
    public function resendPickupOtp(string $id): JsonResponse
    {
        $address = Address::where('id', $id)
            ->where('user_id', JWTAuth::user()->id)
            ->firstOrFail();

        if (! $address->shiprocket_pickup_id) {
            return $this->errorResponse('No Shiprocket pickup is registered for this address.', 400);
        }

        if ($address->pickup_verified) {
            return $this->successResponse(message: 'Pickup already verified — no OTP needed.');
        }

        try {
            $this->shiprocket->resendPickupOtp((int) $address->shiprocket_pickup_id);
            return $this->successResponse(message: 'OTP re-sent. Check your phone.');
        } catch (\Exception $e) {
            return $this->errorResponse('Could not resend OTP: ' . $e->getMessage(), 400);
        }
    }

    // POST /api/v1/user/addresses/{id}/refresh-pickup-status
    // For when the user verified externally via Shiprocket's SMS/email link
    public function refreshPickupStatus(string $id): JsonResponse
    {
        $address = Address::where('id', $id)
            ->where('user_id', JWTAuth::user()->id)
            ->firstOrFail();

        if (! $address->shiprocket_pickup_name) {
            return $this->errorResponse('No Shiprocket pickup is registered for this address.', 400);
        }

        try {
            $fresh = $this->shiprocket->refreshPickupStatus($address->shiprocket_pickup_name);

            $address->update([
                'pickup_verified'    => $fresh['verified'],
                'pickup_verified_at' => $fresh['verified'] && ! $address->pickup_verified_at ? now() : $address->pickup_verified_at,
            ]);

            return $this->successResponse(
                data: ['address' => $address->fresh()],
                message: $fresh['verified'] ? 'Pickup verified!' : 'Pickup still pending verification.'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Could not refresh status: ' . $e->getMessage(), 400);
        }
    }
}
