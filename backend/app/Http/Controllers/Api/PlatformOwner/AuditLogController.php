<?php

namespace App\Http\Controllers\Api\PlatformOwner;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Policies\FinancePolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AuditLogController extends Controller
{
    /**
     * Menampilkan daftar riwayat audit log immutable lengkap (Hanya Platform Owner).
     */
    public function index(Request $request): JsonResponse
    {
        // Hanya platform owner yang berhak melihat audit trail keseluruhan
        Gate::authorize('manageFinances');

        $query = AuditLog::with('actor:id,name,email,avatar')->latest('created_at');

        if ($action = $request->query('action')) {
            $query->where('action', 'like', "%{$action}%");
        }

        if ($actorId = $request->query('actor_id')) {
            $query->where('actor_id', $actorId);
        }

        $logs = $query->paginate(20);

        return response()->json($logs);
    }
}
