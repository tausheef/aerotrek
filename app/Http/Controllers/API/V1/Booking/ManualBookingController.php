<?php

namespace App\Http\Controllers\API\V1\Booking;

use App\Exceptions\ShipmentLimitReachedException;
use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Services\Shipment\AerotrekIdGenerator;
use App\Services\Wallet\WalletService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;

class ManualBookingController extends Controller
{
    use ApiResponse;

    public function __construct(
        private AerotrekIdGenerator $idGenerator,
        private WalletService       $wallet,
    ) {}

    /**
     * POST /api/v1/shipments/manual-book
     * User submits shipment details with a selected rate.
     * Wallet is debited immediately; AeroTrek staff books on carrier and updates AWB.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'goods_type'              => ['required', 'in:Document,Non-Document'],
            'reason_for_export'       => ['required', 'in:GIFT,SALE,SAMPLE,RETURN,PERSONAL_USE'],
            'duty_tax'                => ['required', 'in:DDU,DDP'],

            // Selected carrier/rate (optional — user may skip rate selection)
            'carrier'                 => ['nullable', 'string'],
            'service_code'            => ['nullable', 'string'],
            'service_name'            => ['nullable', 'string'],
            'network'                 => ['nullable', 'string'],
            'price'                   => ['required_with:carrier', 'nullable', 'numeric', 'min:1'],

            // Sender
            'sender_name'             => ['required', 'string', 'max:100'],
            'sender_address_line1'    => ['required', 'string', 'max:255'],
            'sender_address_line2'    => ['nullable', 'string', 'max:255'],
            'sender_city'             => ['required', 'string', 'max:100'],
            'sender_state'            => ['required', 'string', 'max:100'],
            'sender_pincode'          => ['required', 'string', 'max:20'],
            'sender_phone'            => ['required', 'string', 'max:20'],

            // Receiver
            'receiver_name'           => ['required', 'string', 'max:100'],
            'receiver_address_line1'  => ['required', 'string', 'max:255'],
            'receiver_address_line2'  => ['nullable', 'string', 'max:255'],
            'receiver_city'           => ['required', 'string', 'max:100'],
            'receiver_state'          => ['required', 'string', 'max:100'],
            'receiver_zipcode'        => ['required', 'string', 'max:20'],
            'receiver_country_code'   => ['required', 'string', 'size:2'],
            'receiver_phone'          => ['required', 'string', 'max:20'],

            // Packages
            'packages'                => ['required', 'array', 'min:1'],
            'packages.*.length'       => ['required', 'numeric', 'min:0.1'],
            'packages.*.width'        => ['required', 'numeric', 'min:0.1'],
            'packages.*.height'       => ['required', 'numeric', 'min:0.1'],
            'packages.*.weight'       => ['required', 'numeric', 'min:0.1'],

            // Products (required for Non-Document only)
            'products'                => ['nullable', 'array'],
            'products.*.description'  => ['required_with:products', 'string'],
            'products.*.hsn_code'     => ['required_with:products', 'string'],
            'products.*.qty'          => ['required_with:products', 'integer', 'min:1'],
            'products.*.unit_rate'    => ['required_with:products', 'numeric', 'min:0'],

            'invoice_no'              => ['nullable', 'string', 'max:100'],
            'invoice_date'            => ['nullable', 'string'],
            'notes'                   => ['nullable', 'string', 'max:1000'],
        ]);

        $user = JWTAuth::user();

        // KYC required
        if ($user->kyc_status !== 'verified') {
            return $this->errorResponse('KYC verification required before booking.', 403);
        }

        // Wallet check (only when a price is submitted with the request)
        if ($request->price && $user->balanceFloat < $request->price) {
            return $this->errorResponse(
                'Insufficient wallet balance. Current balance: ₹' . $user->balanceFloat,
                400
            );
        }

        try {
            // May throw ShipmentLimitReachedException — must run before any DB writes
            $aerotrekId = $this->idGenerator->generate($user);

            $shipment = null;

            DB::transaction(function () use ($request, $user, $aerotrekId, &$shipment) {
                $shipment = Shipment::create([
                    'aerotrek_id'       => $aerotrekId,
                    'booking_type'      => 'manual',
                    'platform'          => null,
                    'user_id'           => $user->id,
                    'status'            => 'pending_acceptance',
                    'goods_type'        => $request->goods_type,
                    'carrier'           => $request->carrier,
                    'service_code'      => $request->service_code,
                    'service_name'      => $request->service_name,
                    'network'           => $request->network,
                    'price'             => $request->price,
                    'reason_for_export' => $request->reason_for_export,
                    'duty_tax'          => $request->duty_tax,
                    'sender' => [
                        'name'          => $request->sender_name,
                        'address_line1' => $request->sender_address_line1,
                        'address_line2' => $request->sender_address_line2 ?? '',
                        'city'          => $request->sender_city,
                        'state'         => $request->sender_state,
                        'pincode'       => $request->sender_pincode,
                        'phone'         => $request->sender_phone,
                        'email'         => $user->email,
                    ],
                    'receiver' => [
                        'name'          => $request->receiver_name,
                        'address_line1' => $request->receiver_address_line1,
                        'address_line2' => $request->receiver_address_line2 ?? '',
                        'city'          => $request->receiver_city,
                        'state'         => $request->receiver_state,
                        'zipcode'       => $request->receiver_zipcode,
                        'country_code'  => strtoupper($request->receiver_country_code),
                        'phone'         => $request->receiver_phone,
                    ],
                    'packages'          => $request->packages,
                    'products'          => $request->products ?? [],
                    'invoice_no'        => $request->invoice_no,
                    'invoice_date'      => $request->invoice_date,
                    'notes'             => $request->notes,
                    'tracking_events'   => [],
                ]);

                if ($request->price) {
                    $this->wallet->deduct(
                        user:        $user,
                        amount:      $request->price,
                        description: ($request->carrier ?? 'Manual') . ' booking #' . $aerotrekId,
                        referenceId: $aerotrekId,
                    );
                }
            });

            return $this->successResponse(
                data: [
                    'aerotrek_id'    => $shipment->aerotrek_id,
                    'status'         => $shipment->status,
                    'wallet_balance' => $user->fresh()->balanceFloat,
                ],
                message: 'Your shipment request has been received. Our team will review and process it within 24 hours.',
                statusCode: 201
            );
        } catch (ShipmentLimitReachedException $e) {
            return response()->json([
                'success'    => false,
                'message'    => $e->getMessage(),
                'error_code' => 'SHIPMENT_LIMIT_REACHED',
            ], 403);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }
}
