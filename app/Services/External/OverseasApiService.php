<?php

namespace App\Services\External;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OverseasApiService
{
    private string $baseUrl;
    private string $clientId;
    private string $clientSecret;
    private string $accountCode;

    public function __construct()
    {
        $this->baseUrl      = config('overseas.base_url');
        $this->clientId     = config('overseas.client_id');
        $this->clientSecret = config('overseas.client_secret');
        $this->accountCode  = config('overseas.account_code');
    }

    // ──────────────────────────────────────────────────────────────────
    // TOKEN MANAGEMENT
    // ──────────────────────────────────────────────────────────────────

    /**
     * Get valid token — from cache or fetch new one.
     * Completely silent — user never knows this is happening.
     */
    private function getToken(): string
    {
        $cacheKey = config('overseas.token_cache_key', 'overseas_api_token');

        // Return cached token if valid
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        // Fetch new token
        $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
            ->asForm()
            ->post("{$this->baseUrl}/token", [
                'grant_type' => 'client_credentials',
            ]);

        if (! $response->successful()) {
            Log::error('Overseas API token fetch failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \Exception('Failed to authenticate with Overseas API.');
        }

        $token = $response->json('access_token');

        // Cache for 55 minutes (token valid 60 min)
        $ttl = config('overseas.token_cache_ttl', 55 * 60);
        Cache::put($cacheKey, $token, $ttl);

        Log::info('Overseas API token refreshed successfully.');

        return $token;
    }

    /**
     * Make authenticated request to Overseas API.
     */
    private function request(string $method, string $endpoint, array $data = []): array
    {
        $token = $this->getToken();

        $response = Http::withToken($token)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->{$method}("{$this->baseUrl}{$endpoint}", $data);

        Log::info("Overseas API {$method} {$endpoint}", [
            'status' => $response->status(),
        ]);

        if (! $response->successful()) {
            Log::error("Overseas API error {$endpoint}", [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \Exception("Overseas API error: " . $response->body());
        }

        return $response->json();
    }

    // ──────────────────────────────────────────────────────────────────
    // SERVICE & COUNTRY LIST (cached)
    // ──────────────────────────────────────────────────────────────────

    /**
     * Get available services — cached permanently.
     */
    public function getServiceList(): array
    {
        return Cache::rememberForever('overseas_service_list', function () {
            $response = $this->request('get', '/api/service/list');
            return $response['Data'] ?? [];
        });
    }

    /**
     * Get available countries — cached permanently.
     */
    public function getCountryList(): array
    {
        return Cache::rememberForever('overseas_country_list', function () {
            $response = $this->request('get', '/api/Service/country-list');
            return $response['Data'] ?? [];
        });
    }

    // ──────────────────────────────────────────────────────────────────
    // DHL OTP FLOW
    // ──────────────────────────────────────────────────────────────────

    /**
     * Send OTP to shipper's phone — DHL only.
     * Step 1 of DHL booking flow.
     */
    public function sendDhlOtp(array $data): array
    {
        $payload = [
            'AccountCode'        => $this->accountCode,
            'ShipperName'        => $data['shipper_name'],
            'ConsigneeName'      => $data['consignee_name'],
            'ConsigneeCountry'   => $data['consignee_country'],
            'KYCType'            => $data['kyc_type'],
            'KYCNo'              => $data['kyc_no'],
            'ShipperPhoneNumber' => $data['shipper_phone'],
            'GoodsType'          => $data['goods_type'],         // Dox | NDox
            'ImageBase64String'  => $data['image_base64'] ?? '',
            'KYCImageBase64String' => $data['kyc_image_base64'] ?? '',
        ];

        $response = $this->request('post', '/api/shipment/send-otp', $payload);

        return [
            'success' => $response['Status'] ?? false,
            'message' => $response['Data']['Message'] ?? 'OTP sent.',
        ];
    }

    /**
     * Verify OTP and get TransactionId — DHL only.
     * Step 2 of DHL booking flow.
     */
    public function verifyDhlOtp(array $data, string $otp): array
    {
        $payload = [
            'AccountCode'          => $this->accountCode,
            'ShipperName'          => $data['shipper_name'],
            'ConsigneeName'        => $data['consignee_name'],
            'ConsigneeCountry'     => $data['consignee_country'],
            'KYCType'              => $data['kyc_type'],
            'KYCNo'                => $data['kyc_no'],
            'ShipperPhoneNumber'   => $data['shipper_phone'],
            'GoodsType'            => $data['goods_type'],
            'ImageBase64String'    => $data['image_base64'] ?? '',
            'KYCImageBase64String' => $data['kyc_image_base64'] ?? '',
            'OTP'                  => $otp,
        ];

        $response = $this->request('post', '/api/shipment/verify-otp', $payload);

        // Extract TransactionId from message
        // Response: { "Message": "\"Transaction ID:66614568.\"" }
        $message       = $response['Data']['Message'] ?? '';
        $transactionId = null;

        if (preg_match('/Transaction ID[:\s]+(\d+)/i', $message, $matches)) {
            $transactionId = $matches[1];
        }

        return [
            'success'        => $response['Status'] ?? false,
            'transaction_id' => $transactionId,
            'message'        => $message,
        ];
    }

    // ──────────────────────────────────────────────────────────────────
    // CREATE SHIPMENT
    // ──────────────────────────────────────────────────────────────────

    /**
     * Create shipment — works for ALL carriers.
     * DHL requires transaction_id from OTP verification.
     */
    public function createShipment(array $shipmentData): array
    {
        $payload = [
            'AccountCode' => $this->accountCode,

            'Sender' => [
                'SenderName'          => $shipmentData['sender']['name'],
                'SenderContactPerson' => $shipmentData['sender']['contact_person'] ?? $shipmentData['sender']['name'],
                'SenderAddressLine1'  => $shipmentData['sender']['address_line1'],
                'SenderAddressLine2'  => $shipmentData['sender']['address_line2'] ?? '',
                'SenderAddressLine3'  => $shipmentData['sender']['address_line3'] ?? '',
                'SenderPincode'       => $shipmentData['sender']['pincode'],
                'SenderCity'          => $shipmentData['sender']['city'],
                'SenderState'         => $shipmentData['sender']['state'],
                'SenderTelephone'     => $shipmentData['sender']['phone'],
                'SenderEmailId'       => $shipmentData['sender']['email'],
                'KYCType'             => $shipmentData['sender']['kyc_type'],
                'KYCNo'               => $shipmentData['sender']['kyc_no'],
            ],

            'Receiver' => [
                'ReceiverName'          => $shipmentData['receiver']['name'],
                'ReceiverContactPerson' => $shipmentData['receiver']['contact_person'] ?? $shipmentData['receiver']['name'],
                'ReceiverAddressLine1'  => $shipmentData['receiver']['address_line1'],
                'ReceiverAddressLine2'  => $shipmentData['receiver']['address_line2'] ?? '',
                'ReceiverAddressLine3'  => $shipmentData['receiver']['address_line3'] ?? '',
                'ReceiverZipcode'       => $shipmentData['receiver']['zipcode'],
                'ReceiverCity'          => $shipmentData['receiver']['city'],
                'ReceiverState'         => $shipmentData['receiver']['state'],
                'ReceiverCountry'       => $shipmentData['receiver']['country_code'], // ISO 2 letter
                'ReceiverTelephone'     => $shipmentData['receiver']['phone'],
                'ReceiverEmailid'       => $shipmentData['receiver']['email'],
                'VatId'                 => $shipmentData['receiver']['vat_id'] ?? '',
            ],

            'ServiceDetails' => [
                'Service'     => $shipmentData['service_code'],  // DHL_EXPRESS, UPS_SAVER etc
                'GoodsType'   => $shipmentData['goods_type'],    // Dox | NDox
                'PackageType' => $shipmentData['package_type'] ?? 'PACKAGE',
            ],

            'PackageDetails' => [
                'PackageDetail' => array_map(fn($pkg) => [
                    'Length'       => $pkg['length'],
                    'Width'        => $pkg['width'],
                    'Height'       => $pkg['height'],
                    'ActualWeight' => $pkg['weight'],
                ], $shipmentData['packages']),
            ],

            'AdditionalDetails' => [
                'ProductDetails' => array_map(fn($item) => [
                    'BoxNo'          => $item['box_no'] ?? '1',
                    'Description'    => $item['description'],
                    'HSNCode'        => $item['hsn_code'],
                    'UnitType'       => $item['unit_type'] ?? 'PCS',
                    'Qty'            => $item['qty'],
                    'UnitRate'       => $item['unit_rate'],
                    'ShipPieceIGST'  => $item['igst'] ?? 0,
                    'PieceWt'        => $item['piece_weight'] ?? 0,
                ], $shipmentData['products']),

                'InvoiceCurrency'     => $shipmentData['invoice_currency'] ?? 'INR',
                'InvoiceNo'           => $shipmentData['invoice_no'],
                'InvoiceDate'         => $shipmentData['invoice_date'],
                'TermsOfSale'         => $shipmentData['terms_of_sale'] ?? 'FOB',
                'ReasonForExport'     => $shipmentData['reason_for_export'] ?? 'GIFT',
                'FreightCharge'       => $shipmentData['freight_charge'] ?? 0,
                'InsuranceCharge'     => $shipmentData['insurance_charge'] ?? 0,
                'CSB_Type'            => $shipmentData['csb_type'] ?? 'CSB 4',
                'CustomerRefNo'       => $shipmentData['customer_ref_no'] ?? '',
                'DeliveryConfirmation'=> $shipmentData['delivery_confirmation'] ?? 'Email',
                'DutyTax'             => $shipmentData['duty_tax'] ?? 'DDU',
                'DutiesAccountNo'     => $shipmentData['duties_account_no'] ?? '',
                'TransactionId'       => $shipmentData['transaction_id'] ?? '', // DHL only
                'ShipperImage'        => $shipmentData['shipper_image'] ?? '',
                'ShipperKYC'          => $shipmentData['shipper_kyc_base64'] ?? '',
                'FileName'            => $shipmentData['kyc_filename'] ?? '',
            ],
        ];

        $response = $this->request('post', '/api/shipment/create', $payload);

        if (! ($response['Status'] ?? false)) {
            throw new \Exception('Shipment creation failed: ' . ($response['Error'] ?? 'Unknown error'));
        }

        $data = $response['Data'] ?? [];
        $tracking = $data['TrackingNumbers'][0] ?? [];

        return [
            'success'       => true,
            'awb_no'        => $data['AwbNo'] ?? null,
            'tracking_no'   => $tracking['TrackingNo'] ?? null,
            'label_url'     => $tracking['LabelUrl'] ?? null,
            'invoice_url'   => $tracking['InvoiceUrl'] ?? null,
            'destination'   => $data['Destination'] ?? null,
            'chargeable_weight' => $data['ChargeableWeight'] ?? null,
            'total_charges' => $data['TotalCharges'] ?? null,
            'raw_response'  => $response,
        ];
    }

    // ──────────────────────────────────────────────────────────────────
    // TRACKING
    // ──────────────────────────────────────────────────────────────────

    /**
     * Track shipment by AWB number.
     */
    public function trackShipment(string $awbNo): array
    {
        $response = $this->request('get', "/api/tracking/{$awbNo}");

        if (! ($response['Status'] ?? false)) {
            throw new \Exception('Tracking failed for AWB: ' . $awbNo);
        }

        $data   = $response['Data'] ?? [];
        $events = $data['Events'] ?? [];

        return [
            'success'      => true,
            'awb_no'       => $data['Awbno'] ?? $awbNo,
            'ship_date'    => $data['Shipdate'] ?? null,
            'destination'  => $data['Destination'] ?? null,
            'consignee'    => $data['Consignee'] ?? null,
            'forwarder'    => $data['Forwarder'] ?? null,
            'forwarding_no'=> $data['ForwardingNo'] ?? null,
            'events'       => array_map(fn($event) => [
                'date'        => $event['EventDate'] ?? null,
                'time'        => $event['EventTime'] ?? null,
                'code'        => $event['EventCode'] ?? null,
                'description' => $event['EventDescription'] ?? null,
                'location'    => $event['Location'] ?? null,
            ], $events),
        ];
    }
}