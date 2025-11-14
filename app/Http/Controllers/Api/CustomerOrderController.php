<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Order;
use App\Models\Product;
use App\Models\Rating;
use Illuminate\Support\Facades\Validator;
use App\Models\Store;

class CustomerOrderController extends Controller
{
    /**
     * Get all orders for authenticated customer
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $orders = Order::where('user_id', $user->id)
            ->with(['store', 'orderItems.product']) 
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'orders' => $orders
        ]);
    }

    /**
     * Place a new order
     */
  /**
 * Place a new order
 */
public function store(Request $request)
{
    // ✅ CORRECTED: Fixed validation with all required fields
    $validator = Validator::make($request->all(), [
        'address' => 'required|string',
        'latitude' => 'required|numeric',
        'longitude' => 'required|numeric',
        'items' => 'required|array|min:1',
        'items.*.product_id' => 'nullable|required_without:items.*.custom_name|exists:products,id',
        'items.*.custom_name' => 'nullable|required_without:items.*.product_id|string|max:255',
        'items.*.quantity' => 'required|integer|min:1',
        'items.*.price' => 'required|numeric|min:0',
        'items.*.special_instructions' => 'nullable|string|max:500',
        'total' => 'required|numeric|min:0',
        'subtotal' => 'required|numeric|min:0', // ✅ ADDED: This was missing!
        'delivery_fee' => 'required|numeric|min:0',
        'payment_method' => 'required|string',
        'phone' => 'required|string',
        'store_id' => 'required|exists:stores,id',
        'notes' => 'nullable|string|max:1000', // ✅ ADDED: For order notes
        'distance' => 'nullable|numeric|min:0', // ✅ ADDED: For distance validation
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors()
        ], 422);
    }

    // Debug logging
    \Log::info('Order request received', [
        'user_id' => $request->user()->id,
        'store_id' => $request->store_id,
        'items_count' => count($request->items),
        'total' => $request->total,
        'subtotal' => $request->subtotal
    ]);

    $store = Store::find($request->store_id);
    if (!$store) {
        return response()->json([
            'success' => false,
            'message' => 'Store not found.'
        ], 422);
    }

    // Check store coordinates
    if (!$store->latitude || !$store->longitude) {
        return response()->json([
            'success' => false,
            'message' => 'Store location is not available.'
        ], 422);
    }

    // Calculate distance using Haversine formula
    $distance = $this->calculateDistance(
        $request->latitude,
        $request->longitude,
        $store->latitude,
        $store->longitude
    );

    \Log::info('Distance calculation', [
        'user_lat' => $request->latitude,
        'user_lng' => $request->longitude,
        'store_lat' => $store->latitude,
        'store_lng' => $store->longitude,
        'calculated_distance' => $distance
    ]);

    // Check distance limit
    if ($distance > 17) {
        return response()->json([
            'success' => false,
            'message' => 'Delivery is only available within 17 km. Your distance is ' . number_format($distance, 2) . ' km.',
            'distance' => $distance
        ], 422);
    }

    try {
        // Create the order
        $user = $request->user();

        $order = Order::create([
            'user_id' => $user->id,
            'store_id' => $request->store_id,
            'address' => $request->address,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'subtotal' => $request->subtotal,
            'delivery_fee' => $request->delivery_fee,
            'total' => $request->total,
            'payment_method' => $request->payment_method,
            'phone' => $request->phone,
            'notes' => $request->notes ?? null,
            'status' => 'pending', // ✅ Ensure status is set
        ]);

        // Create order items
        foreach ($request->items as $item) {
            $orderItemData = [
                'product_id' => $item['product_id'] ?? null,
                'custom_name' => $item['custom_name'] ?? null,
                'quantity' => $item['quantity'],
                'price' => $item['price'],
                'special_instructions' => $item['special_instructions'] ?? null,
            ];
            
            $order->orderItems()->create($orderItemData);
            
            // Debug logging for each item
            \Log::info('Order item created', $orderItemData);
        }

        // Load relationships for response
        $order->load(['store', 'orderItems.product']);

        \Log::info('Order created successfully', [
            'order_id' => $order->id,
            'user_id' => $user->id,
            'total' => $order->total
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Order placed successfully',
            'order' => $order,
            'distance' => $distance
        ], 201);

    } catch (\Exception $e) {
        \Log::error('Order creation failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Failed to create order: ' . $e->getMessage()
        ], 500);
    }
}

    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat/2) * sin($dLat/2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));

        return $earthRadius * $c;
    }

    /**
     * Show a specific order
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();

        $order = Order::where('id', $id)
            ->where('user_id', $user->id)
            ->with([
                'orderItems.product.store', 
                'store', 
                'employer:id,name'
            ])
            ->first();

        if (!$order) {
            return response()->json([
                'message' => 'Order not found or not authorized'
            ], 404);
        }

        // ✅ Add these fields explicitly so Flutter can read them
        $order->loadMissing('employer'); // Ensure employer relation is loaded

        // Add flags for UI
        $order->is_rated = Rating::where('order_id', $id)->exists();

        // Return only necessary data for customer tracking
        return response()->json([
            'id' => $order->id,
            'status' => $order->status,
            'address' => $order->delivery_address ?? $order->address,
            'total' => $order->total,
            'subtotal' => $order->subtotal,
            'delivery_fee' => $order->delivery_fee,
            'payment_method' => $order->payment_method,
            'phone' => $order->phone,
            'created_at' => $order->created_at,
            'updated_at' => $order->updated_at,
            'is_rated' => $order->is_rated,
            'store' => [
                'id' => $order->store->id,
                'name' => $order->store->name,
                'address' => $order->store->address,
                'average_rating' => $order->store->average_rating,
                'image' => $order->store->image, // ✅ ADD THIS
                'logo' => $order->store->logo,   // ✅ ADD THIS
                'photo' => $order->store->photo, // ✅ ADD THIS
            ],
            'order_items' => $order->orderItems->map(function ($item) {
                $productData = null;
                if ($item->product) {
                    $productData = [
                        'id' => $item->product->id,
                        'name' => $item->product->name,
                        'image' => $item->product->image, // ✅ ADD THIS
                        'description' => $item->product->description,
                        'price' => $item->product->price,
                    ];
                }
                
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'custom_name' => $item->custom_name,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'special_instructions' => $item->special_instructions, // ✅ INCLUDES SPECIAL INSTRUCTIONS
                    'product' => $productData, // ✅ This now includes image
                ];
            }),
            'employer_name' => $order->employer ? $order->employer->name : null,
            'delivery_current_lat' => $order->delivery_current_lat,
            'delivery_current_lng' => $order->delivery_current_lng,
            'delivery_updated_at' => $order->delivery_updated_at,
            'preparing_at' => $order->preparing_at,
            'on_the_way_at' => $order->on_the_way_at,
            'delivered_at' => $order->delivered_at,
            'rating' => $order->rating, // ✅ Add rating if exists
            'review' => $order->review, // ✅ Add review if exists
        ]);
    }

    /**
     * Rate an order
     */
    public function rateOrder(Request $request, $orderId)
    {
        $validator = Validator::make($request->all(), [
            'rating' => 'required|integer|between:1,5',
            'review' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first()
            ], 422);
        }

        $customer = Auth::user();

        $order = Order::where('id', $orderId)
            ->where('user_id', $customer->id)
            ->where('status', 'delivered')
            ->first();

        if (!$order) {
            return response()->json([
                'message' => 'Order not found or not delivered.'
            ], 404);
        }

        if (!$order->store_id) {
            return response()->json([
                'message' => 'Cannot rate: store information is missing for this order.'
            ], 400);
        }

        if (Rating::where('order_id', $orderId)->exists()) {
            return response()->json([
                'message' => 'You have already rated this order.'
            ], 400);
        }

        Rating::create([
            'order_id' => $orderId,
            'customer_id' => $customer->id,
            'store_id' => $order->store_id,
            'rating' => $request->rating,
            'review' => $request->review,
        ]);

        return response()->json([
            'message' => 'Thank you for your rating!'
        ], 200);
    }
}