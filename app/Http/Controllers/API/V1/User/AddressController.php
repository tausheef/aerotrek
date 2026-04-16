<?php

namespace App\Http\Controllers\API\V1\User;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class AddressController extends Controller
{
    use ApiResponse;

    // GET /api/v1/user/addresses
    public function index(): JsonResponse
    {
        $addresses = Address::where('user_id', JWTAuth::user()->_id)
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

        $userId = JWTAuth::user()->_id;

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

        return $this->successResponse(
            data: ['address' => $address],
            message: 'Address added.',
            statusCode: 201
        );
    }

    // PUT /api/v1/user/addresses/{id}
    public function update(Request $request, string $id): JsonResponse
    {
        $userId  = JWTAuth::user()->_id;
        $address = Address::where('_id', $id)
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
        $address = Address::where('_id', $id)
            ->where('user_id', JWTAuth::user()->_id)
            ->firstOrFail();

        $address->delete();

        return $this->successResponse(message: 'Address deleted.');
    }

    // PUT /api/v1/user/addresses/{id}/default
    public function setDefault(string $id): JsonResponse
    {
        $userId = JWTAuth::user()->_id;

        Address::where('user_id', $userId)->update(['is_default' => false]);

        $address = Address::where('_id', $id)
            ->where('user_id', $userId)
            ->firstOrFail();

        $address->update(['is_default' => true]);

        return $this->successResponse(message: 'Default address updated.');
    }
}