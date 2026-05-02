<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class AdminPlatformController extends Controller
{
    use ApiResponse;

    private array $platforms = [
        'overseas'   => 'Overseas API',
        'shiprocket' => 'Shiprocket',
    ];

    // GET /api/v1/admin/platforms
    public function index(): JsonResponse
    {
        $platforms = [];
        foreach ($this->platforms as $key => $label) {
            $platforms[$key] = [
                'enabled' => SiteSetting::get("platform_{$key}_enabled", '1') === '1',
                'label'   => $label,
            ];
        }

        return $this->successResponse(data: ['platforms' => $platforms]);
    }

    // POST /api/v1/admin/platforms/{platform}/toggle
    public function toggle(string $platform): JsonResponse
    {
        if (! array_key_exists($platform, $this->platforms)) {
            return $this->errorResponse(message: 'Invalid platform.', statusCode: 422);
        }

        $key     = "platform_{$platform}_enabled";
        $current = SiteSetting::get($key, '1');
        $newVal  = $current === '1' ? '0' : '1';

        SiteSetting::set($key, $newVal, 'boolean');

        $state = $newVal === '1' ? 'enabled' : 'disabled';

        return $this->successResponse(
            data: ['platform' => $platform, 'enabled' => $newVal === '1'],
            message: "{$this->platforms[$platform]} {$state} successfully."
        );
    }
}
