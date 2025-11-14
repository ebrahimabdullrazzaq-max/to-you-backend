<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Store;
use Illuminate\Support\Facades\Log;

class WaterTankOrderController extends Controller
{
    /**
     * Submit a water tank delivery order
     */
    public function store(Request $request)
    {
        Log::info('💧 WATER TANK order request received', $request->all());

        // ✅ VALIDATION SPECIFIC FOR WATER TANK ORDERS
        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.custom_name' => 'required|string|max:255',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.special_instructions' => 'nullable|string|max:500',
            'delivery_address' => 'required|string|max:500',
            'delivery_latitude' => 'required|numeric',
            'delivery_longitude' => 'required|numeric',
            'water_station_address' => 'required|string|max:500',
            'water_station_latitude' => 'nullable|numeric',
            'water_station_longitude' => 'nullable|numeric',
            'notes' => 'nullable|string|max:1000',
            'total' => 'required|numeric|min:0',
            'subtotal' => 'required|numeric|min:0',
            'delivery_fee' => 'required|numeric|min:0',
            'payment_method' => 'required|string|in:cash_on_delivery,online',
            'phone' => 'required|string|max:20',
            'distance' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            Log::error('❌ Water tank order validation failed', [
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

            Log::info('🚚 Creating WATER TANK order for user: ' . $user->id);

            // ✅ Calculate subtotal from items to verify
            $calculatedSubtotal = 0;
            foreach ($request->items as $item) {
                $itemPrice = $item['price'] ?? 0;
                $itemQuantity = $item['quantity'] ?? 1;
                $calculatedSubtotal += $itemPrice * $itemQuantity;
            }

            // Log calculation for debugging
            Log::info('💰 Price calculation', [
                'calculated_subtotal' => $calculatedSubtotal,
                'received_subtotal' => $request->subtotal,
                'delivery_fee' => $request->delivery_fee,
                'total' => $request->total
            ]);

            // ✅ CREATE WATER TANK ORDER
            $order = Order::create([
                'user_id' => $user->id,
                'store_id' => null, // ✅ No specific store for water tank orders
                'address' => $request->delivery_address,
                'latitude' => $request->delivery_latitude,
                'longitude' => $request->delivery_longitude,
                'subtotal' => $request->subtotal,
                'delivery_fee' => $request->delivery_fee,
                'total' => $request->total,
                'payment_method' => $request->payment_method,
                'phone' => $request->phone,
                'notes' => $request->notes ?? null,
                'status' => 'pending',
                // Water tank specific fields
                'pickup_address' => $request->water_station_address,
                'pickup_latitude' => $request->water_station_latitude,
                'pickup_longitude' => $request->water_station_longitude,
                'order_type' => 'water_tank', // ✅ Specific type for water tank orders
                'distance' => $request->distance,
            ]);

            Log::info('✅ Water tank order created with ID: ' . $order->id);

            // Create water tank order items
            foreach ($request->items as $index => $item) {
                $orderItem = $order->orderItems()->create([
                    'custom_name' => $item['custom_name'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'special_instructions' => $item['special_instructions'] ?? null,
                    'type' => 'water_tank',
                    'product_id' => null,
                ]);
                Log::info("💧 Water tank item {$index} created", [
                    'name' => $item['custom_name'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price']
                ]);
            }

            // Load relationships for response
            $order->load(['orderItems']);

            Log::info('🎉 Water tank order completed successfully', [
                'order_id' => $order->id,
                'user_id' => $user->id,
                'total' => $order->total,
                'items_count' => count($request->items)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Water tank order submitted successfully! A water delivery driver will be assigned soon.',
                'order' => $order,
                'order_id' => $order->id
            ], 201);

        } catch (\Exception $e) {
            Log::error('💥 Water tank order creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit water tank order: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get water tank orders for authenticated user
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $orders = Order::where('user_id', $user->id)
            ->where('order_type', 'water_tank')
            ->with(['orderItems', 'employer'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'orders' => $orders,
            'count' => $orders->count()
        ]);
    }

    /**
     * Get specific water tank order
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();

        $order = Order::where('id', $id)
            ->where('user_id', $user->id)
            ->where('order_type', 'water_tank')
            ->with(['orderItems', 'employer'])
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Water tank order not found or you are not authorized to view it'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'order' => $order
        ]);
    }

    /**
     * Cancel a water tank order
     */
    public function cancel(Request $request, $id)
    {
        $user = $request->user();

        $order = Order::where('id', $id)
            ->where('user_id', $user->id)
            ->where('order_type', 'water_tank')
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Water tank order not found'
            ], 404);
        }

        // Only allow cancellation if order is still pending or confirmed
        if (!in_array($order->status, ['pending', 'confirmed'])) {
            return response()->json([
                'success' => false,
                'message' => 'Water tank order cannot be cancelled at this stage. Current status: ' . $order->status
            ], 400);
        }

        $order->update([
            'status' => 'canceled',
            'canceled_at' => now()
        ]);

        Log::info('❌ Water tank order cancelled', [
            'order_id' => $order->id,
            'user_id' => $user->id
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Water tank order cancelled successfully',
            'order' => $order
        ]);
    }

    /**
     * Get water tank order statistics for user
     */
    public function stats(Request $request)
    {
        $user = $request->user();

        $stats = [
            'total_orders' => Order::where('user_id', $user->id)
                ->where('order_type', 'water_tank')
                ->count(),
            'pending_orders' => Order::where('user_id', $user->id)
                ->where('order_type', 'water_tank')
                ->where('status', 'pending')
                ->count(),
            'delivered_orders' => Order::where('user_id', $user->id)
                ->where('order_type', 'water_tank')
                ->where('status', 'delivered')
                ->count(),
            'total_spent' => Order::where('user_id', $user->id)
                ->where('order_type', 'water_tank')
                ->where('status', 'delivered')
                ->sum('total'),
        ];

        return response()->json([
            'success' => true,
            'stats' => $stats
        ]);
    }
}