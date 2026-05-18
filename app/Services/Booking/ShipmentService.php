<?php

namespace App\Services\Booking;

use App\Exceptions\PlatformDisabledException;
use App\Models\Kyc;
use App\Models\Shipment;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\External\OverseasApiService;
use App\Services\External\ShiprocketApiService;
use App\Services\Shipment\AerotrekIdGenerator;
use App\Services\Shipment\AerotrekInvoiceService;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ShipmentService
{
    public function __construct(
        private OverseasApiService   $overseas,
        private ShiprocketApiService $shiprocket,
        private WalletService        $wallet,
        private AerotrekIdGenerator  $idGenerator,
        private AerotrekInvoiceService $invoiceService,
    ) {}

    /**
     * Send DHL OTP — Step 1 for DHL bookings only.
     */
    public function sendDhlOtp(User $user, array $bookingData): array
    {
        if (SiteSetting::get('platform_overseas_enabled', '1') !== '1') {
            throw new PlatformDisabledException('Overseas API');
        }

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
        if (SiteSetting::get('platform_overseas_enabled', '1') !== '1') {
            throw new PlatformDisabledException('Overseas API');
        }

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
     * Book shipment — routes to Overseas or Shiprocket automatically.
     */
    public function book(User $user, array $data): Shipment
    {
        $platform = $this->resolvePlatform($data);

        $this->checkPlatformEnabled($platform);

        $kyc        = $this->getUserKyc($user);
        $aerotrekId = $this->idGenerator->generate($user);

        $sender = [
            'user_id'        => $user->id,
            'name'           => $user->name,
            'contact_person' => $user->name,
            'address_line1'  => $data['sender_address_line1'],
            'address_line2'  => $data['sender_address_line2'] ?? '',
            'city'           => $data['sender_city'] ?? 'Delhi',
            'state'          => $data['sender_state'] ?? 'DL',
            'pincode'        => $data['sender_pincode'],
            'phone'          => $user->phone,
            'email'          => $user->email,
            'kyc_type'       => $this->mapKycType($kyc->document_type),
            'kyc_no'         => $kyc->document_number,
        ];

        $receiver = [
            'name'           => $data['receiver_name'],
            'contact_person' => $data['receiver_contact_person'] ?? $data['receiver_name'],
            'address_line1'  => $data['receiver_address_line1'],
            'address_line2'  => $data['receiver_address_line2'] ?? '',
            'city'           => $data['receiver_city'],
            'state'          => $data['receiver_state'] ?? '',
            'zipcode'        => $data['receiver_zipcode'],
            'country_code'   => $data['receiver_country_code'],
            'phone'          => $data['receiver_phone'],
            'email'          => $data['receiver_email'] ?? '',
            'vat_id'         => $data['receiver_vat_id'] ?? '',
            'isd_code'       => $data['receiver_isd_code'] ?? '',
        ];

        // Pre-save shipment before touching any external API.
        // If the carrier call fails, we update this record to 'failed'.
        // If the payment step fails after a successful carrier call, this record
        // stays 'pending' so an admin can investigate rather than losing it entirely.
        $shipment = Shipment::create([
            'aerotrek_id'       => $aerotrekId,
            'booking_type'      => 'auto',
            'platform'          => $platform,
            'user_id'           => $user->id,
            'carrier'           => $data['carrier'],
            'service_code'      => $data['service_code'],
            'service_name'      => $data['service_name'],
            'network'           => $data['network'],
            'status'            => 'pending',
            'goods_type'        => $data['goods_type'],
            'price'             => $data['price'],
            'sender'            => $sender,
            'receiver'          => $receiver,
            'packages'          => $data['packages'],
            'products'          => $data['products'],
            'invoice_no'        => $data['invoice_no'] ?? $aerotrekId,
            'invoice_date'      => $data['invoice_date'] ?? now()->format('Y-m-d\TH:i:s\Z'),
            'invoice_currency'  => strtoupper($data['invoice_currency'] ?? 'INR'),
            'terms_of_sale'     => $data['terms_of_sale'] ?? 'FOB',
            'csb_type'          => $data['csb_type'] ?? 'CSB 4',
            'duty_tax'          => $data['duty_tax'] ?? 'DDU',
            'reason_for_export' => $data['reason_for_export'] ?? 'GIFT',
            'transaction_id'    => $data['transaction_id'] ?? null,
        ]);

        // Call carrier API — on failure mark shipment failed and bail out.
        // No wallet is touched at this point.
        try {
            $response = $platform === 'shiprocket'
                ? $this->bookViaShiprocket($aerotrekId, $data, $sender, $receiver)
                : $this->bookViaOverseas($data, $sender, $receiver, $kyc, $aerotrekId, $user);
        } catch (\Exception $e) {
            $shipment->update(['status' => 'failed']);
            Log::error('Carrier booking failed', [
                'platform'    => $platform,
                'aerotrek_id' => $aerotrekId,
                'user_id'     => $user->id,
                'error'       => $e->getMessage(),
            ]);

            // Surface the carrier's actual message when it's a clean upstream
            // message (e.g. "Courier is facing some issues, Please try after
            // sometime."), so the user knows to retry vs. bail.
            $msg = $e->getMessage();
            if (str_starts_with($msg, 'Shiprocket:') || str_starts_with($msg, 'Overseas:')) {
                throw new \Exception($msg);
            }

            throw new \Exception('Carrier booking failed. Please try again, choose a different service, or use Manual Booking.');
        }

        // Wallet deduction + shipment confirmation must succeed or fail together.
        // If this transaction fails after a successful carrier call the shipment
        // remains 'pending' — logged as critical for admin follow-up.
        try {
            DB::transaction(function () use ($shipment, $user, $data, $response, $platform) {
                $walletTxn = $this->wallet->deduct(
                    user:        $user,
                    amount:      $data['price'],
                    description: "{$data['carrier']} shipment #{$response['awb_no']}",
                    referenceId: $response['awb_no']
                );

                $updateFields = [
                    'status'                => 'booked',
                    'awb_no'                => $response['awb_no'],
                    'tracking_no'           => $response['tracking_no'],
                    'label_url'             => $response['label_url'],
                    'invoice_url'           => $response['invoice_url'],
                    'chargeable_weight'     => $response['chargeable_weight'] ?? null,
                    'platform_ref_id'       => $response['platform_ref_id'],
                    'wallet_transaction_id' => (string) $walletTxn->id,
                ];

                if ($platform === 'shiprocket') {
                    $updateFields['shiprocket_response'] = $response['raw_response'];
                } else {
                    $updateFields['overseas_response'] = $response['raw_response'];
                }

                $shipment->update($updateFields);
            });
        } catch (\Exception $e) {
            Log::critical('Auto booking: carrier succeeded but payment/DB failed', [
                'shipment_id' => $shipment->id,
                'aerotrek_id' => $aerotrekId,
                'awb_no'      => $response['awb_no'],
                'user_id'     => $user->id,
                'error'       => $e->getMessage(),
            ]);
            throw new \Exception('Shipment was created with the carrier but payment processing failed. Our support team has been notified.');
        }

        $shipment->refresh();

        // Generate AeroTrek invoice PDF — non-fatal if it fails
        $invoiceUrl = $this->invoiceService->generate($shipment);
        if ($invoiceUrl) {
            $shipment->update(['invoice_url' => $invoiceUrl]);
            $shipment->refresh();
        }

        return $shipment;
    }

    // ── Platform routing ──────────────────────────────────────────────

    /**
     * Decide which platform to use based on carrier and network type.
     * This is internal — never expose 'platform' to the user.
     */
    private function resolvePlatform(array $data): string
    {
        // Primary: service_type set by frontend
        $serviceType = strtolower($data['service_type'] ?? '');

        if (in_array($serviceType, ['ecommerce', 'india_post'])) {
            return 'shiprocket';
        }

        // Secondary: network field from the selected rate
        if (strtoupper($data['network'] ?? '') === 'SHIPROCKET') {
            return 'shiprocket';
        }

        // Tertiary: carrier name list
        if (in_array($data['carrier'] ?? '', config('shiprocket.carriers', []))) {
            return 'shiprocket';
        }

        return 'overseas';
    }

    private function checkPlatformEnabled(string $platform): void
    {
        if ($platform === 'overseas' && SiteSetting::get('platform_overseas_enabled', '1') !== '1') {
            throw new PlatformDisabledException('Overseas API');
        }

        if ($platform === 'shiprocket' && SiteSetting::get('platform_shiprocket_enabled', '1') !== '1') {
            throw new PlatformDisabledException('Shiprocket');
        }
    }

    // ── Platform-specific booking calls ──────────────────────────────

    private function bookViaShiprocket(string $aerotrekId, array $data, array $sender, array $receiver): array
    {
        $response = $this->shiprocket->createShipment($aerotrekId, array_merge($data, [
            'sender'             => $sender,
            'receiver'           => $receiver,
            'courier_company_id' => $data['courier_company_id'] ?? null,
        ]));

        if (! $response['success']) {
            throw new \Exception('Shiprocket booking failed. Please try again.');
        }

        return [
            'awb_no'           => $response['awb_no'],
            'tracking_no'      => $response['tracking_no'],
            'label_url'        => $response['label_url'],
            'invoice_url'      => null,
            'chargeable_weight'=> null,
            'platform_ref_id'  => (string) ($response['order_id'] ?? $response['awb_no']),
            'raw_response'     => $response['raw_response'],
        ];
    }

    private function bookViaOverseas(array $data, array $sender, array $receiver, $kyc, string $aerotrekId, User $user): array
    {
        $products = $data['products'] ?? [];

        $invoiceCurrency = strtoupper($data['invoice_currency'] ?? 'INR');
        $threshold       = $user->account_type === 'company' ? 50000 : 25000;

        if (isset($data['total_value_inr']) && $data['total_value_inr'] !== null) {
            // Frontend already converted to INR — most accurate path
            $totalValue = (float) $data['total_value_inr'];
            $csbType    = $totalValue > $threshold ? 'CSB 5' : ($data['csb_type'] ?? 'CSB 4');
        } elseif ($invoiceCurrency === 'INR') {
            // INR invoice — safe to sum directly
            $totalValue = collect($products)->sum(fn($p) => ($p['qty'] ?? 1) * ($p['unit_rate'] ?? 0));
            $csbType    = $totalValue > $threshold ? 'CSB 5' : ($data['csb_type'] ?? 'CSB 4');
        } else {
            // Non-INR without exchange rate — cannot verify threshold, force CSB 5 (safe default)
            $csbType = 'CSB 5';
        }

        $shipmentData = [
            'sender'                => $sender,
            'receiver'              => $receiver,
            'service_code'          => $data['service_code'],
            'goods_type'            => $data['goods_type'] === 'Document' ? 'Dox' : 'NDox',
            'package_type'          => $data['package_type'] ?? 'PACKAGE',
            'packages'              => $data['packages'],
            'products'              => $products,
            'invoice_no'            => $data['invoice_no'] ?? 'INV-' . time(),
            'invoice_date'          => isset($data['invoice_date'])
                ? \Carbon\Carbon::parse($data['invoice_date'])->format('Y-m-d\TH:i:s\Z')
                : now()->format('Y-m-d\TH:i:s\Z'),
            'invoice_currency'      => strtoupper($data['invoice_currency'] ?? 'INR'),
            'terms_of_sale'         => $data['terms_of_sale'] ?? 'FOB',
            'reason_for_export'     => $data['reason_for_export'] ?? 'GIFT',
            'duty_tax'              => $data['duty_tax'] ?? 'DDU',
            'duties_account_no'     => '',
            'transaction_id'        => $data['transaction_id'] ?? '',
            'shipper_kyc_base64'    => $this->getKycImageBase64($kyc),
            'kyc_filename'          => $kyc->document_type . '.pdf',
            'freight_charge'        => 0,
            'insurance_charge'      => 0,
            'csb_type'              => $csbType,
            'customer_ref_no'       => $aerotrekId,
            'delivery_confirmation' => 'Email',
        ];

        $response = $this->overseas->createShipment($shipmentData);

        if (! $response['success']) {
            throw new \Exception('Booking failed. Please try again.');
        }

        return [
            'awb_no'           => $response['awb_no'],
            'tracking_no'      => $response['tracking_no'],
            'label_url'        => $response['label_url'],
            'invoice_url'      => $response['invoice_url'],
            'chargeable_weight'=> $response['chargeable_weight'],
            'platform_ref_id'  => $response['awb_no'],
            'raw_response'     => $response['raw_response'],
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────

    private function getUserKyc(User $user): Kyc
    {
        $kyc = Kyc::where('user_id', $user->id)
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

            $driver  = config('filesystems.storage_driver', 'local');
            $disk    = $driver === 'r2' ? 'r2' : 'public';
            $content = Storage::disk($disk)->get($kyc->document_image);

            return base64_encode($content);
        } catch (\Exception $e) {
            return '';
        }
    }
}
