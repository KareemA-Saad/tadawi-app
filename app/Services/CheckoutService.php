<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderMedicine;
use App\Models\StockBatch;
use App\Models\Medicine;
use App\Models\PharmacyProfile;
use App\Models\User;
use App\Services\PaymentService;
use App\Traits\ImageHandling;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CheckoutService
{
    use ImageHandling;

    protected CartService $cartService;
    protected PaymentService $paymentService;

    public function __construct(CartService $cartService, PaymentService $paymentService)
    {
        $this->cartService = $cartService;
        $this->paymentService = $paymentService;
    }

    /**
     * Validate cart for checkout with comprehensive validation
     */
    public function validateCartForCheckout(int $cartId, int $userId): array
    {
        return DB::transaction(function () use ($cartId, $userId) {
            try {
                $cart = Order::where('id', $cartId)
                    ->where('user_id', $userId)
                    ->where('status', 'cart')
                    ->with(['medicines.medicine', 'pharmacy'])
                    ->lockForUpdate()
                    ->first();

                if (!$cart) {
                    return [
                        'valid' => false,
                        'message' => 'Cart not found or not accessible',
                        'errors' => ['cart_not_found']
                    ];
                }

                // Check if cart is expired
                if ($this->cartService->isCartExpired($cart)) {
                    return [
                        'valid' => false,
                        'message' => 'Cart has expired. Please add items again.',
                        'errors' => ['cart_expired']
                    ];
                }

                // Check if cart is empty
                if ($cart->medicines->isEmpty()) {
                    return [
                        'valid' => false,
                        'message' => 'Cart is empty',
                        'errors' => ['cart_empty']
                    ];
                }

                // Validate pharmacy availability
                $pharmacyValidation = $this->validatePharmacyAvailability($cart->pharmacy);
                if (!$pharmacyValidation['valid']) {
                    return $pharmacyValidation;
                }

                // Validate stock availability and price consistency
                $stockValidation = $this->validateStockAvailability($cart);
                if (!$stockValidation['valid']) {
                    return $stockValidation;
                }

                // Validate user profile
                $userValidation = $this->validateUserProfile($cart->user);
                if (!$userValidation['valid']) {
                    return $userValidation;
                }

                // Validate quantity limits
                $quantityValidation = $this->validateQuantityLimits($cart);
                if (!$quantityValidation['valid']) {
                    return $quantityValidation;
                }

                // Compute totals for parity with checkout summary without mutating DB state
                $totals = $this->calculateOrderTotals($cart);

                return [
                    'valid' => true,
                    'message' => 'Cart is ready for checkout',
                    'cart' => $cart,
                    'totals' => $totals,
                ];

            } catch (\Exception $e) {
                Log::error('Checkout validation error: ' . $e->getMessage());
                return [
                    'valid' => false,
                    'message' => 'Validation failed due to system error',
                    'errors' => ['system_error']
                ];
            }
        });
    }

    /**
     * Process checkout for a cart with enhanced transaction handling
     */
    public function processCheckout(int $cartId, int $userId, array $checkoutData): array
    {
        return DB::transaction(function () use ($cartId, $userId, $checkoutData) {
            $cart = null;
            $order = null;

            try {
                // Validate cart first with comprehensive validation
                $validation = $this->validateCartForCheckout($cartId, $userId);
                if (!$validation['valid']) {
                    return $validation;
                }

                $cart = $validation['cart'];

                // Reserve stock with proper locking
                $stockReservation = $this->reserveStockWithLocking($cart);
                if (!$stockReservation['success']) {
                    return [
                        'success' => false,
                        'message' => 'Failed to reserve stock: ' . $stockReservation['message'],
                        'errors' => ['stock_reservation_failed']
                    ];
                }

                // Convert cart to order
                $order = $this->convertCartToOrder($cart, $checkoutData);
                if (!$order) {
                    // Release reserved stock
                    $this->releaseStock($cart);
                    return [
                        'success' => false,
                        'message' => 'Failed to create order',
                        'errors' => ['order_creation_failed']
                    ];
                }

                // Update stock after successful order creation
                $this->updateStockAfterOrder($order);

                // Process payment for the order
                $paymentResult = $this->paymentService->processPayment($order, [
                    'method' => $checkoutData['payment_method'] ?? 'cash',
                    'currency' => $checkoutData['currency'] ?? 'EGP'
                ]);

                if (!$paymentResult['success']) {
                    // If payment fails, we should handle this gracefully
                    Log::warning("Payment failed for order {$order->id}: " . $paymentResult['message']);
                    // Continue with order creation but log the payment failure
                }

                // Delete cart after successful order creation
                $cart->medicines()->delete();
                $cart->delete();

                Log::info("Checkout completed for cart {$cartId}, order {$order->id}");

                return [
                    'success' => true,
                    'message' => 'Checkout completed successfully',
                    'order' => $order,
                    'order_id' => $order->id,
                    'payment_result' => $paymentResult
                ];

            } catch (\Exception $e) {
                Log::error('Checkout processing error: ' . $e->getMessage());

                // Rollback: release stock if it was reserved
                if ($cart) {
                    $this->releaseStock($cart);
                }

                return [
                    'success' => false,
                    'message' => 'Checkout failed due to system error',
                    'errors' => ['system_error']
                ];
            }
        }, 3); // Retry up to 3 times for deadlocks
    }

    /**
     * Calculate order totals
     */
    public function calculateOrderTotals(Order $cart): array
    {
        $subtotal = 0;
        $totalItems = 0;

        foreach ($cart->medicines as $item) {
            $itemSubtotal = $item->price_at_time * $item->quantity;
            $subtotal += $itemSubtotal;
            $totalItems += $item->quantity;
        }

        // Calculate tax (14%)
        $tax = 14/100 * $subtotal;

        // Calculate shipping (30 EGP)
        $shipping = 30;

        $total = $subtotal + $tax + $shipping;

        return [
            'total_amount' => round($total, 2),
            'total_items' => $totalItems,
            'subtotal' => round($subtotal, 2),
            'tax' => round($tax, 2),
            'shipping' => round($shipping, 2),
        ];
    }

    /**
     * Validate pharmacy availability
     */
    protected function validatePharmacyAvailability(PharmacyProfile $pharmacy): array
    {
        if (!$pharmacy->verified) {
            return [
                'valid' => false,
                'message' => 'Pharmacy is not verified',
                'errors' => ['pharmacy_not_verified']
            ];
        }

        if ($pharmacy->status !== 'active') {
            return [
                'valid' => false,
                'message' => 'Pharmacy is not currently accepting orders',
                'errors' => ['pharmacy_inactive']
            ];
        }

        return ['valid' => true];
    }

    /**
     * Validate stock availability with proper locking
     */
    protected function validateStockAvailability(Order $cart): array
    {
        $unavailableItems = [];
        $priceChangedItems = [];

        foreach ($cart->medicines as $item) {
            // Lock stock for update to prevent race conditions
            $stock = StockBatch::where('pharmacy_id', $cart->pharmacy_id)
                ->where('medicine_id', $item->medicine_id)
                ->lockForUpdate()
                ->first();

            if (!$stock) {
                $unavailableItems[] = [
                    'medicine_id' => $item->medicine_id,
                    'medicine_name' => $item->medicine->brand_name ?? 'Unknown',
                    'requested_quantity' => $item->quantity,
                    'available_quantity' => 0,
                    'reason' => 'not_available'
                ];
                continue;
            }

            if ($stock->quantity < $item->quantity) {
                $unavailableItems[] = [
                    'medicine_id' => $item->medicine_id,
                    'medicine_name' => $item->medicine->brand_name ?? 'Unknown',
                    'requested_quantity' => $item->quantity,
                    'available_quantity' => $stock->quantity,
                    'reason' => 'insufficient_stock'
                ];
            }

            // Check for price changes
            $currentPrice = $item->medicine->price;
            if ($item->price_at_time != $currentPrice) {
                $priceChangedItems[] = [
                    'medicine_id' => $item->medicine_id,
                    'medicine_name' => $item->medicine->brand_name ?? 'Unknown',
                    'old_price' => $item->price_at_time,
                    'new_price' => $currentPrice,
                    'price_change' => $currentPrice - $item->price_at_time
                ];
            }
        }

        $errors = [];
        if (!empty($unavailableItems)) {
            $errors[] = 'insufficient_stock';
        }
        if (!empty($priceChangedItems)) {
            $errors[] = 'price_changed';
        }

        if (!empty($errors)) {
            return [
                'valid' => false,
                'message' => 'Some items are no longer available or have price changes',
                'errors' => $errors,
                'unavailable_items' => $unavailableItems,
                'price_changed_items' => $priceChangedItems
            ];
        }

        return ['valid' => true];
    }

    /**
     * Validate user profile
     */
    protected function validateUserProfile(User $user): array
    {
        if (!$user->email_verified_at) {
            return [
                'valid' => false,
                'message' => 'Please verify your email address before checkout',
                'errors' => ['email_not_verified']
            ];
        }

        // Add more user validation as needed
        return ['valid' => true];
    }

    /**
     * Validate quantity limits for all items in cart
     */
    protected function validateQuantityLimits(Order $cart): array
    {
        $config = $this->cartService->getConfig();
        $maxPerMedicine = $config['max_quantity_per_medicine'];

        $exceededItems = [];

        foreach ($cart->medicines as $item) {
            if ($item->quantity > $maxPerMedicine) {
                $exceededItems[] = [
                    'medicine_id' => $item->medicine_id,
                    'medicine_name' => $item->medicine->brand_name ?? 'Unknown',
                    'quantity' => $item->quantity,
                    'max_allowed' => $maxPerMedicine
                ];
            }
        }

        if (!empty($exceededItems)) {
            return [
                'valid' => false,
                'message' => 'Some items exceed the maximum quantity limit',
                'errors' => ['quantity_limit_exceeded'],
                'exceeded_items' => $exceededItems
            ];
        }

        return ['valid' => true];
    }

    /**
     * Convert cart to order
     */
    protected function convertCartToOrder(Order $cart, array $checkoutData): ?Order
    {
        try {
            // Calculate proper totals with tax and shipping
            $totals = $this->calculateOrderTotals($cart);

            $order = Order::create([
                'user_id' => $cart->user_id,
                'pharmacy_id' => $cart->pharmacy_id,
                'status' => 'pending',
                'payment_method' => $checkoutData['payment_method'] ?? 'cash',
                'billing_address' => $checkoutData['billing_address'] ?? null,
                'shipping_address' => $checkoutData['shipping_address'] ?? null,
                'total_items' => $totals['total_items'],
                'total_amount' => $totals['total_amount'],
                'currency' => $checkoutData['currency'] ?? 'EGP',
            ]);

            // Copy cart items to order
            foreach ($cart->medicines as $cartItem) {
                $order->medicines()->create([
                    'medicine_id' => $cartItem->medicine_id,
                    'quantity' => $cartItem->quantity,
                    'price_at_time' => $cartItem->price_at_time,
                ]);
            }

            // Process prescription files if required
            if (isset($checkoutData['prescription_required']) && $checkoutData['prescription_required'] && isset($checkoutData['prescription_files'])) {
                $this->processPrescriptionFiles($order, $checkoutData['prescription_files']);
            }

            return $order;

        } catch (\Exception $e) {
            Log::error('Order creation error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get checkout summary
     */
    public function getCheckoutSummary(int $cartId, int $userId): array
    {
        $validation = $this->validateCartForCheckout($cartId, $userId);

        if (!$validation['valid']) {
            return $validation;
        }

        $cart = $validation['cart'];
        $totals = $this->calculateOrderTotals($cart);

        return [
            'success' => true,
            'cart' => [
                'id' => $cart->id,
                'user_id' => $cart->user_id,
                'pharmacy_id' => $cart->pharmacy_id,
                'status' => $cart->status
            ],
            'pharmacy' => [
                'id' => $cart->pharmacy->id,
                'name' => $cart->pharmacy->location ?? 'Unknown Pharmacy',
                'address' => $cart->pharmacy->location ?? 'Unknown Address',
                'phone' => $cart->pharmacy->contact_info ?? 'Unknown Phone'
            ],
            'medicines' => $cart->medicines->map(function ($item) {
                return [
                    'id' => $item->medicine_id,
                    'name' => $item->medicine->brand_name ?? 'Unknown',
                    'quantity' => $item->quantity,
                    'price' => $item->price_at_time,
                    'subtotal' => $item->price_at_time * $item->quantity
                ];
            }),
            'totals' => $totals,
            'estimated_delivery' => $this->getEstimatedDelivery($cart->pharmacy_id)
        ];
    }

    /**
     * Get estimated delivery time
     */
    protected function getEstimatedDelivery(int $pharmacyId): string
    {
        // This can be enhanced with actual delivery logic
        return '1-2 business days';
    }

    /**
     * Reserve stock with proper locking to prevent race conditions
     */
    protected function reserveStockWithLocking(Order $cart): array
    {
        try {
            $reservedItems = [];

            foreach ($cart->medicines as $item) {
                // Lock stock for update
                $stock = StockBatch::where('pharmacy_id', $cart->pharmacy_id)
                    ->where('medicine_id', $item->medicine_id)
                    ->lockForUpdate()
                    ->first();

                if (!$stock) {
                    // Release previously reserved items
                    $this->releaseReservedStock($reservedItems);
                    return [
                        'success' => false,
                        'message' => "Medicine {$item->medicine->brand_name} is no longer available"
                    ];
                }

                if ($stock->quantity < $item->quantity) {
                    // Release previously reserved items
                    $this->releaseReservedStock($reservedItems);
                    return [
                        'success' => false,
                        'message' => "Insufficient stock for {$item->medicine->brand_name}. Available: {$stock->quantity}, Required: {$item->quantity}"
                    ];
                }

                // Reserve the stock
                $stock->quantity -= $item->quantity;
                $stock->save();

                $reservedItems[] = [
                    'stock_id' => $stock->id,
                    'medicine_id' => $item->medicine_id,
                    'quantity' => $item->quantity
                ];
            }

            return ['success' => true, 'message' => 'Stock reserved successfully', 'reserved_items' => $reservedItems];

        } catch (\Exception $e) {
            // Release any partially reserved stock
            if (isset($reservedItems)) {
                $this->releaseReservedStock($reservedItems);
            }
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Reserve stock (placeholder - will be moved to InventoryService)
     */
    protected function reserveStock(Order $cart): array
    {
        try {
            // This is a placeholder implementation
            // Will be moved to InventoryService in Step 4
            return ['success' => true, 'message' => 'Stock reserved'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Release reserved stock items
     */
    protected function releaseReservedStock(array $reservedItems): void
    {
        foreach ($reservedItems as $item) {
            try {
                $stock = StockBatch::find($item['stock_id']);
                if ($stock) {
                    $stock->quantity += $item['quantity'];
                    $stock->save();
                }
            } catch (\Exception $e) {
                Log::error("Failed to release stock for item {$item['stock_id']}: " . $e->getMessage());
            }
        }
    }

    /**
     * Release stock (placeholder - will be moved to InventoryService)
     */
    protected function releaseStock(Order $cart): array
    {
        try {
            // This is a placeholder implementation
            // Will be moved to InventoryService in Step 4
            return ['success' => true, 'message' => 'Stock released'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Update stock after order (placeholder - will be moved to InventoryService)
     */
    protected function updateStockAfterOrder(Order $order): array
    {
        try {
            // This is a placeholder implementation
            // Will be moved to InventoryService in Step 4
            return ['success' => true, 'message' => 'Stock updated'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Process prescription files for an order
     */
    protected function processPrescriptionFiles(Order $order, array $prescriptionFiles): void
    {
        foreach ($prescriptionFiles as $file) {
            if ($file && $file->isValid()) {
                $filename = $this->uploadImage($file, 'prescriptions', 'prescription');

                $order->prescriptionUploads()->create([
                    'file_path' => $filename,
                    'ocr_text' => null,
                    'validated_by_doctor' => false,
                ]);
            }
        }
    }
}
