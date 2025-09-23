<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Services\CheckoutService;
use App\Services\PaymentService;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class CheckoutController extends Controller
{
    protected CheckoutService $checkoutService;
    protected PaymentService $paymentService;

    public function __construct(CheckoutService $checkoutService, PaymentService $paymentService)
    {
        $this->checkoutService = $checkoutService;
        $this->paymentService = $paymentService;
    }

    /**
     * Validate cart for checkout
     */
    public function validateCart(Request $request, int $pharmacyId)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        // Find cart for this pharmacy
        $cart = Order::where('user_id', $user->id)
            ->where('pharmacy_id', $pharmacyId)
            ->where('status', 'cart')
            ->first();

        if (!$cart) {
            return response()->json([
                'success' => false,
                'message' => 'No cart found for this pharmacy'
            ], 404);
        }

        $validation = $this->checkoutService->validateCartForCheckout($cart->id, $user->id);
        
        return response()->json($validation);
    }

    /**
     * Get checkout summary
     */
    public function getSummary(Request $request, int $pharmacyId)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        // Find cart for this pharmacy
        $cart = Order::where('user_id', $user->id)
            ->where('pharmacy_id', $pharmacyId)
            ->where('status', 'cart')
            ->first();

        if (!$cart) {
            return response()->json([
                'success' => false,
                'message' => 'No cart found for this pharmacy'
            ], 404);
        }

        $summary = $this->checkoutService->getCheckoutSummary($cart->id, $user->id);
        
        return response()->json($summary);
    }

    /**
     * Initiate checkout process
     */
    public function initiate(CheckoutRequest $request, int $pharmacyId)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        // Find cart for this pharmacy
        $cart = Order::where('user_id', $user->id)
            ->where('pharmacy_id', $pharmacyId)
            ->where('status', 'cart')
            ->first();

        if (!$cart) {
            return response()->json([
                'success' => false,
                'message' => 'No cart found for this pharmacy'
            ], 404);
        }

        $validated = $request->validated();
        
        // Process checkout
        $result = $this->checkoutService->processCheckout($cart->id, $user->id, $validated);
        
        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Checkout initiated successfully',
            'order_id' => $result['order_id'],
            'data' => $result['order']
        ]);
    }

    /**
     * Process PayPal payment
     */
    public function processPayPal(Request $request, int $pharmacyId)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        $request->validate([
            'order_id' => 'required|string',           
            'paypal_order_id' => 'required|string',    
            'payer_id' => 'required|string',
            'payment_id' => 'required|string'
        ]);

        try {
            // Verify payment with PayPal
            $verification = $this->verifyPayPalPayment($request->paypal_order_id, $request->payer_id);
            
            if (!$verification['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment verification failed',
                    'errors' => ['payment_verification_failed']
                ], 400);
            }

            // Find the order
            $order = Order::where('id', $request->order_id)
                ->where('user_id', $user->id)
                ->where('pharmacy_id', $pharmacyId)
                ->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found'
                ], 404);
            }

            // Create payment record
            $payment = $this->paymentService->processPayment($order, [
                'method' => 'paypal',
                'payment_id' => $request->payment_id,
                'payer_id' => $request->payer_id,
                'currency' => 'EGP'
            ]);

            if (!$payment['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment processing failed',
                    'errors' => $payment['errors'] ?? ['payment_failed']
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment processed successfully',
                'payment_id' => $payment['payment_id'],
                'transaction_id' => $payment['transaction_id']
            ]);

        } catch (\Exception $e) {
            Log::error('PayPal payment processing error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Payment processing failed due to system error',
                'errors' => ['system_error']
            ], 500);
        }
    }

    /**
     * Handle PayPal webhook
     */
    public function paypalWebhook(Request $request)
    {
        try {
            $webhookData = $request->all();
            
            // Verify webhook signature
            $isValid = $this->verifyPayPalWebhook($request);
            if (!$isValid) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid webhook signature'
                ], 400);
            }

            // Process webhook
            $result = $this->paymentService->handlePayPalWebhook($webhookData);
            
            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('PayPal webhook error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Webhook processing failed'
            ], 500);
        }
    }

    /**
     * Get payment status
     */
    public function getPaymentStatus(Request $request, int $orderId)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        $order = Order::where('id', $orderId)
            ->where('user_id', $user->id)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        $payment = $order->payments()->latest()->first();
        
        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'No payment found for this order'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'payment' => [
                'id' => $payment->id,
                'status' => $payment->status,
                'method' => $payment->method,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'transaction_id' => $payment->transaction_id,
                'created_at' => $payment->created_at,
                'updated_at' => $payment->updated_at
            ]
        ]);
    }

    /**
     * Verify PayPal payment
     */
    protected function verifyPayPalPayment(string $paypalOrderId, string $payerId): array
    {
        try {
            $paypalConfig = config('payment.methods.paypal');
            
            if (!$paypalConfig['enabled']) {
                return [
                    'success' => false,
                    'message' => 'PayPal is not enabled'
                ];
            }

            // Get access token
            $accessToken = $this->getPayPalAccessToken();
            
            if (!$accessToken) {
                return [
                    'success' => false,
                    'message' => 'Failed to get PayPal access token'
                ];
            }
            
            // Verify order with PayPal
            $baseUrl = $paypalConfig['sandbox'] ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->get("{$baseUrl}/v2/checkout/orders/{$paypalOrderId}");
            
            if ($response->successful()) {
                $orderData = $response->json();
                
                if ($orderData['status'] === 'COMPLETED') {
                    return [
                        'success' => true,
                        'message' => 'PayPal payment verified',
                        'order_data' => $orderData
                    ];
                }
                
                return [
                    'success' => false,
                    'message' => 'PayPal payment not completed. Status: ' . $orderData['status']
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Failed to verify PayPal payment'
            ];

        } catch (\Exception $e) {
            Log::error('PayPal verification error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Payment verification failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get PayPal access token
     */
    protected function getPayPalAccessToken(): ?string
    {
        try {
            $paypalConfig = config('payment.methods.paypal');
            $baseUrl = $paypalConfig['sandbox'] ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
            
            $response = Http::withBasicAuth(
                $paypalConfig['client_id'],
                $paypalConfig['client_secret']
            )->asForm()->post("{$baseUrl}/v1/oauth2/token", [
                'grant_type' => 'client_credentials'
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                return $data['access_token'] ?? null;
            }
            
            Log::error('PayPal access token error: ' . $response->body());
            return null;
            
        } catch (\Exception $e) {
            Log::error('PayPal access token exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Verify PayPal webhook signature
     */
    protected function verifyPayPalWebhook(Request $request): bool
    {
        try {
            // In real implementation, you would verify the webhook signature
            // using PayPal's webhook verification endpoint
            $webhookId = config('payment.methods.paypal.webhook_id');
            
            if (!$webhookId) {
                return false;
            }

            // Placeholder verification - in real implementation, you would:
            // 1. Get the webhook signature from headers
            // 2. Verify with PayPal's webhook verification endpoint
            // 3. Check the webhook ID matches
            
            return true; // Placeholder

        } catch (\Exception $e) {
            Log::error('PayPal webhook verification error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get PayPal configuration for frontend
     */
    public function getPayPalConfig()
    {
        $paypalConfig = config('payment.methods.paypal');
        
        if (!$paypalConfig['enabled']) {
            return response()->json([
                'success' => false,
                'message' => 'PayPal is not enabled'
            ], 400);
        }

        return response()->json([
            'success' => true,
            'config' => [
                'client_id' => $paypalConfig['client_id'],
                'currency' => config('payment.default_currency', 'EGP'),
                'sandbox' => $paypalConfig['sandbox'],
                'environment' => $paypalConfig['sandbox'] ? 'sandbox' : 'production'
            ]
        ]);
    }
}
