<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddToCartRequest;
use App\Http\Requests\UpdateCartRequest;
use App\Models\Order;
use App\Models\OrderMedicine;
use App\Models\Medicine;
use App\Models\StockBatch;
use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    protected CartService $cartService;

    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }
    public function index(Request $request)
    {
        $user = Auth::user();

        // Authorization check: User must be authenticated
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        $pharmacyId = $request->query('pharmacy_id');

        $query = Order::where('user_id', $user->id)->where('status', 'cart');
        if ($pharmacyId) $query->where('pharmacy_id', $pharmacyId);


        $carts = $query->with([
            'medicines.medicine',
            'pharmacy.user'
        ])->get();

        // Check for expired carts and clean them up
        $expiredCarts = $carts->filter(function ($cart) {
            return $this->cartService->isCartExpired($cart);
        });

        if ($expiredCarts->isNotEmpty()) {
            foreach ($expiredCarts as $cart) {
                $cart->medicines()->delete();
                $cart->delete();
            }
            $carts = $carts->diff($expiredCarts);
        }


        $pharmacyIds = $carts->pluck('pharmacy_id')->unique();

        // Safely extract medicine IDs from carts
        $medicineIds = collect();
        foreach ($carts as $cart) {
            if ($cart->medicines && $cart->medicines->isNotEmpty()) {
                $medicineIds = $medicineIds->merge($cart->medicines->pluck('medicine_id'));
            }
        }
        $medicineIds = $medicineIds->unique()->filter();

        if ($medicineIds->isNotEmpty() && $pharmacyIds->isNotEmpty()) {
            $stockData = StockBatch::whereIn('pharmacy_id', $pharmacyIds)
                ->whereIn('medicine_id', $medicineIds)
                ->get()
                ->groupBy(['pharmacy_id', 'medicine_id']);
        } else {
            $stockData = collect();
        }

        $carts->each(function ($cart) use ($stockData) {
            try {
                if ($cart->medicines && $cart->medicines->isNotEmpty()) {
                    $cart->medicines->each(function ($item) use ($cart, $stockData) {
                        try {
                            // Validate item structure and required properties
                            if (!$item || !property_exists($item, 'medicine_id') || !property_exists($item, 'price_at_time') || !property_exists($item, 'quantity')) {
                                $item->is_available = false;
                                $item->stock_status = 'unknown';
                                $item->available_stock = 0;
                                $item->subtotal = 0;
                                return; // Skip processing this item
                            }

                            // Get stock from pre-loaded data instead of querying database
                            $stock = $stockData->get($cart->pharmacy_id, collect())
                                ->get($item->medicine_id);

                            // Add computed fields for display (not saved to DB)
                            $item->is_available = $stock && $stock->quantity >= $item->quantity;
                            $item->stock_status = $this->cartService->calculateStockStatus($stock, $item->quantity);
                            $item->available_stock = $stock ? $stock->quantity : 0;
                            $item->subtotal = $item->price_at_time * $item->quantity;
                        } catch (\Exception $e) {
                            // Handle individual item errors
                            $item->is_available = false;
                            $item->stock_status = 'error';
                            $item->available_stock = 0;
                            $item->subtotal = 0;
                        }
                    });
                }

                // Calculate and save totals to database
                $subtotal = $cart->medicines->sum('subtotal') ?? 0.0;
                $totalAmount = round((float) $subtotal, 2);
                $totalItems = (int) ($cart->medicines->sum('quantity') ?? 0);

                $cart->total_amount = $totalAmount;
                $cart->total_items = $totalItems;

                // Save the updated totals to the database
                $cart->save();
            } catch (\Exception $e) {
                // Handle cart-level errors
                $cart->total_amount = 0.00;
                $cart->total_items = 0;
                $cart->save();
            }
        });

        return response()->json(['success' => true, 'data' => $carts]);
    }

    public function store(AddToCartRequest $request)
    {
        $user = Auth::user();

        // Authorization check: User must be authenticated
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        $validated = $request->validated();

        return DB::transaction(function () use ($user, $validated) {
            try {
                // Basic medicine existence check only
                $medicine = Medicine::find($validated['medicine_id']);
                if (!$medicine) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Medicine not found',
                    ], 404);
                }

                // Create or get cart with lock to prevent concurrent modifications
                $cart = Order::where('user_id', $user->id)
                    ->where('pharmacy_id', $validated['pharmacy_id'])
                    ->where('status', 'cart')
                    ->lockForUpdate()
                    ->first();

                if (!$cart) {
                    $cart = Order::create([
                        'user_id' => $user->id,
                        'pharmacy_id' => $validated['pharmacy_id'],
                        'status' => 'cart',
                        'total_amount' => 0.00,
                        'total_items' => 0
                    ]);
                }

                // Check existing item in cart
                $existing = $cart->medicines()->where('medicine_id', $validated['medicine_id'])->first();
                $newQuantity = $existing ? $existing->quantity + $validated['quantity'] : $validated['quantity'];

                // Only validate quantity limit (no stock or price validation here)
                $quantityValidation = $this->cartService->validateQuantityLimit($validated['quantity'], $existing);
                if (!$quantityValidation['valid']) {
                    return response()->json([
                        'success' => false,
                        'message' => $quantityValidation['message'],
                        'current_quantity' => $quantityValidation['current_quantity'],
                        'requested_quantity' => $quantityValidation['requested_quantity'],
                        'max_per_medicine' => $quantityValidation['max_per_medicine'],
                    ], 400);
                }

                $currentPrice = $medicine->price;

                // Update or create cart item
                if ($existing) {
                    $existing->update(['quantity' => $newQuantity]);
                } else {
                    $cart->medicines()->create([
                        'medicine_id' => $validated['medicine_id'],
                        'quantity' => $validated['quantity'],
                        'price_at_time' => $currentPrice,
                    ]);
                }

                $cart->save();

                $updatedCart = $cart->load(['medicines.medicine', 'pharmacy.user']);

                // Calculate totals for the response
                $updatedCart->total_items = $updatedCart->medicines->sum('quantity') ?? 0;
                $updatedCart->total_amount = $updatedCart->medicines->sum(function ($item) {
                    return $item->price_at_time * $item->quantity;
                }) ?? 0;

                return response()->json(['success' => true, 'message' => 'Added to cart', 'data' => $updatedCart]);

            } catch (\Exception $e) {
                \Log::error('Cart add item error: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to add item to cart',
                ], 500);
            }
        });
    }

    public function update(UpdateCartRequest $request, OrderMedicine $item)
    {
        $user = Auth::user();

        // Authorization check: User must be authenticated
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        // Authorization check: User must own the cart item
        if ($item->order->user_id !== $user->id || $item->order->status !== 'cart') {
            return response()->json(['success' => false, 'message' => 'Item not found'], 404);
        }

        $validated = $request->validated();

        return DB::transaction(function () use ($validated, $item) {
            try {
                // Lock the cart to prevent concurrent modifications
                $cart = $item->order()->lockForUpdate()->first();

                // Only validate quantity limit (no stock validation here)
                $quantityValidation = $this->cartService->validateQuantityLimit($validated['quantity']);
                if (!$quantityValidation['valid']) {
                    return response()->json([
                        'success' => false,
                        'message' => $quantityValidation['message'],
                        'requested_quantity' => $quantityValidation['requested_quantity'],
                        'max_per_medicine' => $quantityValidation['max_per_medicine'],
                    ], 400);
                }

                $item->update(['quantity' => $validated['quantity']]);
                $cart->save();

                $updatedCart = $cart->load(['medicines.medicine', 'pharmacy.user']);
                return response()->json(['success' => true, 'message' => 'Updated', 'data' => $updatedCart]);

            } catch (\Exception $e) {
                \Log::error('Cart update item error: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update cart item',
                ], 500);
            }
        });
    }

    public function destroy(OrderMedicine $item)
    {
        $user = Auth::user();

        // Authorization check: User must be authenticated
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        // Null check for order relationship
        if (!$item->order) {
            return response()->json(['success' => false, 'message' => 'Item not found - order missing'], 404);
        }

        // Authorization check: User must own the cart item
        if ($item->order->user_id !== $user->id || $item->order->status !== 'cart') {
            return response()->json(['success' => false, 'message' => 'Item not found'], 404);
        }

        $cart = $item->order;

        try {
            $item->delete();

            // Safe cart deletion with null check
            if ($cart && $cart->medicines()->count() === 0) {
                $cart->delete();
                return response()->json(['success' => true, 'message' => 'Removed', 'data' => []]);
            }

            // Safe data loading with null coalescing
            $cartData = $cart ? $cart->load(['medicines.medicine']) : null;
            return response()->json(['success' => true, 'message' => 'Removed', 'data' => $cartData ?? []]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error removing item',
                'data' => []
            ], 500);
        }
    }

   public function clear(Request $request)
{
    $user = Auth::user();

    // Authorization check: User must be authenticated
    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'Authentication required'
        ], 401);
    }

    $pharmacyId = $request->query('pharmacy_id');

    $cart = Order::where('user_id', $user->id)->where('status', 'cart');
    if ($pharmacyId) {
        $cart->where('pharmacy_id', $pharmacyId);
    }
    $cart = $cart->first();

    // Cart query completed

    if (!$cart) {
        return response()->json([
            'success' => false,
            'message' => 'No cart found for this user' . ($pharmacyId ? " or pharmacy $pharmacyId" : ''),
            'data' => []
        ], 404);
    }

    // Safe deletion with null checks
    try {
        if ($cart->medicines) {
            $cart->medicines()->delete();
        }
        $cart->delete();

        return response()->json(['success' => true, 'message' => 'Cleared', 'data' => []]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error clearing cart',
            'data' => []
        ], 500);
    }
}

    public function recommendations(Request $request)
    {
        $user = Auth::user();

        // Authorization check: User must be authenticated
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        $pharmacyId = $request->query('pharmacy_id');

        // Optimized eager loading to prevent N+1 queries
        $cart = Order::where('user_id', $user->id)
            ->where('pharmacy_id', $pharmacyId)
            ->where('status', 'cart')
            ->with([
                'medicines.medicine.therapeuticClasses',
                'medicines.medicine.activeIngredient'
            ])
            ->first();

        if (!$cart || $cart->medicines->isEmpty()) {
            return response()->json(['success' => true, 'data' => [], 'message' => 'Add medicines for recommendations']);
        }

        // Optimized data extraction with better performance
        $validMedicines = $cart->medicines->filter(function ($item) {
            return $item->medicine_id && $item->medicine;
        });

        // Extract classes and ingredients more efficiently
        $classes = collect();
        $ingredients = collect();
        $inCartIds = collect();

        foreach ($validMedicines as $item) {
            $inCartIds->push($item->medicine_id);

            if ($item->medicine->therapeuticClasses) {
                $classes = $classes->merge($item->medicine->therapeuticClasses->pluck('id'));
            }

            if ($item->medicine->activeIngredient) {
                $ingredients->push($item->medicine->activeIngredient->id);
            }
        }

        $classes = $classes->unique();
        $ingredients = $ingredients->unique()->filter();

        $recs = Medicine::whereNotIn('id', $inCartIds)
            ->where(function ($q) use ($classes, $ingredients) {
                if ($classes->isNotEmpty()) {
                    $q->whereHas('therapeuticClasses', function ($sub) use ($classes) {
                        $sub->whereIn('id', $classes);
                    });
                }
                if ($ingredients->isNotEmpty()) {
                    $q->orWhereHas('activeIngredient', function ($sub) use ($ingredients) {
                        $sub->whereIn('id', $ingredients);
                    });
                }
                if ($classes->isEmpty() && $ingredients->isEmpty()) {
                    $q->whereRaw('1=0'); // No recommendations if no basis
                }
            })
            ->whereHas('stockBatches', fn($q) => $q->where('pharmacy_id', $pharmacyId)->where('quantity', '>', 0))
            ->with(['therapeuticClasses', 'activeIngredient'])
            ->limit(6)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $recs,
            'based_on' => ['classes' => $classes->values(), 'ingredients' => $ingredients->values()]
        ]);
    }
}
