<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Enums\UserRole;

class RoleController extends Controller
{
    /**
     * Endpoint untuk user mendaftar sebagai owner toko.
     * Akan menambahkan role 'owner_toko' ke user yang login.
     */
    public function becomeOwner(Request $request)
    {
        $user = $request->user();
        
        if (! $user->hasRole(UserRole::OWNER_TOKO->value)) {
            $user->assignRole(UserRole::OWNER_TOKO->value);
            return response()->json([
                'message' => 'Berhasil mendaftar sebagai Owner Toko.',
                'roles' => $user->getRoleNames()
            ]);
        }
        
        return response()->json([
            'message' => 'Anda sudah memiliki role Owner Toko.'
        ], 400);
    }
}
