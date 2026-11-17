<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ProfileController extends Controller
{
    /**
     * Get the user's profile.
     */
    public function show(Request $request)
    {
        $user = $request->user()->load('profile', 'roles');
        return response()->json($user);
    }
    
    /**
     * Store or update the user's profile (Onboarding).
     */
    public function store(Request $request)
    {
        $request->validate([
            'nama_lengkap' => 'required|string|max:255',
            'alamat_lengkap' => 'required|string',
            'rt_rw' => 'required|string|max:20',
            'nomor_telepon' => 'required|string|max:20',
        ]);
        
        $user = $request->user();
        
        $profile = $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            $request->only(['nama_lengkap', 'alamat_lengkap', 'rt_rw', 'nomor_telepon'])
        );
        
        return response()->json([
            'message' => 'Profile updated successfully',
            'profile' => $profile
        ]);
    }
}
