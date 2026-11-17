<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\PublicTokoController;
use App\Http\Controllers\Api\OwnerToko\TokoManagementController;
use App\Http\Controllers\Api\OwnerToko\TarifController;
use App\Http\Controllers\Api\OwnerToko\StaffTokoController;
use App\Http\Controllers\Api\OwnerToko\TokoOrderController;
use App\Http\Controllers\Api\OwnerToko\SettlementController;
use App\Http\Controllers\Api\AdminOwner\TokoApprovalController;
use App\Http\Controllers\Api\AdminOwner\DisputeController;
use App\Http\Controllers\Api\AdminOwner\UserController;
use App\Http\Controllers\Api\PlatformOwner\FinanceController;
use App\Http\Controllers\Api\PlatformOwner\AuditLogController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Api\StaffInvitationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\Api\Customer\CustomerOrderController;
use App\Http\Controllers\Api\Customer\CustomerTrackingController;
use App\Http\Controllers\Api\Customer\CustomerReviewController;
use App\Http\Controllers\Api\Kurir\KurirOrderController;
use App\Http\Controllers\Api\Kurir\KurirTrackingController;
use App\Http\Controllers\Api\Kurir\KurirReviewController;
use App\Http\Controllers\Api\DisputeSubmissionController;
use App\Http\Controllers\Api\ChatbotController;
use App\Http\Controllers\Api\Management\AccountManagementController;
use App\Http\Controllers\Api\NotificationController;

// 1. Health Check Endpoint (Public)
Route::get('/health', [HealthController::class, 'index']);

// 2. Marketplace Public Listing (Hanya menampilkan toko 'approved')
Route::get('/tokos', [PublicTokoController::class, 'index']);
Route::get('/tokos/{id}', [PublicTokoController::class, 'show']);

// 3. Auth Routes (Public)
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])->name('verification.verify');
});

// Undangan Staf Publik (Melihat detail & Menerima undangan)
Route::prefix('invitations')->group(function () {
    Route::get('/details', [StaffInvitationController::class, 'getDetails']);
    Route::post('/accept', [StaffInvitationController::class, 'accept']);
});

// 4. Authenticated Routes (Sanctum)
Route::middleware(['auth:sanctum'])->group(function () {
    
    // Verifikasi 2FA (Boleh diakses saat token berstatus 2fa:pending)
    Route::prefix('auth/2fa')->group(function () {
        Route::get('/status', [TwoFactorController::class, 'status']);
        Route::get('/setup', [TwoFactorController::class, 'setup']);
        Route::post('/verify', [TwoFactorController::class, 'verify']);
    });

    // Kirim Ulang Email Verifikasi & Logout
    Route::post('/auth/email/resend', [AuthController::class, 'resendVerification']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    
    // Rute yang membutuhkan verifikasi token penuh (bukan 2fa:pending)
    Route::middleware(['require2fa'])->group(function () {
        
        // Ubah Password dari Dalam Akun
        Route::post('/auth/change-password', [AuthController::class, 'changePassword']);

        // Kelola Undangan Staf (Kirim & Batalkan)
        Route::post('/invitations', [StaffInvitationController::class, 'invite']);
        Route::post('/invitations/{id}/cancel', [StaffInvitationController::class, 'cancel']);
        
        // Info User & Active Roles
        Route::get('/auth/me', function (Request $request) {
            return response()->json([
                'user' => $request->user()->load('profile', 'roles'),
                'roles' => $request->user()->getRoleNames(),
            ]);
        });
        
        // Onboarding Profil Customer
        Route::get('/user/profile', [ProfileController::class, 'show']);
        Route::post('/user/profile', [ProfileController::class, 'store']);
        
        // Pendaftaran Role Owner Toko (Mitra)
        Route::post('/user/become-owner', [RoleController::class, 'becomeOwner']);
        
        // ==========================================
        // NOTIFIKASI & PREFERENSI (Semua Role User)
        // ==========================================
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
        Route::get('/notification-preferences', [NotificationController::class, 'getPreferences']);
        Route::put('/notification-preferences', [NotificationController::class, 'updatePreferences']);
        
        // ==========================================
        // MODUL OWNER TOKO & ADMIN TOKO
        // ==========================================
        Route::prefix('owner')->group(function () {
            // List toko milik user atau toko tempat bertugas
            Route::get('/tokos', [TokoManagementController::class, 'index']);
            
            // Onboarding toko baru (Owner Toko)
            Route::post('/tokos', [TokoManagementController::class, 'store']);
            
            // Resource toko dengan proteksi Defense in Depth Lapis 1 (ScopedTokoMiddleware)
            Route::middleware(['scoped.toko:toko'])->group(function () {
                Route::get('/tokos/{toko}', [TokoManagementController::class, 'show']);
                Route::post('/tokos/{toko}', [TokoManagementController::class, 'update']);
                
                // Tarif layanan toko
                Route::get('/tokos/{toko}/tarifs', [TarifController::class, 'index']);
                Route::post('/tokos/{toko}/tarifs', [TarifController::class, 'store']);
                
                // Kelola Admin Toko (Staff) - Hanya untuk Owner Toko
                Route::get('/tokos/{toko}/staff', [StaffTokoController::class, 'index']);
                Route::post('/tokos/{toko}/staff', [StaffTokoController::class, 'store']);
                Route::delete('/tokos/{toko}/staff/{staffToko}', [StaffTokoController::class, 'destroy']);

                // Riwayat Settlement / Payout toko sendiri (Hanya Owner Toko)
                Route::get('/tokos/{toko}/settlement', [SettlementController::class, 'index']);
            });

            // Update & Delete tarif spesifik
            Route::put('/tarifs/{tarif}', [TarifController::class, 'update']);
            Route::delete('/tarifs/{tarif}', [TarifController::class, 'destroy']);

            // ==========================================
            // KELOLA PESANAN TOKO (Owner & Admin Toko)
            // ==========================================
            Route::get('/tokos/{toko}/orders', [TokoOrderController::class, 'index']);
            Route::post('/orders/{id}/confirm', [TokoOrderController::class, 'confirm']);
            Route::post('/orders/{id}/weigh', [TokoOrderController::class, 'weigh']);
            Route::post('/orders/{id}/status', [TokoOrderController::class, 'updateStatus']);
        });

        // ==========================================
        // MODUL ORDER CUSTOMER
        // ==========================================
        Route::prefix('customer')->group(function () {
            Route::get('/orders', [CustomerOrderController::class, 'index']);
            Route::post('/orders', [CustomerOrderController::class, 'store']);
            Route::get('/orders/{id}', [CustomerOrderController::class, 'show']);
            Route::post('/orders/{id}/cancel', [CustomerOrderController::class, 'cancel']);
            Route::post('/orders/{id}/review', [CustomerOrderController::class, 'review']);
            Route::get('/orders/{id}/tracking', [CustomerTrackingController::class, 'getKurirLocation']);
            Route::get('/reviews', [CustomerReviewController::class, 'index']);
        });

        // ==========================================
        // MODUL TUGAS KURIR
        // ==========================================
        Route::prefix('kurir')->group(function () {
            Route::get('/tasks/available', [KurirOrderController::class, 'availableTasks']);
            Route::get('/tasks/my', [KurirOrderController::class, 'myTasks']);
            Route::post('/tasks/{id}/accept', [KurirOrderController::class, 'acceptTask']);
            Route::post('/tasks/{id}/release', [KurirOrderController::class, 'releaseTask']);
            Route::post('/tasks/{id}/status', [KurirOrderController::class, 'updateStatus']);
            Route::post('/location/ping', [KurirTrackingController::class, 'ping']);
            Route::get('/reviews', [KurirReviewController::class, 'index']);
        });

        // ==========================================
        // MODUL DISPUTE / KOMPLAIN PESANAN (Customer & Toko)
        // ==========================================
        // AI Chatbot
        Route::post('/chatbot/message', [ChatbotController::class, 'message']);

        Route::post('/orders/{id}/dispute', [DisputeSubmissionController::class, 'store']);
        Route::get('/orders/{id}/disputes', [DisputeSubmissionController::class, 'index']);

        // ==========================================
        // MODUL MANAJEMEN AKUN & UNDANGAN STAF (Least Privilege)
        // Platform Owner, Admin Owner, & Owner Toko
        // ==========================================
        Route::prefix('management')->group(function () {
            // Platform Owner exclusive routes
            Route::middleware(['active.role:platform_owner'])->group(function () {
                Route::get('/pending-accounts/count', [AccountManagementController::class, 'pendingAccountsCount']);
                Route::get('/pending-accounts', [AccountManagementController::class, 'pendingAccounts']);
                Route::post('/users/{id}/approve', [AccountManagementController::class, 'approveUser']);
                Route::post('/users/{id}/reject', [AccountManagementController::class, 'rejectUser']);
                Route::post('/users/bulk-approve', [AccountManagementController::class, 'bulkApprove']);
                Route::post('/users/bulk-reject', [AccountManagementController::class, 'bulkReject']);
                Route::delete('/users/{id}', [AccountManagementController::class, 'destroy']);
                Route::get('/settings', [AccountManagementController::class, 'getSettings']);
                Route::put('/settings', [AccountManagementController::class, 'updateSettings']);
            });

            Route::get('/users', [AccountManagementController::class, 'index']);
            Route::get('/users/{id}', [AccountManagementController::class, 'show']);
            Route::patch('/users/{id}/status', [AccountManagementController::class, 'updateStatus']);
            Route::patch('/users/{id}/role', [AccountManagementController::class, 'updateRole']);
            Route::post('/users/{id}/revoke-sessions', [AccountManagementController::class, 'revokeSessions']);
            Route::post('/users/{id}/reset-password', [AccountManagementController::class, 'triggerResetPassword']);
            Route::get('/invitations', [AccountManagementController::class, 'listInvitations']);
        });

        // ==========================================
        // MODUL PLATFORM MANAGEMENT (Platform Owner & Admin Owner)
        // ==========================================
        Route::prefix('platform')->group(function () {
            
            // 1. Approval & Moderasi Toko (Admin Owner & Platform Owner)
            Route::get('/tokos', [TokoApprovalController::class, 'index'])->middleware('active.role:platform_owner,admin_owner');
            Route::patch('/tokos/{toko}/status', [TokoApprovalController::class, 'updateStatus'])->middleware('active.role:platform_owner,admin_owner');
            
            // 2. Kelola Dispute (Admin Owner & Platform Owner)
            Route::get('/disputes', [DisputeController::class, 'index'])->middleware('active.role:platform_owner,admin_owner');
            Route::patch('/disputes/{id}', [DisputeController::class, 'update'])->middleware('active.role:platform_owner,admin_owner');
            
            // 3. Kelola User (Admin Owner & Platform Owner)
            Route::get('/users', [UserController::class, 'index'])->middleware('active.role:platform_owner,admin_owner');
            Route::patch('/users/{id}/status', [UserController::class, 'toggleStatus'])->middleware('active.role:platform_owner,admin_owner');
            
            // ==========================================
            // KEUANGAN, KOMISI INTI & AUDIT LOG (EKSKLUSIF HANYA PLATFORM OWNER)
            // Admin Owner DILARANG KERAS mengakses grup ini (Enforced by Middleware & Policy)
            // ==========================================
            Route::middleware(['active.role:platform_owner'])->group(function () {
                Route::get('/overview', [FinanceController::class, 'getOverview']);
                Route::get('/finances/commission', [FinanceController::class, 'getCommissionSettings']);
                Route::put('/finances/commission', [FinanceController::class, 'updateCommissionSettings']);
                Route::get('/finances/settlements', [FinanceController::class, 'getAllSettlements']);
                Route::post('/finances/settlements/{tokoId}/payout', [FinanceController::class, 'processPayout']);
                Route::get('/audit-logs', [AuditLogController::class, 'index']);
            });
        });

    });
});
