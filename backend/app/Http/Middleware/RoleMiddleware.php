<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request based on user role.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // Placeholder implementation for Role checking
        
        if (! $request->user()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        
        // $userRole = $request->user()->role->value; // using the UserRole Enum
        // if (! in_array($userRole, $roles)) {
        //     return response()->json(['message' => 'Forbidden. You do not have the required role.'], 403);
        // }

        return $next($request);
    }
}
