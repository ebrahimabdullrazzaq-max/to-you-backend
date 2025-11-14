<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Models\Store;
use App\Models\StoreCategory;
use App\Models\Product;

class StoreController extends Controller
{
    /**
     * Display all stores (Paginated)
     */
 /**
 * List all stores (paginated) with store categories
 */
public function index()
{
    // Load stores with their category and store_categories
    $stores = Store::with(['category', 'categories'])->paginate(10);

    return response()->json([
        'success' => true,
        'stores' => $stores->map(function ($store) {
            return [
                'id' => $store->id,
                'name' => $store->name,
                'category_id' => $store->category_id,
                'category_name' => $store->category?->name,
                'address' => $store->address,
                'latitude' => $store->latitude,
                'longitude' => $store->longitude,
                'phone' => $store->phone,
                'image' => $store->image ? asset('storage/' . $store->image) : null,
                'is_active' => $store->is_active,
                'is_favorite' => $store->is_favorite,
                'is_new' => $store->is_new,
                'opening_hour' => $store->opening_hour,
                'opening_minute' => $store->opening_minute,
                'closing_hour' => $store->closing_hour,
                'closing_minute' => $store->closing_minute,
                'delivery_time_min' => $store->delivery_time_min,
                'delivery_time_max' => $store->delivery_time_max,

                // ✅ Include store_categories here
                'store_categories' => $store->categories->map(function ($category) {
                    return [
                        'id' => $category->id,
                        'name' => $category->name,
                        'image' => $category->image ? asset('storage/' . $category->image) : null,
                        'is_active' => $category->is_active,
                    ];
                }),
            ];
        }),
    ]);
}


    /**
     * Show single store
     */
    public function show($id)
    {
        try {
            $store = Store::with('category')->findOrFail($id);
            return response()->json([
                'success' => true,
                'store' => $this->formatStore($store)
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Store not found'
            ], 404);
        }
    }

    /**
     * Get stores by category
     */
public function getByCategory($categoryId, Request $request)
{
    try {
        $city = $request->query('city');

        $query = Store::where('category_id', $categoryId)->where('is_active', true);
        if ($city) {
            $query->where('city', $city); // 👈 CHANGE THIS from address to city
        }

        $stores = $query->withAvg('ratings', 'rating')->withCount('ratings')->get();

        $formatted = $stores->map(function ($store) {
            return [
                'id' => $store->id,
                'name' => $store->name,
                'image' => $store->image ? asset('storage/' . $store->image) : null,
                'city' => $store->city, // 👈 ADD THIS
                'address' => $store->address,
                'latitude' => $store->latitude,
                'longitude' => $store->longitude,
                'phone' => $store->phone ?? 'N/A',
                'is_active' => $store->is_active,
                'is_favorite' => (bool)($store->is_favorite ?? false),
                'is_new' => (bool)($store->is_new ?? false),
                'opening_hour' => $store->opening_hour ?? 7,
                'opening_minute' => $store->opening_minute ?? 0,
                'closing_hour' => $store->closing_hour ?? 23,
                'closing_minute' => $store->closing_minute ?? 0,
                'delivery_time_min' => $store->delivery_time_min ?? 30,
                'delivery_time_max' => $store->delivery_time_max ?? 60,
                'average_rating' => $store->ratings_avg_rating ? round($store->ratings_avg_rating, 1) : 0.0,
                'rating_count' => $store->ratings_count ?? 0,
                'category_id' => $store->category_id,
                'is_open' => $store->is_open,
            ];
        });

        return response()->json([
            'success' => true,
            'stores' => $formatted
        ]);
    } catch (Exception $e) {
        Log::error('Failed to fetch stores', ['error' => $e->getMessage()]);
        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch stores',
            'error' => $e->getMessage()
        ], 500);
    }
}

    /**
     * Add new store
     */
// In your StoreController - store method
public function store(Request $request)
{
    $request->validate([
        'name' => 'required|string|max:255',
        'category_id' => 'required|exists:categories,id',
        'city' => 'required|string', // Make sure this exists
        'address' => 'nullable|string',
        'latitude' => 'required|numeric',
        'longitude' => 'required|numeric',
        'phone' => 'required|string|max:20',
        'is_favorite' => 'boolean',
        'is_new' => 'boolean',
        'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
    ]);

    try {
        $imagePath = $request->hasFile('image') 
            ? $request->file('image')->store('stores', 'public') 
            : null;

        $store = Store::create([
            'name' => $request->name,
            'category_id' => $request->category_id,
            'city' => $request->city, // Make sure this is included
            'address' => $request->address,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'phone' => $request->phone,
            'image' => $imagePath,
            'is_active' => true,
            'is_favorite' => $request->boolean('is_favorite', false),
            'is_new' => $request->boolean('is_new', true),
            'opening_hour' => $request->opening_hour ?? 7,
            'opening_minute' => $request->opening_minute ?? 0,
            'closing_hour' => $request->closing_hour ?? 23,
            'closing_minute' => $request->closing_minute ?? 0,
            'delivery_time_min' => $request->delivery_time_min ?? 30,
            'delivery_time_max' => $request->delivery_time_max ?? 60,
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Store created successfully',
            'store' => $this->formatStore($store)
        ], 201);
    } catch (Exception $e) {
        Log::error('Failed to create store', ['error' => $e->getMessage()]);
        return response()->json([
            'success' => false,
            'message' => 'Failed to create store',
            'error' => $e->getMessage()
        ], 500);
    }
}


    /**
     * Update store
     */
public function update(Request $request, $id)
{
    try {
        $store = Store::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'city' => 'required|string', // 👈 ADD THIS
            'address' => 'nullable|string', // 👈 Change to nullable
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'phone' => 'required|string|max:20',
            'is_favorite' => 'boolean',
            'is_new' => 'boolean',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $imagePath = $store->image;
        if ($request->hasFile('image')) {
            if ($imagePath && Storage::disk('public')->exists($imagePath)) {
                Storage::disk('public')->delete($imagePath);
            }
            $imagePath = $request->file('image')->store('stores', 'public');
        }

        $store->update([
            'name' => $request->name,
            'category_id' => $request->category_id,
            'city' => $request->city, // 👈 ADD THIS
            'address' => $request->address,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'phone' => $request->phone,
            'image' => $imagePath,
            'is_favorite' => $request->boolean('is_favorite', $store->is_favorite),
            'is_new' => $request->boolean('is_new', $store->is_new),
            'opening_hour' => $request->opening_hour ?? $store->opening_hour,
            'opening_minute' => $request->opening_minute ?? $store->opening_minute,
            'closing_hour' => $request->closing_hour ?? $store->closing_hour,
            'closing_minute' => $request->closing_minute ?? $store->closing_minute,
            'delivery_time_min' => $request->delivery_time_min ?? $store->delivery_time_min,
            'delivery_time_max' => $request->delivery_time_max ?? $store->delivery_time_max,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Store updated successfully',
            'store' => $this->formatStore($store)
        ]);
    } catch (Exception $e) {
        Log::error('Failed to update store', ['error' => $e->getMessage()]);
        return response()->json([
            'success' => false,
            'message' => 'Failed to update store',
            'error' => $e->getMessage()
        ], 500);
    }
}
    /**
     * Delete store
     */
    public function destroy($id)
    {
        try {
            $store = Store::findOrFail($id);

            if ($store->image && Storage::disk('public')->exists($store->image)) {
                Storage::disk('public')->delete($store->image);
            }

            $store->delete();

            return response()->json([
                'success' => true,
                'message' => 'Store deleted successfully'
            ]);
        } catch (Exception $e) {
            Log::error('Failed to delete store', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete store',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Format store response
     */
private function formatStore(Store $store)
{
    return [
        'id' => $store->id,
        'name' => $store->name,
        'category_id' => $store->category_id,
        'category_name' => $store->category?->name,
        'city' => $store->city, // 👈 ADD THIS
        'store_categories' => $store->categories->map(fn($c) => [
            'id' => $c->id,
            'name' => $c->name,
        ]),
        'address' => $store->address,
        'latitude' => $store->latitude,
        'longitude' => $store->longitude,
        'phone' => $store->phone,
        'image' => $store->image ? asset('storage/' . $store->image) : null,
        'is_active' => $store->is_active,
        'is_favorite' => $store->is_favorite,
        'is_new' => $store->is_new,
        'opening_hour' => $store->opening_hour,
        'opening_minute' => $store->opening_minute,
        'closing_hour' => $store->closing_hour,
        'closing_minute' => $store->closing_minute,
        'delivery_time_min' => $store->delivery_time_min,
        'delivery_time_max' => $store->delivery_time_max,
    ];
}


    /**
     * Add store category
     */
public function addStoreCategory(Request $request)
{
    $request->validate([
        'store_id' => 'required|exists:stores,id',
        'name' => 'required|string|max:255',
        'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // Change to accept image files
        'is_active' => 'boolean',
        'category_id' => 'required|exists:categories,id',
    ]);

    try {
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('store_categories', 'public');
        }

        $category = StoreCategory::create([
            'store_id' => $request->store_id,
            'name' => $request->name,
            'image' => $imagePath, // Store the path, not the file
            'is_active' => $request->boolean('is_active', true),
            'category_id' => $request->category_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Category added successfully',
            'category' => $category
        ], 201);
    } catch (Exception $e) {
        Log::error('Failed to add store category', ['error' => $e->getMessage()]);
        return response()->json([
            'success' => false,
            'message' => 'Failed to add category',
            'error' => $e->getMessage()
        ], 500);
    }
}

    /**
     * Update store category
     */
  public function updateStoreCategory(Request $request, $id)
{
    try {
        $category = StoreCategory::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // Change this
            'is_active' => 'boolean',
        ]);

        $imagePath = $category->image;
        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($imagePath && Storage::disk('public')->exists($imagePath)) {
                Storage::disk('public')->delete($imagePath);
            }
            $imagePath = $request->file('image')->store('store_categories', 'public');
        }

        $category->update([
            'name' => $request->name,
            'image' => $imagePath,
            'is_active' => $request->boolean('is_active', $category->is_active),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully',
            'category' => $category
        ]);
    } catch (Exception $e) {
        Log::error('Failed to update store category', ['error' => $e->getMessage()]);
        return response()->json([
            'success' => false,
            'message' => 'Failed to update category',
            'error' => $e->getMessage()
        ], 500);
    }
}

    /**
     * Delete store category
     */
    public function deleteStoreCategory($id)
    {
        try {
            $category = StoreCategory::findOrFail($id);

            // Delete all products
            $products = Product::where('store_category_id', $category->id)->get();
            foreach ($products as $product) {
                if ($product->image && Storage::disk('public')->exists($product->image)) {
                    Storage::disk('public')->delete($product->image);
                }
                $product->delete();
            }

            // Delete category image
            if ($category->image && Storage::disk('public')->exists($category->image)) {
                Storage::disk('public')->delete($category->image);
            }

            $category->delete();

            return response()->json([
                'success' => true,
                'message' => 'Category and products deleted'
            ]);
        } catch (Exception $e) {
            Log::error('Failed to delete category', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Delete failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
public function getCategories($storeId)
{
    $store = Store::with('categories')->find($storeId);

    if (!$store) {
        return response()->json([
            'success' => false,
            'message' => 'Store not found'
        ], 404);
    }

    return response()->json([
        'success'    => true,
        'categories' => $store->categories
    ], 200);
}


public function getCities()
{
    try {
        $cities = Store::whereNotNull('city')
            ->where('city', '!=', '')
            ->distinct()
            ->pluck('city')
            ->sort()
            ->values();

        return response()->json([
            'success' => true,
            'cities' => $cities
        ]);
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch cities',
            'error' => $e->getMessage()
        ], 500);
    }
}

}