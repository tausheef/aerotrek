<?php

namespace App\Services\Booking;

use App\Models\Kyc;
use App\Models\Shipment;
use App\Models\User;
use App\Services\External\OverseasApiService;
use App\Services\Shipment\AerotrekIdGenerator;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\Storage;

class ShipmentService
{
    public function __construct(
        private OverseasApiService  $overseas,
        private WalletService       $wallet,
        private AerotrekIdGenerator $idGenerator,
    ) {}

    /**
     * Send DHL OTP — Step 1 for DHL bookings only.
     */
    public function sendDhlOtp(User $user, array $bookingData): array
    {
        $kyc = $this->getUserKyc($user);

        return $this->overseas->sendDhlOtp([
            'shipper_name'      => $user->name,
            'consignee_name'    => $bookingData['receiver_name'],
            'consignee_country' => $bookingData['receiver_country_code'],
            'kyc_type'          => $this->mapKycType($kyc->document_type),
            'kyc_no'            => $kyc->document_number,
            'shipper_phone'     => $user->phone,
            'goods_type'        => $bookingData['goods_type'] === 'Document' ? 'Dox' : 'NDox',
            'kyc_image_base64'  => $this->getKycImageBase64($kyc),
        ]);
    }

    /**
     * Verify DHL OTP — Step 2 for DHL bookings only.
     */
    public function verifyDhlOtp(User $user, array $bookingData, string $otp): array
    {
        $kyc = $this->getUserKyc($user);

        return $this->overseas->verifyDhlOtp([
            'shipper_name'      => $user->name,
            'consignee_name'    => $bookingData['receiver_name'],
            'consignee_country' => $bookingData['receiver_country_code'],
            'kyc_type'          => $this->mapKycType($kyc->document_type),
            'kyc_no'            => $kyc->document_number,
            'shipper_phone'     => $user->phone,
            'goods_type'        => $bookingData['goods_type'] === 'Document' ? 'Dox' : 'NDox',
            'kyc_image_base64'  => $this->getKycImageBase64($kyc),
        ], $otp);
    }

    /**
     * Book shipment — main booking method.
     * Works for all carriers. DHL requires transaction_id from OTP.
     */
    public function book(User $user, array $data): Shipment
    {
        $kyc = $this->getUserKyc($user);

        // Generate Aerotrek ID before anything else — we own this no matter what happens
        $aerotrekId = $this->idGenerator->generate();

        // Check wallet balance
        if ($user->wallet_balance < $data['price']) {
            throw new \Exception('Insufficient wallet balance. Please recharge your wallet.');
        }

        // Build shipment data for Overseas API
        $shipmentData = [
            'sender' => [
                'name'          => $user->name,
                'contact_person'=> $user->name,
                'address_line1' => $data['sender_address_line1'],
                'address_line2' => $data['sender_address_line2'] ?? '',
                'city'          => $data['sender_city'] ?? 'Delhi',
                'state'         => $data['sender_state'] ?? 'DL',
                'pincode'       => $data['sender_pincode'],
                'phone'         => $user->phone,
                'email'         => $user->email,
                'kyc_type'      => $this->mapKycType($kyc->document_type),
                'kyc_no'        => $kyc->document_number,
            ],
            'receiver' => [
                'name'          => $data['receiver_name'],
                'contact_person'=> $data['receiver_contact_person'] ?? $data['receiver_name'],
                'address_line1' => $data['receiver_address_line1'],
                'address_line2' => $data['receiver_address_line2'] ?? '',
                'city'          => $data['receiver_city'],
                'state'         => $data['receiver_state'],
                'zipcode'       => $data['receiver_zipcode'],
                'country_code'  => $data['receiver_country_code'],
                'phone'         => $data['receiver_phone'],
                'email'         => $data['receiver_email'] ?? '',
                'vat_id'        => $data['receiver_vat_id'] ?? '',
            ],
            'service_code'   => $data['service_code'],
            'goods_type'     => $data['goods_type'] === 'Document' ? 'Dox' : 'NDox',
            'package_type'   => $data['package_type'] ?? 'PACKAGE',
            'packages'       => $data['packages'],
            'products'       => $data['products'],
            'invoice_no'     => $data['invoice_no'] ?? 'INV-' . time(),
            'invoice_date'   => $data['invoice_date'] ?? now()->format('Y-m-d\TH:i:s\Z'),
            'invoice_currency'=> 'INR',
            'terms_of_sale'  => $data['terms_of_sale'] ?? 'FOB',
            'reason_for_export' => $data['reason_for_export'] ?? 'GIFT',
            'duty_tax'       => $data['duty_tax'] ?? 'DDU',
            'transaction_id' => $data['transaction_id'] ?? '', // DHL only
            'shipper_kyc_base64' => $this->getKycImageBase64($kyc),
            'kyc_filename'   => $kyc->document_type . '.pdf',
            'freight_charge' => 0,
            'insurance_charge'=> 0,
        ];

        // Call Overseas API
        $response = $this->overseas->createShipment($shipmentData);

        if (! $response['success']) {
            throw new \Exception('Booking failed. Please try again.');
        }

        // Deduct wallet
        $walletTxn = $this->wallet->deduct(
            user:        $user,
            amount:      $data['price'],
            description: "{$data['carrier']} shipment #{$response['awb_no']}",
            referenceId: $response['awb_no']
        );

        // Save shipment to MongoDB
        $shipment = Shipment::create([
            'aerotrek_id'          => $aerotrekId,
            'platform'             => 'overseas',
            'platform_ref_id'      => $response['awb_no'], // Overseas's own reference
            'user_id'              => (string) $user->_id,
            'awb_no'               => $response['awb_no'],
            'tracking_no'          => $response['tracking_no'],
            'carrier'              => $data['carrier'],
            'service_code'         => $data['service_code'],
            'service_name'         => $data['service_name'],
            'network'              => $data['network'],
            'status'               => 'booked',
            'goods_type'           => $data['goods_type'],
            'label_url'            => $response['label_url'],
            'invoice_url'          => $response['invoice_url'],
            'price'                => $data['price'],
            'chargeable_weight'    => $response['chargeable_weight'],
            'sender'               => $shipmentData['sender'],
            'receiver'             => $shipmentData['receiver'],
            'packages'             => $data['packages'],
            'products'             => $data['products'],
            'invoice_no'           => $shipmentData['invoice_no'],
            'invoice_date'         => $shipmentData['invoice_date'],
            'duty_tax'             => $shipmentData['duty_tax'],
            'reason_for_export'    => $shipmentData['reason_for_export'],
            'transaction_id'       => $data['transaction_id'] ?? null,
            'wallet_transaction_id'=> (string) $walletTxn->_id,
            'overseas_response'    => $response['raw_response'],
        ]);

        return $shipment;
    }

    // ── Helpers ───────────────────────────────────────────────────────

    private function getUserKyc(User $user): Kyc
    {
        $kyc = Kyc::where('user_id', (string) $user->_id)
            ->where('status', 'verified')
            ->latest()
            ->first();

        if (! $kyc) {
            throw new \Exception('KYC verification required before booking.');
        }

        return $kyc;
    }

    private function mapKycType(string $documentType): string
    {
        return match ($documentType) {
            'aadhaar'     => 'Aadhaar Number',
            'pan'         => 'PAN Number',
            'gst'         => 'GSTIN (Normal)',
            'company_pan' => 'PAN Number',
            'passport'    => 'Passport Number',
            default       => 'Aadhaar Number',
        };
    }

    private function getKycImageBase64(Kyc $kyc): string
    {
        try {
            if (! $kyc->document_image) return '';

            $driver = config('filesystems.storage_driver', 'local');
            $disk   = $driver === 'r2' ? 'r2' : 'public';
            $content = Storage::disk($disk)->get($kyc->document_image);

            return base64_encode($content);
        } catch (\Exception $e) {
            return '';
        }
    }
}