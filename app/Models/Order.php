<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'pharmacy_id',
        'status',
        'payment_method',
        'billing_address',
        'total_items', 
        'total_amount', 
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => 'string',
        'payment_method' => 'string',
        'total_items' => 'integer',
        'total_amount' => 'decimal:2',
    ];

    /**
     * Attributes to append to model's array/json form.
     */
    protected $appends = [
        'pharmacy_name',
    ];

    /**
     * Get the user that placed this order.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the pharmacy that will fulfill this order.
     */
    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(PharmacyProfile::class, 'pharmacy_id');
    }

    /**
     * Get the medicines in this order (via OrderMedicine).
     */
    public function medicines(): HasMany
    {
        return $this->hasMany(OrderMedicine::class);
    }

    /**
     * Get the prescription uploads for this order.
     */
    public function prescriptionUploads(): HasMany
    {
        return $this->hasMany(PrescriptionUpload::class);
    }

    /**
     * Get the payments for this order.
     */

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Accessor for pharmacy_name derived from related pharmacy's user name.
     */
    public function getPharmacyNameAttribute(): ?string
    {
        return data_get($this, 'pharmacy.user.name');

    }

    /**
     * Get or create cart for user and pharmacy.
     */
    public static function getCart($userId, $pharmacyId)
    {
        return self::firstOrCreate(
            ['user_id' => $userId, 'pharmacy_id' => $pharmacyId, 'status' => 'cart'],
            ['total_items' => 0, 'total_amount' => 0.00]
        );
    }

    /**
     * Calculate totals and validate max items before saving.
     */
    protected static function booted()
    {
        static::saving(function ($order) {
            if ($order->status === 'cart') {
                // Load medicines if not already loaded
                if (!$order->relationLoaded('medicines')) {
                    $order->load('medicines');
                }
                
                $order->total_items = $order->medicines->sum('quantity') ?? 0;
                $order->total_amount = $order->medicines->sum(function ($item) {
                    return $item->price_at_time * $item->quantity;
                }) ?? 0;

                if ($order->total_items > 10) {
                    throw new \Exception('Cart cannot contain more than 10 total items');
                }
            }
        });
    }

    // ========================================
    // ORDER STATUS MANAGEMENT METHODS
    // ========================================

    /**
     * Check if order is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if order is processing
     */
    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    /**
     * Check if order is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if order is cancelled
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Check if order is a cart
     */
    public function isCart(): bool
    {
        return $this->status === 'cart';
    }

    // ========================================
    // ORDER CONVERSION METHODS
    // ========================================

    /**
     * Convert cart to order
     */
    public function convertFromCart(array $checkoutData): Order
    {
        if (!$this->isCart()) {
            throw new \Exception('Cannot convert non-cart order');
        }

        $this->update([
            'status' => 'pending',
            'payment_method' => $checkoutData['payment_method'] ?? 'cash',
            'billing_address' => $checkoutData['billing_address'] ?? null,
        ]);

        return $this;
    }

    /**
     * Calculate order totals
     */
    public function calculateTotals(): array
    {
        $subtotal = $this->medicines->sum(function ($item) {
            return $item->price_at_time * $item->quantity;
        });

        $totalItems = $this->medicines->sum('quantity');
        
        // Calculate tax (can be added later)
        $tax = 14/100 * $subtotal;
        
        // Calculate shipping (can be added later)
        $shipping = 30;
        
        $total = $subtotal + $tax + $shipping;

        return [
            'subtotal' => round($subtotal, 2),
            'tax' => round($tax, 2),
            'shipping' => round($shipping, 2),
            'total' => round($total, 2),
            'total_items' => $totalItems,
        ];
    }

    /**
     * Recalculate and update order totals
     */
    public function recalculateTotals(): bool
    {
        if ($this->status === 'cart') {
            $totals = $this->calculateTotals();
            
            $this->update([
                'total_items' => $totals['total_items'],
                'total_amount' => $totals['total']
            ]);
            
            return true;
        }
        
        return false;
    }

    /**
     * Update order status
     */
    public function updateStatus(string $status): bool
    {
        $validStatuses = ['pending', 'processing', 'completed', 'cancelled'];
        
        if (!in_array($status, $validStatuses)) {
            throw new \Exception('Invalid order status: ' . $status);
        }

        return $this->update(['status' => $status]);
    }

    // ========================================
    // ORDER VALIDATION METHODS
    // ========================================

    /**
     * Validate order for checkout
     */
    public function canBeCheckedOut(): bool
    {
        // Must be a cart
        if (!$this->isCart()) {
            return false;
        }

        // Must have items
        if ($this->medicines->isEmpty()) {
            return false;
        }

        // Must have valid items
        if (!$this->hasValidItems()) {
            return false;
        }

        // Must be from valid pharmacy
        if (!$this->isFromValidPharmacy()) {
            return false;
        }

        return true;
    }

    /**
     * Check if order has valid items
     */
    public function hasValidItems(): bool
    {
        foreach ($this->medicines as $item) {
            // Check if medicine exists
            if (!$item->medicine) {
                return false;
            }

            // Check if quantity is valid
            if ($item->quantity <= 0) {
                return false;
            }

            // Check if price is valid
            if ($item->price_at_time <= 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if order is from valid pharmacy
     */
    public function isFromValidPharmacy(): bool
    {
        if (!$this->pharmacy) {
            return false;
        }

        // Check if pharmacy is verified
        if (!$this->pharmacy->verified) {
            return false;
        }

        // Check if pharmacy is active
        if ($this->pharmacy->status !== 'active') {
            return false;
        }

        return true;
    }

    // ========================================
    // ORDER PROCESSING METHODS
    // ========================================

    /**
     * Process order completion
     */
    public function markAsCompleted(): bool
    {
        if (!$this->isProcessing()) {
            throw new \Exception('Only processing orders can be marked as completed');
        }

        return $this->updateStatus('completed');
    }

    /**
     * Process order cancellation
     */
    public function markAsCancelled(string $reason = null): bool
    {
        if ($this->isCompleted()) {
            throw new \Exception('Completed orders cannot be cancelled');
        }

        $this->updateStatus('cancelled');
        
        // Log cancellation reason
        if ($reason) {

            Log::info("Order {$this->id} cancelled: {$reason}");
        }

        return true;
    }

    /**
     * Add prescription to order
     */

    /*public function addPrescription(array $prescriptionData): bool
    {   
        try {
            $this->prescriptionUploads()->create([
                'file_path' => $prescriptionData['file_path'],
                'file_name' => $prescriptionData['file_name'],
                'file_size' => $prescriptionData['file_size'],
                'mime_type' => $prescriptionData['mime_type'],

                'uploaded_by' => optional(auth()->user())->id,
            ]);

            return true;
        } catch (\Exception $e) {

            Log::error('Failed to add prescription to order: ' . $e->getMessage());
            return false;
        }
    }*/


    // ========================================
    // ORDER QUERY SCOPES
    // ========================================

    /**
     * Scope for active orders (not cancelled or deleted)
     */
    public function scopeActive($query)
    {
        return $query->whereNotIn('status', ['cancelled'])
                    ->whereNull('deleted_at');
    }

    /**
     * Scope for user orders
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for pharmacy orders
     */
    public function scopeForPharmacy($query, $pharmacyId)
    {
        return $query->where('pharmacy_id', $pharmacyId);
    }

    /**
     * Scope for orders by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for recent orders
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // ========================================
    // ORDER UTILITY METHODS
    // ========================================

    /**
     * Get order summary for display
     */
    public function getSummary(): array
    {
        $totals = $this->calculateTotals();
        
        return [
            'id' => $this->id,
            'status' => $this->status,
            'pharmacy' => [
                'id' => data_get($this, 'pharmacy.id'),
                'name' => data_get($this, 'pharmacy_name', 'Unknown Pharmacy'),
                'location' => data_get($this, 'pharmacy.location', 'Unknown Location'),
            ],
            'medicines' => $this->medicines->map(function ($item) {
                return [
                    'id' => $item->medicine_id,
                    'name' => $item->medicine->brand_name ?? 'Unknown Medicine',
                    'quantity' => $item->quantity,
                    'price' => $item->price_at_time,
                    'subtotal' => $item->price_at_time * $item->quantity,
                ];
            }),
            'totals' => $totals,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Check if order can be modified
     */
    public function canBeModified(): bool
    {
        return in_array($this->status, ['pending', 'cart']);
    }

    /**
     * Get order status display name
     */
    public function getStatusDisplayAttribute(): string
    {
        return match($this->status) {
            'cart' => 'In Cart',
            'pending' => 'Pending',
            'processing' => 'Processing',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            default => 'Unknown'
        };
    }
}