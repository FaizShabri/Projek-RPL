<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerReviewController extends Controller
{
    /**
     * Menampilkan daftar semua ulasan yang pernah dibuat oleh customer.
     */
    public function index(Request $request): JsonResponse
    {
        $customerId = $request->user()->id;

        $reviews = Review::whereHas('order', function ($q) use ($customerId) {
            $q->where('customer_id', $customerId);
        })
        ->with([
            'order:id,nomor_order,created_at,status',
            'toko:id,nama,alamat',
            'kurir:id,name,avatar',
        ])
        ->latest()
        ->paginate(15);

        return response()->json($reviews);
    }
}
