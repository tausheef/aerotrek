<?php

namespace App\Http\Controllers\API\V1\Booking;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Services\Booking\ShipmentService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class ShipmentController extends Controller
{
    use ApiResponse;

    public function __construct(
        private ShipmentService $shipmentService
    ) {}

    /**
     * POST /api/v1/shipments/send-otp
     * DHL only — Step 1: send OTP to shipper's phone
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $request->validate([
            'receiver_name'         => ['required', 'string'],
            'receiver_country_code' => ['required', 'string', 'size:2'],
            'goods_type'            => ['required', 'in:Document,Non-Document'],
        ]);

        $user = JWTAuth::user();

        try {
            $result = $this->shipmentService->sendDhlOtp($user, $request->all());
            return $this->successResponse(
                data:    ['otp_sent' => $result['success']],
                message: 'OTP sent to your registered phone number.'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * POST /api/v1/shipments/verify-otp
     * DHL only — Step 2: verify OTP, get transaction_id
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'receiver_name'         => ['required', 'string'],
            'receiver_country_code' => ['required', 'string', 'size:2'],
            'goods_type'            => ['required', 'in:Document,Non-Document'],
            'otp'                   => ['required', 'string'],
        ]);

        $user = JWTAuth::user();

        try {
            $result = $this->shipmentService->verifyDhlOtp(
                $user,
                $request->all(),
                $request->otp
            );

            if (! $result['success'] || ! $result['transaction_id']) {
                return $this->errorResponse('Invalid OTP. Please try again.', 400);
            }

            return $this->successResponse(
                data:    ['transaction_id' => $result['transaction_id']],
                message: 'OTP verified successfully.'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * POST /api/v1/shipments/book
     * Book shipment — all carriers
     */
    public function book(Request $request): JsonResponse
    {
        $request->validate([
            // Carrier info
            'carrier'              => ['required', 'string'],
            'service_code'         => ['required', 'string'],
            'service_name'         => ['required', 'string'],
            'network'              => ['required', 'string'],
            'price'                => ['required', 'numeric', 'min:1'],
            'goods_type'           => ['required', 'in:Document,Non-Document'],

            // Sender
            'sender_address_line1' => ['required', 'string'],
            'sender_pincode'       => ['required', 'string'],

            // Receiver
            'receiver_name'           => ['required', 'string'],
            'receiver_address_line1'  => ['required', 'string'],
            'receiver_city'           => ['required', 'string'],
            'receiver_state'          => ['required', 'string'],
            'receiver_zipcode'        => ['required', 'string'],
            'receiver_country_code'   => ['required', 'string', 'size:2'],
            'receiver_phone'          => ['required', 'string'],

            // Packages
            'packages'             => ['required', 'array', 'min:1'],
            'packages.*.length'    => ['required', 'numeric'],
            'packages.*.width'     => ['required', 'numeric'],
            'packages.*.height'    => ['required', 'numeric'],
            'packages.*.weight'    => ['required', 'numeric'],

            // Products
            'products'             => ['required', 'array', 'min:1'],
            'products.*.description' => ['required', 'string'],
            'products.*.hsn_code'  => ['required', 'string'],
            'products.*.qty'       => ['required', 'integer'],
            'products.*.unit_rate' => ['required', 'numeric'],

            // DHL only
            'transaction_id'       => ['nullable', 'string'],
        ]);

        $user = JWTAuth::user();

        // Check KYC
        if ($user->kyc_status !== 'verified') {
            return $this->errorResponse('KYC verification required before booking.', 403);
        }

        // Check wallet
        if ($user->wallet_balance < $request->price) {
            return $this->errorResponse(
                'Insufficient wallet balance. Current balance: ₹' . $user->wallet_balance,
                400
            );
        }

        try {
            $shipment = $this->shipmentService->book($user, $request->all());

            return $this->successResponse(
                data: [
                    'shipment'    => $shipment,
                    'awb_no'      => $shipment->awb_no,
                    'tracking_no' => $shipment->tracking_no,
                    'label_url'   => $shipment->label_url,
                    'invoice_url' => $shipment->invoice_url,
                    'wallet_balance' => $user->fresh()->wallet_balance,
                ],
                message: 'Shipment booked successfully!',
                statusCode: 201
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * GET /api/v1/shipments
     * List user's shipments
     */
    public function index(Request $request): JsonResponse
    {
        $user = JWTAuth::user();

        $shipments = Shipment::forUser((string) $user->_id)
            ->when($request->status, fn($q) => $q->byStatus($request->status))
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return $this->successResponse(data: ['shipments' => $shipments]);
    }

    /**
     * GET /api/v1/shipments/{id}
     * Get single shipment details
     */
    public function show(string $id): JsonResponse
    {
        $user     = JWTAuth::user();
        $shipment = Shipment::where('_id', $id)
            ->where('user_id', (string) $user->_id)
            ->firstOrFail();

        return $this->successResponse(data: ['shipment' => $shipment]);
    }
}