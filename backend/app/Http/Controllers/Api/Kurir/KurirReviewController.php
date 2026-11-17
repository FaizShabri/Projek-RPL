<?php

namespace App\Http\Controllers\Api\Kurir;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KurirReviewController extends Controller
{
    /**
     * Menampilkan daftar rating dan ulasan yang diterima kurir dari customer.
     */
    public function index(Request $request): JsonResponse
    {
        $kurirId = $request->user()->id;

        $reviewsQuery = Review::where('kurir_id', $kurirId)
            ->with([
                'order:id,nomor_order,created_at',
                'order.customer:id,name,avatar',
            ])
            ->latest();

        $avgRating = (float) (clone $reviewsQuery)->avg('rating');
        $totalReviews = (clone $reviewsQuery)->count();

        // Rating breakdown (bintang 1 - 5)
        $ratingCounts = [
            5 => Review::where('kurir_id', $kurirId)->where('rating', 5)->count(),
            4 => Review::where('kurir_id', $kurirId)->where('rating', 4)->count(),
            3 => Review::where('kurir_id', $kurirId)->where('rating', 3)->count(),
            2 => Review::where('kurir_id', $kurirId)->where('rating', 2)->count(),
            1 => Review::where('kurir_id', $kurirId)->where('rating', 1)->count(),
        ];

        $reviews = $reviewsQuery->paginate(15);

        return response()->json([
            'average_rating' => round($avgRating, 1),
            'total_reviews' => $totalReviews,
            'rating_breakdown' => $ratingCounts,
            'reviews' => $reviews,
        ]);
    }
}
