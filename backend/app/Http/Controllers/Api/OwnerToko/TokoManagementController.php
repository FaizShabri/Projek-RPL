<?php

namespace App\Http\Controllers\Api\OwnerToko;

use App\Http\Controllers\Controller;
use App\Events\TokoRegistered;
use App\Models\Toko;
use App\Enums\UserRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class TokoManagementController extends Controller
{
    /**
     * Menampilkan daftar toko yang dimiliki oleh owner toko yang sedang login,
     * atau toko yang di-assign jika user aktif sebagai admin_toko.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $activeRole = $request->header('X-Active-Role');

        if ($activeRole === UserRole::ADMIN_TOKO->value) {
            $tokos = $user->staffTokos()->with(['tarifs', 'staffUsers:id,name,email'])->get();
        } else {
            $tokos = Toko::where('owner_id', $user->id)
                ->with(['tarifs', 'staffUsers:id,name,email'])
                ->latest()
                ->get();
        }

        return response()->json([
            'tokos' => $tokos,
        ]);
    }

    /**
     * Form Onboarding Toko Baru.
     * Status default: 'submitted'.
     * Upload dokumen legalitas: opsional di awal, wajib sebelum settlement pertama.
     */
    public function store(Request $request): JsonResponse
    {
        // Verifikasi Email Wajib sebelum dapat mendaftarkan toko laundry
        if (!$request->user()->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Anda wajib memverifikasi alamat email sebelum dapat mendaftarkan toko laundry.',
                'requires_email_verification' => true,
            ], 403);
        }

        Gate::authorize('create', Toko::class);

        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'alamat' => 'required|string',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'dokumen_legalitas' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120', // max 5MB
        ]);

        $dokumenPath = null;
        if ($request->hasFile('dokumen_legalitas')) {
            $dokumenPath = $request->file('dokumen_legalitas')->store('legalitas', 'public');
        }

        $toko = Toko::create([
            'owner_id' => $request->user()->id,
            'nama' => $validated['nama'],
            'alamat' => $validated['alamat'],
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'dokumen_legalitas' => $dokumenPath,
            'status' => 'submitted',
        ]);

        // Dispatch Event Notifikasi Toko Baru untuk Platform / Admin Owner
        TokoRegistered::dispatch($toko, $request->user());

        return response()->json([
            'message' => 'Toko berhasil didaftarkan dan sedang menunggu peninjauan (submitted).',
            'toko' => $toko,
        ], 201);
    }

    /**
     * Detail informasi toko.
     */
    public function show(Toko $toko): JsonResponse
    {
        Gate::authorize('view', $toko);

        $toko->load(['tarifs', 'staffUsers:id,name,email', 'owner:id,name,email,avatar']);

        return response()->json([
            'toko' => $toko,
        ]);
    }

    /**
     * Update profil / dokumen legalitas toko.
     */
    public function update(Request $request, Toko $toko): JsonResponse
    {
        Gate::authorize('update', $toko);

        $validated = $request->validate([
            'nama' => 'sometimes|required|string|max:255',
            'alamat' => 'sometimes|required|string',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'dokumen_legalitas' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        if ($request->hasFile('dokumen_legalitas')) {
            // Hapus dokumen lama jika ada
            if ($toko->dokumen_legalitas && Storage::disk('public')->exists($toko->dokumen_legalitas)) {
                Storage::disk('public')->delete($toko->dokumen_legalitas);
            }
            $validated['dokumen_legalitas'] = $request->file('dokumen_legalitas')->store('legalitas', 'public');
        }

        $toko->update($validated);

        return response()->json([
            'message' => 'Data toko berhasil diperbarui.',
            'toko' => $toko->fresh(),
        ]);
    }
}
