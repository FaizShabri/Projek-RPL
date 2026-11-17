<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ActiveRoleMiddleware
{
    /**
     * Memastikan user yang login BENAR memiliki role yang dikirim di header X-Active-Role.
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Ambil dari header (dikirim oleh Frontend Role Switcher)
        $activeRole = $request->header('X-Active-Role');

        if (! $activeRole) {
            return response()->json(['message' => 'X-Active-Role header is required.'], 400);
        }

        // Cek apakah user BENAR-BENAR punya role tersebut di DB
        if (! $user->hasRole($activeRole)) {
            return response()->json(['message' => 'Forbidden. You do not have the requested role.'], 403);
        }

        // Cek apakah endpoint mensyaratkan role tertentu (misal dikirim dari route middleware params)
        if (!empty($roles) && !in_array($activeRole, $roles)) {
            return response()->json(['message' => 'Forbidden. Your active role cannot access this resource.'], 403);
        }

        // Simpan activeRole di request agar bisa dipakai Controller
        $request->merge(['active_role' => $activeRole]);

        return $next($request);
    }
}
