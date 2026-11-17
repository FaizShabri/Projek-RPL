<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Toko;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class PublicTokoController extends Controller
{
    /**
     * Endpoint publik untuk listing toko di marketplace.
     * HANYA menampilkan toko yang berstatus 'approved'.
     * Mendukung: search nama/alamat, filter lokasi/radius (lat/lng), filter rating, dan sorting.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Toko::approved()
            ->with(['tarifs', 'owner:id,name,avatar'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews');

        // 1. Search berdasarkan nama toko atau alamat (case-insensitive)
        if ($search = trim($request->query('search', ''))) {
            $query->where(function ($q) use ($search) {
                $term = '%' . strtolower($search) . '%';
                $q->whereRaw('LOWER(nama) LIKE ?', [$term])
                  ->orWhereRaw('LOWER(alamat) LIKE ?', [$term]);
            });
        }

        // 2. Filter rating minimal (misal min_rating = 4)
        if ($request->filled('min_rating')) {
            $minRating = (float) $request->query('min_rating');
            $query->whereRaw('(SELECT AVG(rating) FROM reviews WHERE reviews.toko_id = tokos.id) >= ?', [$minRating]);
        }

        // Ambil data untuk kalkulasi jarak & radius
        $userLat = $request->filled('latitude') ? (float) $request->query('latitude') : null;
        $userLng = $request->filled('longitude') ? (float) $request->query('longitude') : null;
        $radius = $request->filled('radius') ? (float) $request->query('radius') : null;
        $sortBy = $request->query('sort', 'default');

        $tokos = $query->get()->map(function ($toko) use ($userLat, $userLng) {
            if ($userLat !== null && $userLng !== null && $toko->latitude !== null && $toko->longitude !== null) {
                $toko->distance = self::calculateDistanceKm($userLat, $userLng, (float) $toko->latitude, (float) $toko->longitude);
            } else {
                $toko->distance = null;
            }

            // Normalisasi nilai rata-rata rating
            $toko->reviews_avg_rating = $toko->reviews_avg_rating ? round((float) $toko->reviews_avg_rating, 1) : null;
            $toko->reviews_count = (int) $toko->reviews_count;

            return $toko;
        });

        // 3. Filter radius (jika koordinat customer dan radius dikirim)
        if ($radius !== null && $radius > 0 && $userLat !== null && $userLng !== null) {
            $tokos = $tokos->filter(function ($toko) use ($radius) {
                return $toko->distance !== null && $toko->distance <= $radius;
            });
        }

        // 4. Pengurutan (Sorting)
        if ($sortBy === 'terdekat' && $userLat !== null) {
            $tokos = $tokos->sortBy(fn($toko) => $toko->distance ?? 999999);
        } elseif ($sortBy === 'rating') {
            $tokos = $tokos->sortByDesc(fn($toko) => $toko->reviews_avg_rating ?? 0);
        } elseif ($sortBy === 'nama') {
            $tokos = $tokos->sortBy(fn($toko) => strtolower($toko->nama));
        } else {
            // Default: jika ada koordinat utamakan yang terdekat, jika tidak utamakan yang terbaru
            if ($userLat !== null) {
                $tokos = $tokos->sortBy(fn($toko) => $toko->distance ?? 999999);
            } else {
                $tokos = $tokos->sortByDesc('created_at');
            }
        }

        // 5. Pagination
        $page = max((int) $request->query('page', 1), 1);
        $perPage = max((int) $request->query('per_page', 9), 1);
        $total = $tokos->count();
        $sliced = $tokos->slice(($page - 1) * $perPage, $perPage)->values();

        $paginated = new LengthAwarePaginator(
            $sliced,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return response()->json($paginated);
    }

    /**
     * Endpoint detail toko publik beserta daftar tarif layanannya, info ulasan, dan rating.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $toko = Toko::approved()
            ->with([
                'tarifs',
                'owner:id,name,avatar',
                'reviews.order.customer:id,name,avatar',
            ])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->findOrFail($id);

        $userLat = $request->filled('latitude') ? (float) $request->query('latitude') : null;
        $userLng = $request->filled('longitude') ? (float) $request->query('longitude') : null;

        if ($userLat !== null && $userLng !== null && $toko->latitude !== null && $toko->longitude !== null) {
            $toko->distance = self::calculateDistanceKm($userLat, $userLng, (float) $toko->latitude, (float) $toko->longitude);
        } else {
            $toko->distance = null;
        }

        $avgRating = $toko->reviews_avg_rating ? round((float) $toko->reviews_avg_rating, 1) : 0.0;
        $toko->reviews_avg_rating = $avgRating;

        // Breakdown ulasan berdasarkan bintang (1-5)
        $reviews = $toko->reviews;
        $totalReviews = $reviews->count();
        $ratingBreakdown = [
            5 => 0,
            4 => 0,
            3 => 0,
            2 => 0,
            1 => 0,
        ];
        foreach ($reviews as $rev) {
            $r = (int) $rev->rating;
            if (isset($ratingBreakdown[$r])) {
                $ratingBreakdown[$r]++;
            }
        }

        // Format data ulasan
        $formattedReviews = $reviews->sortByDesc('created_at')->values()->map(function ($rev) {
            return [
                'id' => $rev->id,
                'rating' => $rev->rating,
                'komentar' => $rev->komentar,
                'created_at' => $rev->created_at ? $rev->created_at->format('d M Y') : null,
                'customer_name' => $rev->order?->customer?->name ?? 'Pelanggan',
                'customer_avatar' => $rev->order?->customer?->avatar ?? null,
            ];
        });

        return response()->json([
            'toko' => $toko,
            'rating_summary' => [
                'average' => $avgRating,
                'total' => $totalReviews,
                'breakdown' => $ratingBreakdown,
            ],
            'reviews' => $formattedReviews,
        ]);
    }

    /**
     * Hitung jarak lingkaran besar (Great-circle distance) menggunakan formula Haversine dalam kilometer.
     */
    public static function calculateDistanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 2);
    }
}

