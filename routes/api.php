<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConflictController;
use App\Http\Controllers\Api\DonationController;
use App\Http\Controllers\Api\MedicineController;
use App\Http\Controllers\Api\SearchController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AlternativeSearchController;
use App\Http\Controllers\Api\PharmacyController;
use App\Http\Controllers\Api\StockBatchController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\MedicineCorrectionController;


Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        // Public routes
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);

        // OTP routes - accessible both publicly and for authenticated users
        Route::post('verify-otp', [AuthController::class, 'verifyOtp']);
        Route::post('resend-otp', [AuthController::class, 'resendOtp']);

        // Password reset routes - public access
        Route::post('send-password-reset-otp', [AuthController::class, 'sendPasswordResetOtp']);
        Route::post('reset-password', [AuthController::class, 'resetPassword']);

        // Google OAuth
        Route::get('google/redirect', [AuthController::class, 'googleRedirect']);
        Route::get('google/callback', [AuthController::class, 'googleCallback']);

        // Protected routes
        Route::middleware('auth:sanctum')->group(function () {
            // Authenticated (no verification required)
            Route::get('me', [AuthController::class, 'me']);
            Route::post('logout', [AuthController::class, 'logout']);

            // Authenticated + email verified
            Route::middleware('verified')->group(function () {
                Route::post('update-role', [AuthController::class, 'updateRole']);
            });

            // Cart routes
            Route::get('/cart', [CartController::class, 'index']);
            Route::post('/cart', [CartController::class, 'store'])->name('cart.add');
            Route::delete('/cart/clear', [CartController::class, 'clear'])->name('cart.clear');
            Route::get('/cart/recommendations', [CartController::class, 'recommendations'])->name('cart.recommendations');

        });

        // Drug interaction routes
       // Route::get('/suggest-drugs', [InteractionController::class, 'suggest']);
        //Route::post('/check-interaction', [DrugInteractionController::class, 'checkInteraction']);
    });
    Route::get('pharmacies', [PharmacyController::class, 'index']);
    Route::get('search', [SearchController::class, 'search']);
    // search routes
    Route::middleware(['auth:sanctum'])->group(function () {
        // Search routes

        Route::post('search/with-alternatives', [AlternativeSearchController::class, 'search']);

        // Checkout routes
        Route::get('checkout/validate/{pharmacy_id}', [CheckoutController::class, 'validateCart'])->name('checkout.validate');
        Route::get('checkout/summary/{pharmacy_id}', [CheckoutController::class, 'getSummary'])->name('checkout.summary');
        Route::post('checkout/initiate/{pharmacy_id}', [CheckoutController::class, 'initiate'])->name('checkout.initiate');
        Route::post('checkout/paypal/{pharmacy_id}', [CheckoutController::class, 'processPayPal'])->name('checkout.paypal');
        Route::get('checkout/payment-status/{order_id}', [CheckoutController::class, 'getPaymentStatus'])->name('checkout.payment-status');
        Route::get('checkout/paypal/config', [CheckoutController::class, 'getPayPalConfig'])->name('checkout.paypal.config');

        // Order history routes
        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/stats', [OrderController::class, 'stats'])->name('orders.stats');
        Route::get('orders/{id}', [OrderController::class, 'show'])->name('orders.show');

        // Pharmacy routes
        
        Route::get('pharmacies/nearby', [PharmacyController::class, 'nearby']);
        Route::get('pharmacies/my', [PharmacyController::class, 'myPharmacy']);
        Route::get('pharmacies/{id}', [PharmacyController::class, 'show']);
        Route::post('pharmacies', [PharmacyController::class, 'store']);
        Route::put('pharmacies/{id}', [PharmacyController::class, 'update']);
        Route::delete('pharmacies/{id}', [PharmacyController::class, 'destroy']);

        // Stock Batch routes
        Route::get('stock-batches', [StockBatchController::class, 'index']);
        Route::get('stock-batches/summary', [StockBatchController::class, 'summary']);
        Route::get('stock-batches/expired', [StockBatchController::class, 'expired']);
        Route::get('stock-batches/expiring-soon', [StockBatchController::class, 'expiringSoon']);
        Route::get('stock-batches/low-stock', [StockBatchController::class, 'lowStock']);
        Route::get('stock-batches/{id}', [StockBatchController::class, 'show']);
        Route::post('stock-batches', [StockBatchController::class, 'store']);
        Route::put('stock-batches/{id}', [StockBatchController::class, 'update']);
        Route::delete('stock-batches/{id}', [StockBatchController::class, 'destroy']);

         // Pharmacy medicine management routes (Pharmacy can only add, not update medicines)
         Route::post('pharmacies/medicines/add', [PharmacyController::class, 'addMedicine']);
         Route::post('pharmacies/medicines/confirm-correction', [PharmacyController::class, 'confirmMedicineCorrection']);
         Route::get('pharmacies/medicines', [PharmacyController::class, 'getMedicines']);
         Route::get('pharmacies/medicines/suggestions', [PharmacyController::class, 'getMedicineSuggestions']);

        // Medicine Correction routes
        Route::post('medicine-correction/correct', [MedicineCorrectionController::class, 'correctMedicine']);
        Route::get('medicine-correction/autocomplete', [MedicineCorrectionController::class, 'autocomplete']);
        Route::post('medicine-correction/validate', [MedicineCorrectionController::class, 'validateForSave']);

    // Donation routes - require authentication
    Route::middleware(['auth:sanctum'])->group(function () {
        // Medicines search (authenticated, minimal data)
        Route::get('medicines/search', [MedicineController::class, 'search']);

        // User's own donations

        Route::get('donations', [DonationController::class, 'index']);
        Route::post('donations', [DonationController::class, 'store']);
        Route::get('donations/{id}', [DonationController::class, 'show']);
        Route::put('donations/{id}', [DonationController::class, 'update']);
        Route::delete('donations/{id}', [DonationController::class, 'destroy']);

        // Public donations (for searching)
        Route::get('donations-available', [DonationController::class, 'available']);

        // Drug interaction check - requires authentication to access medical history
        Route::post('interactions/check', [ConflictController::class, 'check']);
        Route::get('/medicines', function () {return \App\Models\Medicine::pluck('brand_name');});

    });

    // Admin/Public routes for viewing all donations
    Route::get('donations-all', [DonationController::class, 'all']);

    // PayPal webhook (public route)
    Route::post('checkout/paypal/webhook', [CheckoutController::class, 'paypalWebhook'])->name('checkout.paypal.webhook');

 });
});
