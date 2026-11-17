<?php

namespace App\Http\Controllers\Api\OwnerToko;

use App\Http\Controllers\Controller;
use App\Models\StaffToko;
use App\Models\Toko;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class StaffTokoController extends Controller
{
    /**
     * Menampilkan daftar admin toko / staff yang bertugas pada toko ini.
     */
    public function index(Toko $toko): JsonResponse
    {
        Gate::authorize('view', $toko);

        $staff = $toko->staff()->with('user:id,name,email,avatar')->get();

        return response()->json([
            'toko' => [
                'id' => $toko->id,
                'nama' => $toko->nama,
            ],
            'staff' => $staff,
        ]);
    }

    /**
     * Mengundang / menugaskan user menjadi Admin Toko pada toko ini.
     * 
     * DEFENSE IN DEPTH - Lapis 2 (Controller Policy):
     * Memastikan HANYA owner toko yang berhak mengundang staff.
     */
    public function store(Request $request, Toko $toko): JsonResponse
    {
        // Lapis 2: Policy check
        Gate::authorize('manageStaff', $toko);

        $validated = $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $validated['email'])->firstOrFail();

        // Tidak boleh menugaskan diri sendiri jika sudah owner
        if ($user->id === $toko->owner_id) {
            return response()->json([
                'message' => 'User ini adalah pemilik utama (owner) toko, tidak perlu ditambahkan sebagai staff.',
            ], 422);
        }

        // Cek apakah sudah terdaftar sebagai staff di toko ini
        $alreadyStaff = $toko->staff()->where('user_id', $user->id)->exists();
        if ($alreadyStaff) {
            return response()->json([
                'message' => 'User ini sudah terdaftar sebagai Admin Toko untuk toko ini.',
            ], 422);
        }

        // Pastikan user memiliki role admin_toko di Spatie Roles
        if (!$user->hasRole(UserRole::ADMIN_TOKO->value)) {
            $user->assignRole(UserRole::ADMIN_TOKO->value);
        }

        $staff = $toko->staff()->create([
            'user_id' => $user->id,
        ]);

        return response()->json([
            'message' => "User {$user->name} ({$user->email}) berhasil ditugaskan sebagai Admin Toko.",
            'staff' => $staff->load('user:id,name,email,avatar'),
        ], 201);
    }

    /**
     * Menghapus penugasan admin toko dari toko ini.
     * DEFENSE IN DEPTH - Lapis 2 (Controller Policy).
     */
    public function destroy(Toko $toko, StaffToko $staffToko): JsonResponse
    {
        // Lapis 2: Policy check
        Gate::authorize('manageStaff', $toko);

        if ($staffToko->toko_id !== $toko->id) {
            return response()->json([
                'message' => 'Staff tidak terkait dengan toko ini.',
            ], 400);
        }

        $staffUser = $staffToko->user;
        $staffToko->delete();

        return response()->json([
            'message' => "Akses Admin Toko untuk {$staffUser->name} berhasil dicabut dari toko ini.",
        ]);
    }
}
