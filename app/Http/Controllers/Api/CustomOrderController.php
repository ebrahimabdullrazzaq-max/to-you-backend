<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Store;
use Illuminate\Support\Facades\Log;

class CustomOrderController extends Controller
{
    /**
     * Submit a custom delivery order WITHOUT store
     */
    public function store(Request $request)
    {
        Log::info('📦 Custom DELIVERY order request received', $request->all());

        // ✅ UPDATED VALIDATION - Remove store_id requirement
        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string|max:1000',
            'items.*.price' => 'nullable|numeric|min:0',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.special_instructions' => 'nullable|string|max:500',
            'pickup_address' => 'required|string|max:500',
            'pickup_latitude' => 'nullable|numeric',
            'pickup_longitude' => 'nullable|numeric',
            'delivery_address' => 'required|string|max:500',
            'delivery_latitude' => 'nullable|numeric',
            'delivery_longitude' => 'nullable|numeric',
            'notes' => 'nullable|string|max:1000',
            'total' => 'required|numeric|min:0',
            'delivery_fee' => 'required|numeric|min:0',
            'payment_method' => 'required|string',
            'phone' => 'required|string|max:20',
            'distance' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            Log::error('❌ Custom delivery order validation failed', [
                'errors' => $validator->errors()->toArray(),
                'request_data' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();

            Log::info('🚚 Creating custom DELIVERY order for user: ' . $user->id);

            // ✅ Calculate subtotal properly
            $subtotal = 0;
            foreach ($request->items as $item) {
                $itemPrice = $item['price'] ?? 0;
                $itemQuantity = $item['quantity'] ?? 1;
                $subtotal += $itemPrice * $itemQuantity;
            }

            // ✅ Handle null coordinates
            $deliveryLat = $request->delivery_latitude;
            $deliveryLng = $request->delivery_longitude;
            $pickupLat = $request->pickup_latitude;
            $pickupLng = $request->pickup_longitude;

            // ✅ CREATE ORDER WITHOUT STORE
            $order = Order::create([
                'user_id' => $user->id,
                'store_id' => null, // ✅ No specific store
                'address' => $request->delivery_address,
                'latitude' => $deliveryLat ?: 0,
                'longitude' => $deliveryLng ?: 0,
                'subtotal' => $subtotal,
                'delivery_fee' => $request->delivery_fee,
                'total' => $request->total,
                'payment_method' => $request->payment_method,
                'phone' => $request->phone,
                'notes' => $request->notes ?? null,
                'status' => 'pending',
                // Custom order specific fields
                'pickup_address' => $request->pickup_address,
                'pickup_latitude' => $pickupLat,
                'pickup_longitude' => $pickupLng,
                'order_type' => 'custom_delivery', // ✅ Changed to custom_delivery
            ]);

            Log::info('✅ Delivery order created with ID: ' . $order->id);

            // Create custom order items
            foreach ($request->items as $index => $item) {
                $orderItem = $order->orderItems()->create([
                    'custom_name' => $item['description'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'] ?? 0,
                    'special_instructions' => $item['special_instructions'] ?? null,
                    'type' => 'custom',
                    'product_id' => null,
                ]);
                Log::info("📝 Delivery item {$index} created: " . $item['description']);
            }

            // Load relationships for response
            $order->load(['orderItems']);

            Log::info('🎉 Custom delivery order completed successfully', [
                'order_id' => $order->id,
                'user_id' => $user->id,
                'total' => $order->total
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Delivery order submitted successfully! A driver will be assigned soon.',
                'order' => $order,
                'order_id' => $order->id
            ], 201);

        } catch (\Exception $e) {
            Log::error('💥 Custom delivery order creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit delivery order: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get custom delivery orders for authenticated user
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $orders = Order::where('user_id', $user->id)
            ->where('order_type', 'custom_delivery')
            ->with(['orderItems'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'orders' => $orders,
            'count' => $orders->count()
        ]);
    }

    /**
     * Get specific custom delivery order
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();

        $order = Order::where('id', $id)
            ->where('user_id', $user->id)
            ->where('order_type', 'custom_delivery')
            ->with(['orderItems', 'employer'])
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Delivery order not found or you are not authorized to view it'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'order' => $order
        ]);
    }

    /**
     * Cancel a custom delivery order
     */
    public function cancel(Request $request, $id)
    {
        $user = $request->user();

        $order = Order::where('id', $id)
            ->where('user_id', $user->id)
            ->where('order_type', 'custom_delivery')
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Delivery order not found'
            ], 404);
        }

        // Only allow cancellation if order is still pending or confirmed
        if (!in_array($order->status, ['pending', 'confirmed'])) {
            return response()->json([
                'success' => false,
                'message' => 'Order cannot be cancelled at this stage. Current status: ' . $order->status
            ], 400);
        }

        $order->update([
            'status' => 'canceled',
            'canceled_at' => now()
        ]);

        Log::info('❌ Custom delivery order cancelled', [
            'order_id' => $order->id,
            'user_id' => $user->id
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Delivery order cancelled successfully',
            'order' => $order
        ]);
    }
}