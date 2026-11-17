<?php

namespace App\Http\Controllers\Api\OwnerToko;

use App\Http\Controllers\Controller;
use App\Models\Tarif;
use App\Models\Toko;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TarifController extends Controller
{
    /**
     * Menampilkan daftar tarif layanan dari toko spesifik.
     */
    public function index(Toko $toko): JsonResponse
    {
        Gate::authorize('view', $toko);

        return response()->json([
            'toko' => [
                'id' => $toko->id,
                'nama' => $toko->nama,
            ],
            'tarifs' => $toko->tarifs()->latest()->get(),
        ]);
    }

    /**
     * Menambahkan tarif layanan baru pada toko.
     * Menggunakan TarifPolicy untuk memastikan hanya owner atau admin toko bersangkutan.
     */
    public function store(Request $request, Toko $toko): JsonResponse
    {
        Gate::authorize('create', [Tarif::class, $toko]);

        $validated = $request->validate([
            'jenis_layanan' => 'required|string|max:100',
            'harga' => 'required|numeric|min:0',
        ]);

        $tarif = $toko->tarifs()->create($validated);

        return response()->json([
            'message' => 'Tarif layanan berhasil ditambahkan.',
            'tarif' => $tarif,
        ], 201);
    }

    /**
     * Memperbarui tarif layanan.
     * Menggunakan TarifPolicy (scoped ke toko).
     */
    public function update(Request $request, Tarif $tarif): JsonResponse
    {
        Gate::authorize('update', $tarif);

        $validated = $request->validate([
            'jenis_layanan' => 'sometimes|required|string|max:100',
            'harga' => 'sometimes|required|numeric|min:0',
        ]);

        $tarif->update($validated);

        return response()->json([
            'message' => 'Tarif layanan berhasil diperbarui.',
            'tarif' => $tarif,
        ]);
    }

    /**
     * Menghapus tarif layanan.
     * Menggunakan TarifPolicy (scoped ke toko).
     */
    public function destroy(Tarif $tarif): JsonResponse
    {
        Gate::authorize('delete', $tarif);

        $tarif->delete();

        return response()->json([
            'message' => 'Tarif layanan berhasil dihapus.',
        ]);
    }
}
