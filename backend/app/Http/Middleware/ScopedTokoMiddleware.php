<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Toko;
use App\Enums\UserRole;

class ScopedTokoMiddleware
{
    /**
     * Defense in Depth - Lapis 1 (Route Middleware):
     * Memastikan request ke resource toko tertuju hanya pada toko yang berhak
     * diakses oleh owner_toko atau admin_toko terkait.
     */
    public function handle(Request $request, Closure $next, string $parameterName = 'toko'): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Dapatkan objek atau ID toko dari route parameter
        $tokoParam = $request->route($parameterName);
        
        if (!$tokoParam) {
            return $next($request);
        }

        $toko = $tokoParam instanceof Toko ? $tokoParam : Toko::find($tokoParam);

        if (!$toko) {
            return response()->json(['message' => 'Toko tidak ditemukan.'], 404);
        }

        $activeRole = $request->header('X-Active-Role');

        // Platform owner & admin owner memiliki akses global platform
        if (in_array($activeRole, [UserRole::PLATFORM_OWNER->value, UserRole::ADMIN_OWNER->value])) {
            return $next($request);
        }

        // Jika bertindak sebagai Owner Toko: harus toko miliknya sendiri
        if ($activeRole === UserRole::OWNER_TOKO->value) {
            if ($toko->owner_id !== $user->id) {
                return response()->json([
                    'message' => 'Forbidden. Anda bukan pemilik dari toko ini (Defense in Depth: Layer 1 - Route Middleware).',
                ], 403);
            }
            return $next($request);
        }

        // Jika bertindak sebagai Admin Toko: harus terdaftar di staff_tokos untuk toko ini
        if ($activeRole === UserRole::ADMIN_TOKO->value) {
            $isAssigned = $toko->staff()->where('user_id', $user->id)->exists();
            if (!$isAssigned) {
                return response()->json([
                    'message' => 'Forbidden. Anda tidak ditugaskan sebagai admin pada toko ini (Defense in Depth: Layer 1 - Route Middleware).',
                ], 403);
            }
            return $next($request);
        }

        return response()->json([
            'message' => 'Forbidden. Role aktif Anda tidak memiliki wewenang pada toko ini.',
        ], 403);
    }
}
