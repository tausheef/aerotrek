<?php

namespace App\Http\Controllers\API\V1\Booking;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Services\Shipment\AerotrekIdGenerator;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class ManualBookingController extends Controller
{
    use ApiResponse;

    public function __construct(
        private AerotrekIdGenerator $idGenerator
    ) {}

    /**
     * POST /api/v1/shipments/manual-book
     * User submits shipment details. No carrier API call, no wallet deduction.
     * AeroTrek staff will manually book on carrier and update tracking.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'goods_type'              => ['required', 'in:Document,Non-Document'],
            'reason_for_export'       => ['required', 'in:GIFT,SALE,SAMPLE,RETURN,PERSONAL_USE'],
            'duty_tax'                => ['required', 'in:DDU,DDP'],

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

        $goodsType = $request->goods_type === 'Document' ? 'Dox' : 'NDox';

        $shipment = Shipment::create([
            'aerotrek_id'     => $this->idGenerator->generate(),
            'booking_type'    => 'manual',
            'platform'        => null,
            'user_id'         => $user->id,
            'status'          => 'pending_acceptance',
            'goods_type'      => $goodsType,
            'reason_for_export' => $request->reason_for_export,
            'duty_tax'        => $request->duty_tax,
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
            'packages'        => $request->packages,
            'products'        => $request->products ?? [],
            'invoice_no'      => $request->invoice_no,
            'invoice_date'    => $request->invoice_date,
            'notes'           => $request->notes,
            'tracking_events' => [],
        ]);

        return $this->successResponse(
            data: [
                'aerotrek_id' => $shipment->aerotrek_id,
                'status'      => $shipment->status,
            ],
            message: 'Your shipment request has been received. Our team will review and process it within 24 hours.',
            statusCode: 201
        );
    }
}
