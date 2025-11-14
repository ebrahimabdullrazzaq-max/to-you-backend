<?php

namespace App\Http\Controllers\Api;

use App\Models\OrderItem;
use App\Models\Order;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class OrderItemController extends Controller
{
    // ✅ Get all order items
    public function index()
    {
        return response()->json(OrderItem::with('product', 'order')->get());
    }

    // ✅ Get single order item
    public function show($id)
    {
        $orderItem = OrderItem::with('product', 'order')->findOrFail($id);
        return response()->json($orderItem);
    }

    // ✅ Update an order item (enhanced with special instructions)
    public function update(Request $request, $id)
    {
        $request->validate([
            'quantity' => 'sometimes|required|integer|min:1',
            'special_instructions' => 'nullable|string|max:500',
            'price' => 'sometimes|required|numeric|min:0',
        ]);

        $orderItem = OrderItem::findOrFail($id);
        
        // Update only provided fields
        if ($request->has('quantity')) {
            $orderItem->quantity = $request->quantity;
        }
        
        if ($request->has('special_instructions')) {
            $orderItem->special_instructions = $request->special_instructions;
        }
        
        if ($request->has('price')) {
            $orderItem->price = $request->price;
        }
        
        $orderItem->save();

        return response()->json([
            'message' => 'Order item updated successfully',
            'order_item' => $orderItem->load('product', 'order'),
        ]);
    }

    // ✅ Delete an order item
    public function destroy($id)
    {
        $orderItem = OrderItem::findOrFail($id);
        $orderItem->delete();

        return response()->json([
            'message' => 'Order item deleted successfully'
        ]);
    }

    // ✅ NEW: Get order items by order ID
    public function getByOrder($orderId)
    {
        $orderItems = OrderItem::where('order_id', $orderId)
            ->with('product')
            ->get();

        return response()->json($orderItems);
    }

    // ✅ NEW: Update special instructions only
    public function updateSpecialInstructions(Request $request, $id)
    {
        $request->validate([
            'special_instructions' => 'required|string|max:500',
        ]);

        $orderItem = OrderItem::findOrFail($id);
        $orderItem->special_instructions = $request->special_instructions;
        $orderItem->save();

        return response()->json([
            'message' => 'Special instructions updated successfully',
            'order_item' => $orderItem->load('product'),
        ]);
    }
}