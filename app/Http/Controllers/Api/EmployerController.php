<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Order;
use App\Models\User;

class EmployerController extends Controller
{
    /**
     * Display ALL orders for the authenticated employer (including admin-assigned)
     */
    public function myOrders()
    {
        $user = Auth::user();

        if ($user->role !== 'employer') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // Get ALL orders assigned to this employer (both self-accepted and admin-assigned)
        $orders = Order::where('employer_id', $user->id)
            ->with(['store', 'user', 'orderItems.product'])
            ->latest()
            ->get();

        return response()->json([
            'status' => true,
            'orders' => $orders
        ]);
    }

    /**
     * Get active deliveries for the employer (including admin-assigned)
     */
    public function activeDelivery()
    {
        $user = Auth::user();
        
        if ($user->role !== 'employer') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        
        // Get ALL active orders assigned to this employer
        $activeOrders = Order::where('employer_id', $user->id)
            ->whereIn('status', ['pending', 'confirmed', 'preparing', 'on_the_way'])
            ->with(['store', 'user', 'orderItems.product'])
            ->latest()
            ->get();
            
        return response()->json([
            'status' => true,
            'orders' => $activeOrders
        ]);
    }

    /**
     * Get available orders AND admin-assigned pending orders
     */
    public function availableOrders()
    {
        $user = Auth::user();
        
        if ($user->role !== 'employer') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        
        // Get:
        // 1. Orders with status 'pending' and no employer (available to anyone)
        // 2. Orders with status 'pending' but assigned to THIS employer (admin-assigned)
        $availableOrders = Order::where(function($query) use ($user) {
                $query->where('status', 'pending')
                      ->whereNull('employer_id'); // Available to all
            })
            ->orWhere(function($query) use ($user) {
                $query->where('status', 'pending')
                      ->where('employer_id', $user->id); // Admin-assigned to this employer
            })
            ->with(['store', 'user', 'orderItems.product'])
            ->latest()
            ->get();
            
        return response()->json([
            'status' => true,
            'orders' => $availableOrders
        ]);
    }

    /**
     * Accept an order (for both available and admin-assigned orders)
     */
    public function acceptOrder(Request $request, $orderId)
    {
        $user = Auth::user();
        
        if ($user->role !== 'employer') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        
        // Find order that is either:
        // 1. Available (pending + no employer) OR 
        // 2. Admin-assigned to this employer (pending + this employer_id)
        $order = Order::where('status', 'pending')
            ->where(function($query) use ($user) {
                $query->whereNull('employer_id')
                      ->orWhere('employer_id', $user->id);
            })
            ->find($orderId);
        
        if (!$order) {
            return response()->json(['message' => 'Order not found or not available for acceptance.'], 404);
        }
        
        // If order was available (no employer), assign it to this employer
        // If order was already admin-assigned to this employer, just update status
        $order->employer_id = $user->id;
        $order->status = 'confirmed';
        $order->assigned_at = now();
        $order->confirmed_at = now();
        $order->save();
        
        return response()->json([
            'status' => true,
            'message' => 'Order accepted successfully',
            'order' => $order->load(['store', 'user', 'orderItems.product'])
        ]);
    }

    /**
     * Get specifically admin-assigned pending orders
     */
    public function adminAssignedOrders()
    {
        $user = Auth::user();
        
        if ($user->role !== 'employer') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        
        // Get orders that are pending AND specifically assigned to this employer
        $assignedOrders = Order::where('status', 'pending')
            ->where('employer_id', $user->id) // Specifically admin-assigned
            ->with(['store', 'user', 'orderItems.product'])
            ->latest()
            ->get();
            
        return response()->json([
            'status' => true,
            'orders' => $assignedOrders,
            'message' => 'Admin-assigned pending orders'
        ]);
    }

    /**
     * Update order status
     */
    public function updateOrderStatus(Request $request, $orderId)
    {
        $user = Auth::user();
        
        if ($user->role !== 'employer') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        
        $request->validate([
            'status' => 'required|in:confirmed,preparing,on_the_way,delivered,cancelled'
        ]);
        
        $order = Order::where('id', $orderId)
            ->where('employer_id', $user->id)
            ->first();
            
        if (!$order) {
            return response()->json(['message' => 'Order not found or not assigned to you.'], 404);
        }

        // Status validation
        $validTransitions = [
            'pending' => ['confirmed', 'cancelled'],
            'confirmed' => ['preparing', 'cancelled'],
            'preparing' => ['on_the_way', 'cancelled'],
            'on_the_way' => ['delivered', 'cancelled'],
            'delivered' => [],
            'cancelled' => []
        ];

        if (!in_array($request->status, $validTransitions[$order->status] ?? [])) {
            return response()->json([
                'message' => "Cannot change status from {$order->status} to {$request->status}"
            ], 400);
        }
        
        $order->status = $request->status;
        
        // Set timestamps based on status
        switch ($request->status) {
            case 'preparing':
                $order->preparing_at = now();
                break;
            case 'on_the_way':
                $order->on_the_way_at = now();
                break;
            case 'delivered':
                $order->delivered_at = now();
                break;
            case 'cancelled':
                $order->canceled_at = now();
                break;
        }
        
        $order->save();
        
        return response()->json([
            'status' => true,
            'message' => 'Order status updated successfully',
            'order' => $order->load(['store', 'user', 'orderItems.product'])
        ]);
    }
    
    /**
     * Mark order as delivered
     */
    public function markAsDelivered(Request $request, $orderId)
    {
        $user = Auth::user();
        
        if ($user->role !== 'employer') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        
        $order = Order::where('id', $orderId)
            ->where('employer_id', $user->id)
            ->where('status', 'on_the_way')
            ->first();
            
        if (!$order) {
            return response()->json(['message' => 'Order not found, not assigned to you, or not ready for delivery.'], 404);
        }
            
        $order->status = 'delivered';
        $order->delivered_at = now();
        $order->save();
        
        return response()->json([
            'status' => true,
            'message' => 'Order marked as delivered successfully',
            'order' => $order->load(['store', 'user', 'orderItems.product'])
        ]);
    }
    
    /**
     * Update delivery location
     */
    public function updateLocation(Request $request)
    {
        $user = Auth::user();
        
        if ($user->role !== 'employer') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        
        $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'order_id' => 'required|exists:orders,id'
        ]);
        
        $order = Order::where('id', $request->order_id)
            ->where('employer_id', $user->id)
            ->whereIn('status', ['preparing', 'on_the_way', 'confirmed'])
            ->first();
            
        if (!$order) {
            return response()->json(['message' => 'Order not found, not assigned to you, or not active.'], 404);
        }
            
        // Update delivery location
        $order->updateDeliveryLocation($request->latitude, $request->longitude);
        
        return response()->json([
            'status' => true,
            'message' => 'Location updated successfully',
            'order' => $order->fresh()
        ]);
    }
    
    /**
     * Get delivery history
     */
    public function deliveryHistory()
    {
        $user = Auth::user();
        
        if ($user->role !== 'employer') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        
        $history = Order::where('employer_id', $user->id)
            ->whereIn('status', ['delivered', 'cancelled'])
            ->with(['store', 'user', 'orderItems.product'])
            ->latest()
            ->get();
            
        return response()->json([
            'status' => true,
            'orders' => $history
        ]);
    }

    /**
     * Dashboard stats
     */
    public function dashboard()
    {
        $user = Auth::user();
        
        if ($user->role !== 'employer') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        
        $totalOrders = Order::where('employer_id', $user->id)->count();
        $activeOrders = Order::where('employer_id', $user->id)
            ->whereIn('status', ['preparing', 'on_the_way', 'confirmed', 'pending'])
            ->count();
        $completedOrders = Order::where('employer_id', $user->id)
            ->where('status', 'delivered')
            ->count();
        $cancelledOrders = Order::where('employer_id', $user->id)
            ->where('status', 'cancelled')
            ->count();
            
        return response()->json([
            'status' => true,
            'stats' => [
                'total_orders' => $totalOrders,
                'active_orders' => $activeOrders,
                'completed_orders' => $completedOrders,
                'cancelled_orders' => $cancelledOrders,
            ]
        ]);
    }

    /**
     * Performance stats
     */
    public function performanceStats()
    {
        $user = Auth::user();
        
        if ($user->role !== 'employer') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        
        // Calculate actual performance metrics
        $totalDeliveries = Order::where('employer_id', $user->id)
            ->where('status', 'delivered')
            ->count();
            
        $totalAssignedOrders = Order::where('employer_id', $user->id)->count();
        $completionRate = $totalAssignedOrders > 0 ? ($totalDeliveries / $totalAssignedOrders) * 100 : 0;

        // Calculate actual average delivery time
        $deliveredOrders = Order::where('employer_id', $user->id)
            ->where('status', 'delivered')
            ->whereNotNull('assigned_at')
            ->whereNotNull('delivered_at')
            ->get();

        $totalDeliveryTime = 0;
        $count = 0;

        foreach ($deliveredOrders as $order) {
            $deliveryTime = $order->delivered_at->diffInMinutes($order->assigned_at);
            $totalDeliveryTime += $deliveryTime;
            $count++;
        }

        $avgDeliveryTime = $count > 0 ? round($totalDeliveryTime / $count) : 0;
        $avgDeliveryTimeFormatted = $avgDeliveryTime > 0 ? "{$avgDeliveryTime} min" : 'N/A';
        
        return response()->json([
            'status' => true,
            'performance' => [
                'total_deliveries' => $totalDeliveries,
                'completion_rate' => round($completionRate, 2) . '%',
                'average_delivery_time' => $avgDeliveryTimeFormatted,
                'total_assigned_orders' => $totalAssignedOrders,
            ]
        ]);
    }

    /**
     * Get order details by ID
     */
    public function getOrder($orderId)
    {
        $user = Auth::user();
        
        if ($user->role !== 'employer') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        
        $order = Order::where('id', $orderId)
            ->where('employer_id', $user->id)
            ->with(['store', 'user', 'orderItems.product'])
            ->first();
            
        if (!$order) {
            return response()->json(['message' => 'Order not found or not assigned to you.'], 404);
        }
        
        return response()->json([
            'status' => true,
            'order' => $order
        ]);
    }

    /**
     * Cancel an order
     */
    public function cancelOrder(Request $request, $orderId)
    {
        $user = Auth::user();
        
        if ($user->role !== 'employer') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        
        $order = Order::where('id', $orderId)
            ->where('employer_id', $user->id)
            ->whereIn('status', ['pending', 'confirmed', 'preparing'])
            ->first();
            
        if (!$order) {
            return response()->json(['message' => 'Order not found, not assigned to you, or cannot be cancelled.'], 404);
        }
        
        $order->status = 'cancelled';
        $order->canceled_at = now();
        $order->save();
        
        return response()->json([
            'status' => true,
            'message' => 'Order cancelled successfully',
            'order' => $order->load(['store', 'user', 'orderItems.product'])
        ]);
    }

    /**
     * Get today's orders
     */
    public function todaysOrders()
    {
        $user = Auth::user();
        
        if ($user->role !== 'employer') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        
        $todayOrders = Order::where('employer_id', $user->id)
            ->whereDate('created_at', today())
            ->with(['store', 'user', 'orderItems.product'])
            ->latest()
            ->get();
            
        return response()->json([
            'status' => true,
            'orders' => $todayOrders,
            'count' => $todayOrders->count()
        ]);
    }

    /**
     * Get weekly performance
     */
    public function weeklyPerformance()
    {
        $user = Auth::user();
        
        if ($user->role !== 'employer') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        
        $startOfWeek = now()->startOfWeek();
        $endOfWeek = now()->endOfWeek();
        
        $weeklyStats = [
            'total_orders' => Order::where('employer_id', $user->id)
                ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
                ->count(),
            'delivered_orders' => Order::where('employer_id', $user->id)
                ->where('status', 'delivered')
                ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
                ->count(),
            'cancelled_orders' => Order::where('employer_id', $user->id)
                ->where('status', 'cancelled')
                ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
                ->count(),
        ];
        
        return response()->json([
            'status' => true,
            'weekly_stats' => $weeklyStats,
            'week_start' => $startOfWeek->format('Y-m-d'),
            'week_end' => $endOfWeek->format('Y-m-d')
        ]);
    }
}