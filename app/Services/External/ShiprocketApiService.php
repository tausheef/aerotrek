<?php

namespace App\Services\External;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShiprocketApiService
{
    private string $baseUrl;
    private string $email;
    private string $password;
    private string $pickupLocation;

    public function __construct()
    {
        $this->baseUrl        = config('shiprocket.base_url');
        $this->email          = config('shiprocket.email');
        $this->password       = config('shiprocket.password');
        $this->pickupLocation = config('shiprocket.pickup_location', 'Primary');
    }

    // ──────────────────────────────────────────────────────────────────
    // TOKEN MANAGEMENT
    // ──────────────────────────────────────────────────────────────────

    private function getToken(): string
    {
        $cacheKey = config('shiprocket.token_cache_key', 'shiprocket_api_token');

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $response = Http::post("{$this->baseUrl}/auth/login", [
            'email'    => $this->email,
            'password' => $this->password,
        ]);

        if (! $response->successful()) {
            Log::error('Shiprocket token fetch failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \Exception('Failed to authenticate with Shiprocket.');
        }

        $token = $response->json('token');

        if (! $token) {
            throw new \Exception('Shiprocket login response did not return a token.');
        }

        $ttl = config('shiprocket.token_cache_ttl', 9 * 24 * 60 * 60);
        Cache::put($cacheKey, $token, $ttl);

        Log::info('Shiprocket token refreshed successfully.');

        return $token;
    }

    private function request(string $method, string $endpoint, array $data = []): array
    {
        $token = $this->getToken();

        $response = Http::withToken($token)
            ->acceptJson()
            ->{$method}("{$this->baseUrl}{$endpoint}", $data);

        Log::info("Shiprocket {$method} {$endpoint}", ['status' => $response->status()]);

        if (! $response->successful()) {
            Log::error("Shiprocket error {$endpoint}", [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \Exception('Shiprocket API error: ' . $response->body());
        }

        return $response->json();
    }

    // ──────────────────────────────────────────────────────────────────
    // CREATE SHIPMENT (full flow: order → courier → AWB → label)
    // ──────────────────────────────────────────────────────────────────

    /**
     * Full booking flow for Shiprocket.
     * Auto-detects domestic vs international from receiver country.
     * Returns normalized response matching Overseas API output shape.
     */
    public function createShipment(string $aerotrekId, array $data): array
    {
        $isInternational = strtolower($data['receiver']['country_code'] ?? 'in') !== 'in';

        // Step 1: Create order (domestic or international endpoint)
        $order = $isInternational
            ? $this->createInternationalOrder($aerotrekId, $data)
            : $this->createOrder($aerotrekId, $data);

        $orderId    = $order['order_id'];
        $shipmentId = $order['shipment_id'];

        // Step 2: Get recommended courier and assign AWB
        $courierId = $this->getRecommendedCourierId($shipmentId, $data, $isInternational);
        $awb       = $this->assignAWB($shipmentId, $courierId, $isInternational);

        // Step 3: Generate label / manifest
        $labelUrl = $isInternational
            ? $this->generateInternationalManifest($shipmentId)
            : $this->generateLabel($shipmentId);

        // International also needs a pickup request
        if ($isInternational) {
            $this->generateInternationalPickup($shipmentId);
        }

        return [
            'success'      => true,
            'awb_no'       => $awb,
            'tracking_no'  => $awb,
            'label_url'    => $labelUrl,
            'invoice_url'  => null,
            'order_id'     => $orderId,
            'shipment_id'  => $shipmentId,
            'raw_response' => $order,
        ];
    }

    // ──────────────────────────────────────────────────────────────────
    // INTERNAL STEPS
    // ──────────────────────────────────────────────────────────────────

    private function createOrder(string $aerotrekId, array $data): array
    {
        $nameParts = explode(' ', trim($data['sender']['name']), 2);
        $firstName = $nameParts[0];
        $lastName  = $nameParts[1] ?? '.';

        $totalValue = array_sum(array_map(
            fn($p) => ($p['qty'] ?? 1) * ($p['unit_rate'] ?? 0),
            $data['products']
        ));

        $package = $data['packages'][0]; // use first package for single-piece shipments

        $payload = [
            'order_id'            => $aerotrekId,
            'order_date'          => now()->format('Y-m-d H:i:s'),
            'pickup_location'     => $this->pickupLocation,
            'channel_id'          => '',
            'comment'             => '',

            // Billing (sender)
            'billing_customer_name' => $firstName,
            'billing_last_name'     => $lastName,
            'billing_address'       => $data['sender']['address_line1'],
            'billing_address_2'     => $data['sender']['address_line2'] ?? '',
            'billing_city'          => $data['sender']['city'] ?? 'Delhi',
            'billing_pincode'       => $data['sender']['pincode'],
            'billing_state'         => $data['sender']['state'] ?? 'Delhi',
            'billing_country'       => 'India',
            'billing_email'         => $data['sender']['email'],
            'billing_phone'         => $data['sender']['phone'],

            // Shipping (receiver) — use billing = shipping for most cases
            'shipping_is_billing'       => false,
            'shipping_customer_name'    => $data['receiver']['name'],
            'shipping_last_name'        => '',
            'shipping_address'          => $data['receiver']['address_line1'],
            'shipping_address_2'        => $data['receiver']['address_line2'] ?? '',
            'shipping_city'             => $data['receiver']['city'],
            'shipping_pincode'          => $data['receiver']['zipcode'],
            'shipping_state'            => $data['receiver']['state'],
            'shipping_country'          => $data['receiver']['country_code'] ?? 'India',
            'shipping_email'            => $data['receiver']['email'] ?? '',
            'shipping_phone'            => $data['receiver']['phone'],

            'order_items'         => array_map(fn($p) => [
                'name'          => $p['description'],
                'sku'           => 'SKU-' . substr(md5($p['description']), 0, 8),
                'units'         => $p['qty'],
                'selling_price' => $p['unit_rate'],
                'discount'      => 0,
                'tax'           => $p['igst'] ?? 0,
                'hsn'           => $p['hsn_code'],
            ], $data['products']),

            'payment_method'      => 'Prepaid',
            'shipping_charges'    => 0,
            'giftwrap_charges'    => 0,
            'transaction_charges' => 0,
            'total_discount'      => 0,
            'sub_total'           => $totalValue,

            // Dimensions from first package
            'length'  => $package['length'],
            'breadth' => $package['width'],
            'height'  => $package['height'],
            'weight'  => $package['weight'],
        ];

        $response = $this->request('post', '/orders/create/adhoc', $payload);

        if (empty($response['order_id'])) {
            throw new \Exception('Shiprocket order creation failed: ' . json_encode($response));
        }

        return $response;
    }

    private function createInternationalOrder(string $aerotrekId, array $data): array
    {
        $nameParts = explode(' ', trim($data['sender']['name']), 2);
        $firstName = $nameParts[0];
        $lastName  = $nameParts[1] ?? '.';

        $totalValue = array_sum(array_map(
            fn($p) => ($p['qty'] ?? 1) * ($p['unit_rate'] ?? 0),
            $data['products']
        ));

        $package = $data['packages'][0];

        $payload = [
            'order_id'        => $aerotrekId,
            'order_date'      => now()->format('Y-m-d H:i:s'),
            'pickup_location' => $this->pickupLocation,

            // Billing (sender — always India)
            'billing_customer_name' => $firstName,
            'billing_last_name'     => $lastName,
            'billing_address'       => $data['sender']['address_line1'],
            'billing_address_2'     => $data['sender']['address_line2'] ?? '',
            'billing_city'          => $data['sender']['city'] ?? 'Delhi',
            'billing_pincode'       => $data['sender']['pincode'],
            'billing_state'         => $data['sender']['state'] ?? 'Delhi',
            'billing_country'       => 'India',
            'billing_email'         => $data['sender']['email'],
            'billing_phone'         => $data['sender']['phone'],

            // Shipping (international receiver)
            'shipping_is_billing'    => false,
            'shipping_customer_name' => $data['receiver']['name'],
            'shipping_last_name'     => '',
            'shipping_address'       => $data['receiver']['address_line1'],
            'shipping_address_2'     => $data['receiver']['address_line2'] ?? '',
            'shipping_city'          => $data['receiver']['city'],
            'shipping_pincode'       => $data['receiver']['zipcode'],
            'shipping_state'         => $data['receiver']['state'] ?? '',
            'shipping_country'       => $data['receiver']['country_code'],
            'shipping_email'         => $data['receiver']['email'] ?? '',
            'shipping_phone'         => $data['receiver']['phone'],

            'order_items' => array_map(fn($p) => [
                'name'          => $p['description'],
                'sku'           => 'SKU-' . substr(md5($p['description']), 0, 8),
                'units'         => $p['qty'],
                'selling_price' => $p['unit_rate'],
                'discount'      => 0,
                'tax'           => $p['igst'] ?? 0,
                'hsn'           => $p['hsn_code'] ?? '',
            ], $data['products']),

            'payment_method'      => 'Prepaid',
            'shipping_charges'    => 0,
            'giftwrap_charges'    => 0,
            'transaction_charges' => 0,
            'total_discount'      => 0,
            'sub_total'           => $totalValue,

            'length'  => $package['length'],
            'breadth' => $package['width'],
            'height'  => $package['height'],
            'weight'  => $package['weight'],

            // International-specific fields
            'currency'           => 'INR',
            'reasonOfExport'     => 2,           // Commercial
            'Terms_Of_Invoice'   => 'FOB',
            'purpose_of_shipment'=> 2,           // Commercial
            'ioss'               => $data['ioss']  ?? '',
            'eori'               => $data['eori']  ?? '',
        ];

        $response = $this->request('post', '/international/orders/create/adhoc', $payload);

        if (empty($response['order_id'])) {
            throw new \Exception('Shiprocket international order creation failed: ' . json_encode($response));
        }

        return $response;
    }

    private function getRecommendedCourierId(int $shipmentId, array $data, bool $isInternational = false): int
    {
        $package = $data['packages'][0];

        if ($isInternational) {
            $params = [
                'weight'           => $package['weight'],
                'cod'              => 0,
                'delivery_country' => strtoupper($data['receiver']['country_code']),
                'order_id'         => $shipmentId,
            ];
            $endpoint = '/international/courier/serviceability';
        } else {
            $params = [
                'pickup_postcode'   => $data['sender']['pincode'],
                'delivery_postcode' => $data['receiver']['zipcode'],
                'weight'            => $package['weight'],
                'cod'               => 0,
                'order_id'          => $shipmentId,
            ];
            $endpoint = '/courier/serviceability';
        }

        $response  = $this->request('get', $endpoint, $params);
        $available = $response['data']['available_courier_companies'] ?? [];

        if (empty($available)) {
            throw new \Exception('No couriers available for this route on Shiprocket.');
        }

        // Pick lowest-rate courier
        usort($available, fn($a, $b) => ($a['rate'] ?? 0) <=> ($b['rate'] ?? 0));

        return (int) $available[0]['courier_company_id'];
    }

    private function assignAWB(int $shipmentId, int $courierId, bool $isInternational = false): string
    {
        $endpoint = $isInternational
            ? '/international/courier/assign/awb'
            : '/courier/assign/awb';

        $response = $this->request('post', $endpoint, [
            'shipment_id' => (string) $shipmentId,
            'courier_id'  => (string) $courierId,
        ]);

        $awb = $response['response']['data']['awb_code']
            ?? $response['awb_code']
            ?? null;

        if (! $awb) {
            throw new \Exception('Shiprocket AWB assignment failed: ' . json_encode($response));
        }

        return (string) $awb;
    }

    private function generateLabel(int $shipmentId): ?string
    {
        try {
            $response = $this->request('post', '/courier/generate/label', [
                'shipment_id' => [$shipmentId],
            ]);

            return $response['label_url'] ?? null;
        } catch (\Exception $e) {
            Log::warning('Shiprocket label generation failed', ['shipment_id' => $shipmentId, 'error' => $e->getMessage()]);
            return null;
        }
    }

    private function generateInternationalPickup(int $shipmentId): void
    {
        try {
            // Docs curl shows this endpoint without /v1 — using absolute URL to be safe
            $token    = $this->getToken();
            $response = Http::withToken($token)
                ->acceptJson()
                ->post('https://apiv2.shiprocket.in/v1/external/international/courier/generate/pickup', [
                    'shipment_id' => [$shipmentId],
                ]);

            Log::info('Shiprocket international pickup generated', [
                'shipment_id' => $shipmentId,
                'status'      => $response->status(),
            ]);
        } catch (\Exception $e) {
            Log::warning('Shiprocket international pickup generation failed', [
                'shipment_id' => $shipmentId,
                'error'       => $e->getMessage(),
            ]);
        }
    }

    private function generateInternationalManifest(int $shipmentId): ?string
    {
        try {
            $response = $this->request('post', '/international/manifests/generate', [
                'shipment_id' => [$shipmentId],
            ]);

            return $response['manifest_url'] ?? $response['label_url'] ?? null;
        } catch (\Exception $e) {
            Log::warning('Shiprocket international manifest generation failed', [
                'shipment_id' => $shipmentId,
                'error'       => $e->getMessage(),
            ]);
            return null;
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // TRACKING
    // ──────────────────────────────────────────────────────────────────

    public function trackShipment(string $awbNo): array
    {
        $response = $this->request('get', "/courier/track/awb/{$awbNo}");

        $tracking    = $response['tracking_data'] ?? [];
        $shipment    = $tracking['shipment_track'][0] ?? [];
        $activities  = $tracking['shipment_track_activities'] ?? [];

        if (empty($shipment)) {
            throw new \Exception('Shiprocket tracking returned no data for AWB: ' . $awbNo);
        }

        $events = array_map(fn($activity) => [
            'date'        => substr($activity['date'] ?? '', 0, 10),
            'time'        => substr($activity['date'] ?? '', 11, 5),
            'code'        => null,
            'description' => $activity['activity'] ?? null,
            'location'    => $activity['location'] ?? null,
        ], $activities);

        return [
            'success'     => true,
            'awb_no'      => $awbNo,
            'ship_date'   => $shipment['pickup_date'] ?? null,
            'destination' => $shipment['destination'] ?? null,
            'consignee'   => $shipment['consignee'] ?? null,
            'events'      => $events,
        ];
    }
}
