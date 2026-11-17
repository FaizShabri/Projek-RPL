<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Toko;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerOrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService
    ) {}

    /**
     * Daftar pesanan milik customer yang sedang login.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Order::where('customer_id', $request->user()->id)
            ->with([
                'toko:id,nama,alamat,latitude,longitude',
                'tarif:id,jenis_layanan,harga',
                'kurir:id,name,avatar',
                'reviews',
                'disputes',
            ])
            ->latest();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $orders = $query->paginate(10);

        return response()->json($orders);
    }

    /**
     * Buat pesanan laundry baru oleh customer.
     */
    public function store(Request $request): JsonResponse
    {
        // Verifikasi Email Wajib sebelum Customer dapat membuat order
        if (!$request->user()->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Anda wajib memverifikasi alamat email sebelum dapat membuat pesanan cucian.',
                'requires_email_verification' => true,
            ], 403);
        }

        $validated = $request->validate([
            'toko_id'       => 'required|exists:tokos,id',
            'tarif_id'      => 'required|exists:tarifs,id',
            'alamat_jemput' => 'required|string|max:500',
            'catatan'       => 'nullable|string|max:1000',
        ]);

        $toko = Toko::approved()->findOrFail($validated['toko_id']);

        $order = $this->orderService->createOrder($validated, $request->user());

        return response()->json([
            'message' => 'Pesanan laundry berhasil dibuat.',
            'order'   => $order,
        ], 201);
    }

    /**
     * Detail pesanan spesifik beserta timeline riwayat status.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $order = Order::where('customer_id', $request->user()->id)
            ->with([
                'toko:id,nama,alamat,latitude,longitude',
                'tarif',
                'kurir:id,name,avatar',
                'statusHistories.user:id,name',
                'reviews',
                'disputes',
            ])
            ->findOrFail($id);

        return response()->json([
            'order' => $order,
            'can_cancel' => $order->canCancelByCustomer(),
        ]);
    }

    /**
     * Batalkan pesanan (Hanya jika dalam cancel window: menunggu_konfirmasi / menunggu_kurir).
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'alasan' => 'nullable|string|max:500',
        ]);

        $order = Order::where('customer_id', $request->user()->id)->findOrFail($id);

        $activeRole = $request->header('X-Active-Role') ?: 'customer';
        $updated = $this->orderService->cancelOrder($order, $request->user(), $activeRole, $request->alasan);

        return response()->json([
            'message' => 'Pesanan berhasil dibatalkan.',
            'order'   => $updated,
        ]);
    }

    /**
     * Kirim ulasan toko & kurir terpisah setelah order selesai.
     */
    public function review(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'toko_rating'    => 'nullable|integer|min:1|max:5',
            'toko_komentar'  => 'nullable|string|max:1000',
            'kurir_rating'   => 'nullable|integer|min:1|max:5',
            'kurir_komentar' => 'nullable|string|max:1000',
        ]);

        $order = Order::where('customer_id', $request->user()->id)->findOrFail($id);

        $reviews = $this->orderService->submitReview($order, $request->user(), $request->all());

        return response()->json([
            'message' => 'Ulasan berhasil dikirim. Terima kasih!',
            'reviews' => $reviews,
        ], 201);
    }
}
