<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    /**
     * Return health check status of the API.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'message' => 'Pusat Laundry API is running',
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
