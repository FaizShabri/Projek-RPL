<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Require2FAMiddleware
{
    /**
     * Memastikan user tidak memiliki ability '2fa:pending'.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        if ($user && $user->currentAccessToken()) {
            $abilities = $user->currentAccessToken()->abilities ?? [];
            if (is_array($abilities) && in_array('2fa:pending', $abilities)) {
                $isConfirmed = !empty($user->two_factor_confirmed_at);
                return response()->json([
                    'message' => '2FA Verification Required.',
                    'requires_2fa' => true,
                    'error_code' => $isConfirmed ? '2fa_required' : '2fa_setup_required',
                    'destination' => $isConfirmed ? '/2fa/challenge' : '/2fa/setup',
                ], 403);
            }
        }

        return $next($request);
    }
}
