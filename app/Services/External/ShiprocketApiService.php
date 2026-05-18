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

    public function createShipment(string $aerotrekId, array $data): array
    {
        $isInternational = strtolower($data['receiver']['country_code'] ?? 'in') !== 'in';

        $order = $isInternational
            ? $this->createInternationalOrder($aerotrekId, $data)
            : $this->createOrder($aerotrekId, $data);

        $orderId    = $order['order_id'];
        $shipmentId = $order['shipment_id'];

        $courierId = $this->getRecommendedCourierId($shipmentId, $data, $isInternational);
        $awb       = $this->assignAWB($shipmentId, $courierId, $isInternational);

        // For international: BOTH the carrier label AND the customs manifest are needed.
        // generateLabel → courier AWB label (the shipping sticker)
        // generateInternationalManifest → customs document
        $labelUrl = $this->generateLabel($shipmentId);

        $manifestUrl = null;
        if ($isInternational) {
            $manifestUrl = $this->generateInternationalManifest($shipmentId);
            $this->generateInternationalPickup($shipmentId);
        }

        return [
            'success'      => true,
            'awb_no'       => $awb,
            'tracking_no'  => $awb,
            'label_url'    => $labelUrl,
            'manifest_url' => $manifestUrl,
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

        $package = $data['packages'][0];
        $pickup  = $this->getOrRegisterPickupLocation($data['sender']);

        $payload = [
            'order_id'            => $aerotrekId,
            'order_date'          => now()->format('Y-m-d H:i:s'),
            'pickup_location'     => $pickup['name'],
            'channel_id'          => '',
            'comment'             => '',

            'billing_customer_name' => $firstName,
            'billing_last_name'     => $lastName,
            'billing_address'       => $data['sender']['address_line1'],
            'billing_address_2'     => $data['sender']['address_line2'] ?? '',
            'billing_city'          => $data['sender']['city']  ?: 'Delhi',
            'billing_pincode'       => $data['sender']['pincode'],
            'billing_state'         => $data['sender']['state'] ?: 'Delhi',
            'billing_country'       => 'India',
            'billing_email'         => $data['sender']['email'],
            'billing_phone'         => $data['sender']['phone'],

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

            'order_items' => array_map(fn($p) => [
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
        $nameParts     = explode(' ', trim($data['sender']['name']), 2);
        $firstName     = $nameParts[0];
        $lastName      = $nameParts[1] ?? '.';
        $receiverParts = explode(' ', trim($data['receiver']['name']), 2);

        $totalValue = array_sum(array_map(
            fn($p) => ($p['qty'] ?? 1) * ($p['unit_rate'] ?? 0),
            $data['products']
        ));

        $package = $data['packages'][0];
        // Pickup resolution order:
        //  1. The saved Address record's verified Shiprocket pickup (preferred —
        //     the user verified it via OTP after adding their address).
        //  2. Dynamic registration as a last resort, fallback to Primary if pending.
        $pickup = $this->resolvePickupForBooking($data['sender']);

        $payload = [
            'order_id'               => $aerotrekId,
            'isd_code'               => $data['receiver']['isd_code'] ?? '',
            'billing_isd_code'       => '+91',
            'order_date'             => now()->toIso8601String(),
            'channel_id'             => '',
            'pickup_location_id'     => $pickup['id'],

            'billing_customer_name'  => $firstName,
            'billing_last_name'      => $lastName,
            'billing_address'        => $this->normalizeAddressForShiprocket($data['sender']['address_line1']),
            'billing_address_2'      => $data['sender']['address_line2'] ?? '',
            'billing_city'           => $data['sender']['city']  ?: 'Delhi',
            'billing_pincode'        => $data['sender']['pincode'],
            'billing_state'          => $data['sender']['state'] ?: 'Delhi',
            'billing_country'        => 'India',
            'billing_email'          => $data['sender']['email'],
            'billing_phone'          => $data['sender']['phone'],
            'billing_alternate_phone'=> '',
            'landmark'               => '',

            'shipping_is_billing'    => 0,
            'shipping_customer_name' => $receiverParts[0],
            'shipping_last_name'     => $receiverParts[1] ?? '',
            'shipping_address'       => $this->normalizeAddressForShiprocket($data['receiver']['address_line1']),
            'shipping_address_2'     => $data['receiver']['address_line2'] ?? '',
            'shipping_city'          => $data['receiver']['city'],
            'shipping_pincode'       => $data['receiver']['zipcode'],
            'shipping_state'         => $data['receiver']['state'] ?? '',
            'shipping_country'       => $this->resolveCountryName($data['receiver']['country_code']),
            'shipping_email'         => $data['receiver']['email'] ?? '',
            'shipping_phone'         => $data['receiver']['phone'],

            'order_items' => array_map(fn($p) => [
                'name'            => $p['description'],
                'sku'             => 'SKU-' . substr(md5($p['description']), 0, 8),
                'units'           => (string) ($p['qty'] ?? 1),
                'selling_price'   => (string) ($p['unit_rate'] ?? 0),
                'discount'        => '',
                'tax'             => (string) ($p['igst'] ?? ''),
                'hsn'             => $p['hsn_code'] ?? '',
                'category_name'   => 'Default Category',
                'product_category'=> '',
                'category_id'     => '',
                'category_code'   => '',
            ], $data['products']),

            'payment_method'      => 'Prepaid',
            'shipping_charges'    => 0,
            'giftwrap_charges'    => 0,
            'transaction_charges' => 0,
            'total_discount'      => 0,
            'sub_total'           => $totalValue,

            'weight'  => $package['weight'],
            'length'  => $package['length'],
            'breadth' => $package['width'],
            'height'  => $package['height'],

            'is_order_revamp'     => 1,
            'is_document'         => ($data['goods_type'] ?? 'Non-Document') === 'Document' ? 1 : 0,
            'order_type'          => 1,
            'delivery_challan'    => false,
            'order_tag'           => '',
            'is_insurance_opt'    => (bool) ($data['is_insurance_opt'] ?? false),

            'purpose_of_shipment' => (int) ($data['purpose_of_shipment'] ?? 0),
            'currency'            => strtoupper($data['invoice_currency'] ?? 'INR'),
            'reasonOfExport'      => (int) ($data['reason_of_export_code'] ?? 2),
            'ioss'                => $data['ioss'] ?? '',
            'eori'                => $data['eori'] ?? '',
        ];

        $response = $this->request('post', '/international/orders/create/adhoc', $payload);

        if (empty($response['order_id'])) {
            throw new \Exception('Shiprocket international order creation failed: ' . json_encode($response));
        }

        return $response;
    }

    // ──────────────────────────────────────────────────────────────────
    // PICKUP LOCATION MANAGEMENT
    // ──────────────────────────────────────────────────────────────────

    /**
     * Register a saved Address as a Shiprocket pickup location.
     * Shiprocket auto-sends phone OTP + email verification link on creation.
     * Returns ['name' => ..., 'id' => ..., 'verified' => bool].
     */
    public function registerPickupForAddress(array $address): array
    {
        $safeName     = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $address['full_name'] ?? $address['name'] ?? 'USER'), 0, 8));
        $locationName = 'ATK_' . ($address['pincode'] ?? '') . '_' . $safeName . '_' . substr(md5((string) ($address['id'] ?? uniqid())), 0, 4);

        try {
            $this->request('post', '/settings/company/addpickup', [
                'pickup_location' => $locationName,
                'name'            => $address['full_name'] ?? $address['name'] ?? '',
                'email'           => $address['email'] ?? '',
                'phone'           => $address['phone'] ?? '',
                'address'         => $address['address_line1'] ?? '',
                'address_2'       => $address['address_line2'] ?? '',
                'city'            => $address['city'] ?? '',
                'state'           => $address['state'] ?? '',
                'country'         => $address['country'] ?? 'India',
                'pin_code'        => $address['pincode'] ?? '',
            ]);
            Log::info('Shiprocket pickup registered for address', ['name' => $locationName]);
        } catch (\Exception $e) {
            // If pickup name already exists or any other issue, we'll still try the lookup
            Log::info('Shiprocket pickup registration error', ['name' => $locationName, 'error' => $e->getMessage()]);
        }

        // Look up to get the actual ID + verification status
        return $this->lookupPickupByName($locationName);
    }

    /**
     * Verify a pickup location's phone via OTP that Shiprocket sent on creation.
     * Returns true on success.
     */
    public function verifyPickupOtp(int $pickupId, string $otp): bool
    {
        $response = $this->request('post', '/settings/company/verify-pickup-address', [
            'pickup_id' => $pickupId,
            'otp'       => $otp,
        ]);

        $success = ($response['success'] ?? null) === true
                || ($response['status'] ?? null) === 200
                || isset($response['message']) && stripos((string) $response['message'], 'verified') !== false;

        Log::info('Shiprocket pickup OTP verify', [
            'pickup_id' => $pickupId,
            'success'   => $success,
            'response'  => $response,
        ]);

        return $success;
    }

    /**
     * Ask Shiprocket to re-send the OTP for a pending pickup location.
     */
    public function resendPickupOtp(int $pickupId): bool
    {
        $response = $this->request('post', '/settings/company/resend-pickup-otp', [
            'pickup_id' => $pickupId,
        ]);

        Log::info('Shiprocket pickup OTP resend', [
            'pickup_id' => $pickupId,
            'response'  => $response,
        ]);

        return ($response['success'] ?? null) === true || ($response['status'] ?? null) === 200;
    }

    /**
     * Look up a pickup by its location name and return current state.
     */
    public function lookupPickupByName(string $locationName): array
    {
        $response  = $this->request('get', '/settings/company/pickup');
        $locations = $response['data']['shipping_address']
                  ?? $response['data']
                  ?? [];

        foreach ($locations as $loc) {
            if (! is_array($loc)) continue;
            if (strtolower(trim($loc['pickup_location'] ?? '')) === strtolower($locationName)) {
                // Shiprocket: status=1 means active. phone_verified=1 is required;
                // email_verified may be null/0 — not required for international shipping.
                $verified =
                    ((int) ($loc['status']         ?? 0)) === 1
                    && ((int) ($loc['phone_verified'] ?? 0)) === 1;

                Log::info('Shiprocket pickup lookup', [
                    'name'           => $locationName,
                    'id'             => $loc['id'] ?? null,
                    'status'         => $loc['status'] ?? null,
                    'phone_verified' => $loc['phone_verified'] ?? null,
                    'email_verified' => $loc['email_verified'] ?? null,
                    'verified'       => $verified,
                ]);

                return [
                    'name'     => $loc['pickup_location'],
                    'id'       => (int) $loc['id'],
                    'verified' => $verified,
                ];
            }
        }

        throw new \Exception("Could not find Shiprocket pickup location '{$locationName}' after registration.");
    }

    /**
     * Refresh a known pickup's verification status from Shiprocket.
     * Used when the user verified via the SMS/email link instead of OTP API.
     */
    public function refreshPickupStatus(string $locationName): array
    {
        return $this->lookupPickupByName($locationName);
    }

    /**
     * Register sender address as a Shiprocket pickup location if not already registered.
     * Returns ['name' => '...', 'id' => 123].
     * Cached 30 days per unique pincode+name so repeat bookings skip the API call.
     */
    private function getOrRegisterPickupLocation(array $sender): array
    {
        $safeName     = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $sender['name']), 0, 8));
        $locationName = 'ATK_' . $sender['pincode'] . '_' . $safeName;
        $cacheKey     = 'shiprocket_pickup_' . md5($locationName);

        // Only verified pickups (status=1) are cached — unverified ones must be re-checked
        if ($cached = Cache::get($cacheKey)) {
            Log::info('Shiprocket pickup location from cache', ['name' => $locationName]);
            return $cached;
        }

        // Try to register; if it already exists Shiprocket returns an error — handled below
        try {
            $this->request('post', '/settings/company/addpickup', [
                'pickup_location' => $locationName,
                'name'            => $sender['name'],
                'email'           => $sender['email'],
                'phone'           => $sender['phone'],
                'address'         => $sender['address_line1'],
                'address_2'       => $sender['address_line2'] ?? '',
                'city'            => $sender['city'],
                'state'           => $sender['state'],
                'country'         => 'India',
                'pin_code'        => $sender['pincode'],
            ]);
            Log::info('Shiprocket pickup location registered', ['name' => $locationName]);
        } catch (\Exception $e) {
            Log::info('Shiprocket pickup registration skipped (may already exist)', [
                'name'  => $locationName,
                'error' => $e->getMessage(),
            ]);
        }

        // Look up the pickup to get its CURRENT id + verification status
        $response  = $this->request('get', '/settings/company/pickup');
        $locations = $response['data']['shipping_address']
                  ?? $response['data']
                  ?? [];

        $found = null;
        foreach ($locations as $loc) {
            if (! is_array($loc)) continue;
            if (strtolower(trim($loc['pickup_location'] ?? '')) === strtolower($locationName)) {
                $found = $loc;
                break;
            }
        }

        if (! $found) {
            throw new \Exception("Could not register or find Shiprocket pickup location '{$locationName}'.");
        }

        // Shiprocket: status=1 + phone_verified=1 means usable for international.
        // email_verified is informational, often null — not required.
        $verified =
            ((int) ($found['status']         ?? 0)) === 1
            && ((int) ($found['phone_verified'] ?? 0)) === 1;

        Log::info('Shiprocket pickup lookup result', [
            'name'           => $locationName,
            'id'             => $found['id'] ?? null,
            'status'         => $found['status'] ?? null,
            'phone_verified' => $found['phone_verified'] ?? null,
            'email_verified' => $found['email_verified'] ?? null,
            'verified'       => $verified,
        ]);

        $result = [
            'name'     => $locationName,
            'id'       => (int) $found['id'],
            'verified' => $verified,
        ];

        // Cache only verified pickups so unverified ones get re-checked next time
        if ($verified) {
            Cache::put($cacheKey, $result, 30 * 24 * 60 * 60);
        }

        return $result;
    }

    // ──────────────────────────────────────────────────────────────────
    // COURIER SERVICEABILITY & SELECTION
    // ──────────────────────────────────────────────────────────────────

    public function getAvailableServiceNames(float $weight, string $countryCode, ?string $pickupPincode = null): ?array
    {
        $couriers = $this->getAvailableCouriers($weight, $countryCode, $pickupPincode);
        if ($couriers === null) return null;
        return array_map(fn($c) => $c['name'], $couriers);
    }

    /**
     * Fetch the full live list of available couriers (name + Shiprocket courier_company_id)
     * for the given route. Returns null if pincode missing or API fails.
     *
     * Used by RateCalculatorController to swap stale rate-sheet courier IDs for
     * fresh ones that are actually serviceable.
     */
    public function getAvailableCouriers(float $weight, string $countryCode, ?string $pickupPincode = null): ?array
    {
        $pickupPincode = $pickupPincode ?? config('shiprocket.pickup_pincode');
        if (! $pickupPincode) {
            return null;
        }

        $slab     = ceil($weight * 2) / 2;
        $cacheKey = 'sr_couriers_' . strtoupper($countryCode) . '_' . $slab . '_' . $pickupPincode;

        return Cache::remember($cacheKey, 5 * 60, function () use ($weight, $countryCode, $pickupPincode) {
            try {
                $response = $this->request('get', '/international/courier/serviceability', [
                    'weight'           => round($weight, 2),
                    'cod'              => 0,
                    'delivery_country' => $this->resolveCountryName($countryCode),
                    'pickup_postcode'  => $pickupPincode,
                ]);
                $couriers = $response['data']['available_courier_companies'] ?? [];
                return array_values(array_filter(array_map(
                    fn($c) => [
                        'name' => strtolower(trim($c['courier_name'] ?? '')),
                        'id'   => (int) ($c['courier_company_id'] ?? 0),
                        'rate' => (float) ($c['rate'] ?? 0),
                    ],
                    $couriers
                ), fn($c) => $c['name'] !== '' && $c['id'] > 0));
            } catch (\Exception $e) {
                Log::warning('Shiprocket serviceability check failed during rate calculation', [
                    'country' => $countryCode,
                    'weight'  => $weight,
                    'error'   => $e->getMessage(),
                ]);
                return null;
            }
        });
    }

    private function getRecommendedCourierId(int $shipmentId, array $data, bool $isInternational = false): int
    {
        // Fast path: the frontend sent a courier_company_id that was already
        // validated against live serviceability at rate calculation time
        // (RateCalculatorController overwrote it with a fresh Shiprocket ID).
        // Trust it directly — calling /serviceability again here with an
        // already-created order_id makes Shiprocket return 400.
        if (! empty($data['courier_company_id'])) {
            return (int) $data['courier_company_id'];
        }

        // Fallback path: no courier_company_id provided — look it up by service name.
        $package = $data['packages'][0];

        if ($isInternational) {
            $params   = [
                'weight'           => $package['weight'],
                'cod'              => 0,
                'delivery_country' => $this->resolveCountryName($data['receiver']['country_code']),
                'pickup_postcode'  => $data['sender']['pincode'],
                'order_id'         => $shipmentId,
            ];
            $endpoint = '/international/courier/serviceability';
        } else {
            $params   = [
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

        $selectedName = strtolower(trim($data['service_code'] ?? $data['service_name'] ?? ''));

        if ($selectedName !== '') {
            foreach ($available as $courier) {
                if (strtolower(trim($courier['courier_name'] ?? '')) === $selectedName) {
                    Log::info('Shiprocket courier matched (exact)', ['selected' => $selectedName, 'id' => $courier['courier_company_id']]);
                    return (int) $courier['courier_company_id'];
                }
            }

            foreach ($available as $courier) {
                $courierName = strtolower(trim($courier['courier_name'] ?? ''));
                if ($courierName !== '' && (str_contains($courierName, $selectedName) || str_contains($selectedName, $courierName))) {
                    Log::info('Shiprocket courier matched (substring)', ['selected' => $selectedName, 'id' => $courier['courier_company_id']]);
                    return (int) $courier['courier_company_id'];
                }
            }

            throw new \Exception(
                "The selected service \"{$data['service_name']}\" is not available for this route. " .
                "Please go back and select a different service."
            );
        }

        usort($available, fn($a, $b) => ((float)($a['rate']['rate'] ?? 0)) <=> ((float)($b['rate']['rate'] ?? 0)));
        return (int) $available[0]['courier_company_id'];
    }

    private function assignAWB(int $shipmentId, int $courierId, bool $isInternational = false): string
    {
        $endpoint = $isInternational ? '/international/courier/assign/awb' : '/courier/assign/awb';

        $response = $this->request('post', $endpoint, [
            'shipment_id' => (string) $shipmentId,
            'courier_id'  => (string) $courierId,
        ]);

        $awb = $response['response']['data']['awb_code'] ?? $response['awb_code'] ?? null;

        if (! $awb) {
            // Surface Shiprocket's own message when it's a transient courier issue
            // — these are common (couriers cycle in/out of availability) and the
            // user just needs to retry or pick a different service.
            $reason = $response['message']
                  ?? $response['response']['data']
                  ?? null;

            if (is_string($reason) && $reason !== '') {
                throw new \Exception('Shiprocket: ' . $reason);
            }

            throw new \Exception('Shiprocket AWB assignment failed: ' . json_encode($response));
        }

        return (string) $awb;
    }

    private function generateLabel(int $shipmentId): ?string
    {
        try {
            $response = $this->request('post', '/courier/generate/label', ['shipment_id' => [$shipmentId]]);

            // Shiprocket has returned label URL under different keys in different
            // API versions / order types. Try the known variations.
            $url = $response['label_url']
                ?? $response['data']['label_url']
                ?? $response['response']['label_url']
                ?? null;

            if (! $url) {
                Log::warning('Shiprocket label response missing URL key', [
                    'shipment_id' => $shipmentId,
                    'response'    => $response,
                ]);
            }

            return $url;
        } catch (\Exception $e) {
            Log::warning('Shiprocket label generation failed', ['shipment_id' => $shipmentId, 'error' => $e->getMessage()]);
            return null;
        }
    }

    private function generateInternationalPickup(int $shipmentId): void
    {
        try {
            $token    = $this->getToken();
            $response = Http::withToken($token)
                ->acceptJson()
                ->post('https://apiv2.shiprocket.in/v1/external/international/courier/generate/pickup', [
                    'shipment_id' => [$shipmentId],
                ]);
            Log::info('Shiprocket international pickup generated', ['shipment_id' => $shipmentId, 'status' => $response->status()]);
        } catch (\Exception $e) {
            Log::warning('Shiprocket international pickup generation failed', ['shipment_id' => $shipmentId, 'error' => $e->getMessage()]);
        }
    }

    private function generateInternationalManifest(int $shipmentId): ?string
    {
        try {
            $response = $this->request('post', '/international/manifests/generate', ['shipment_id' => [$shipmentId]]);
            return $response['manifest_url'] ?? $response['label_url'] ?? null;
        } catch (\Exception $e) {
            Log::warning('Shiprocket international manifest generation failed', ['shipment_id' => $shipmentId, 'error' => $e->getMessage()]);
            return null;
        }
    }

    private function resolveCountryName(string $code): string
    {
        if (! extension_loaded('intl')) {
            return $code;
        }
        $name = \Locale::getDisplayRegion('-' . strtoupper($code), 'en');
        return ($name && $name !== strtoupper($code)) ? $name : $code;
    }

    // ──────────────────────────────────────────────────────────────────
    // COURIER LIST
    // ──────────────────────────────────────────────────────────────────

    public function getAllCouriers(): array
    {
        $response = $this->request('get', '/courier/courierListWithCounts');

        return array_map(fn($c) => array_merge($c, [
            'courier_company_id' => $c['id'],
            'courier_name'       => $c['name'],
        ]), $response['courier_data'] ?? []);
    }

    // ──────────────────────────────────────────────────────────────────
    // TRACKING
    // ──────────────────────────────────────────────────────────────────

    public function trackShipment(string $awbNo): array
    {
        $response   = $this->request('get', "/courier/track/awb/{$awbNo}");
        $tracking   = $response['tracking_data'] ?? [];
        $shipment   = $tracking['shipment_track'][0] ?? [];
        $activities = $tracking['shipment_track_activities'] ?? [];

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

    /**
     * Resolve which Shiprocket pickup to use for an international booking.
     *
     * Strategy:
     *   1. Match the booking sender to a saved Address (by pincode + phone). If the
     *      Address has a verified Shiprocket pickup → use it.
     *   2. Otherwise fall back to the configured `Primary` (or any verified pickup
     *      in the account).
     */
    private function resolvePickupForBooking(array $sender): array
    {
        // Match by user_id (most reliable) + pincode. Phone can differ between
        // the user's profile and the address row, so we don't filter on it.
        $matched = \App\Models\Address::query()
            ->when(! empty($sender['user_id']), fn($q) => $q->where('user_id', $sender['user_id']))
            ->where('pincode', $sender['pincode'] ?? '')
            ->where('pickup_verified', true)
            ->whereNotNull('shiprocket_pickup_id')
            ->orderByDesc('pickup_verified_at')
            ->first();

        if ($matched) {
            return [
                'name'     => $matched->shiprocket_pickup_name,
                'id'       => (int) $matched->shiprocket_pickup_id,
                'verified' => true,
            ];
        }

        Log::warning('No verified sender pickup found for booking — using Primary fallback', [
            'sender_pincode' => $sender['pincode'] ?? null,
            'sender_phone'   => $sender['phone'] ?? null,
        ]);

        return $this->getVerifiedPickupLocation();
    }

    /**
     * Look up the pre-configured Primary pickup location (the one verified on the
     * Shiprocket dashboard) and return its `{name, id}`. Cached 30 days since the
     * ID never changes.
     *
     * Use this for international orders — Shiprocket rejects unverified pickups
     * with a generic 500 ("Call to a member function shipments() on null").
     */
    private function getVerifiedPickupLocation(): array
    {
        $cacheKey = 'shiprocket_primary_pickup';

        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        $primaryName = config('shiprocket.pickup_location', 'Primary');
        $response    = $this->request('get', '/settings/company/pickup');

        // Shiprocket response shape: data.shipping_address[] — but older accounts
        // sometimes return data[] directly. Handle both.
        $locations = $response['data']['shipping_address']
                  ?? $response['data']
                  ?? [];

        $isVerified = fn($loc) =>
            ((int) ($loc['status']         ?? 0)) === 1
            && ((int) ($loc['phone_verified'] ?? 0)) === 1;

        // 1. Exact match on configured name, must be verified
        foreach ($locations as $loc) {
            if (! is_array($loc)) continue;
            if (strtolower(trim($loc['pickup_location'] ?? '')) === strtolower($primaryName) && $isVerified($loc)) {
                $result = ['name' => $loc['pickup_location'], 'id' => (int) $loc['id']];
                Cache::put($cacheKey, $result, 30 * 24 * 60 * 60);
                return $result;
            }
        }

        // 2. Last resort: any verified pickup in the account
        foreach ($locations as $loc) {
            if (! is_array($loc)) continue;
            if ($isVerified($loc)) {
                Log::warning('Configured Shiprocket pickup not found; using first verified pickup', [
                    'configured' => $primaryName,
                    'using'      => $loc['pickup_location'] ?? null,
                ]);
                $result = ['name' => $loc['pickup_location'], 'id' => (int) $loc['id']];
                Cache::put($cacheKey, $result, 30 * 24 * 60 * 60);
                return $result;
            }
        }

        throw new \Exception(
            "No verified pickup location found in Shiprocket. " .
            "Log into app.shiprocket.in → Settings → Pickup Addresses, create a pickup, and complete phone + email verification."
        );
    }

    /**
     * Shiprocket rejects addresses that don't contain both a digit and a space
     * (combined line1 + line2 must also be >= 5 chars). Many Indian users type
     * addresses like "542-Janakpuri" with no space — we normalise transparently
     * so the booking doesn't 400 on them.
     */
    private function normalizeAddressForShiprocket(string $address): string
    {
        $address = trim($address);

        // Add a space after `-`, `,`, `/`, `.` when followed by a non-space char
        $address = preg_replace('/([\-,\/\.])(\S)/', '$1 $2', $address);

        // Still no space? Pad the start so Shiprocket sees one
        if (! preg_match('/\s/', $address)) {
            $address = $address . ' ';
        }

        // Still no digit? Prepend a placeholder unit number
        if (! preg_match('/\d/', $address)) {
            $address = '1 ' . $address;
        }

        // Ensure minimum 5 chars
        if (strlen($address) < 5) {
            $address = str_pad($address, 5);
        }

        return $address;
    }
}
