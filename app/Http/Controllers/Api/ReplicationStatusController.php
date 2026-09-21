<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReplicationMonitor;
use Illuminate\Http\JsonResponse;

class ReplicationStatusController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(ReplicationMonitor $monitor): JsonResponse
    {
        $status = $monitor->snapshot();

        return response()->json([
            'healthy' => $monitor->isHealthy(null, $status['replicas']),
            ...$status,
        ]);
    }
}
