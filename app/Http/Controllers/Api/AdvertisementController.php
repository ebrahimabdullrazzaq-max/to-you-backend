<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Advertisement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class AdvertisementController extends Controller
{
    /**
     * Get all advertisements (for admin)
     */
    public function index(Request $request)
    {
        try {
            Log::info('📱 FETCHING ADVERTISEMENTS FOR ADMIN');
            
            $query = Advertisement::with('admin')->latest();

            if ($request->has('status')) {
                if ($request->status === 'active') {
                    $query->where('is_active', true);
                } elseif ($request->status === 'inactive') {
                    $query->where('is_active', false);
                }
            }

            if ($request->has('search')) {
                $query->where('title', 'like', '%' . $request->search . '%');
            }

            $advertisements = $query->paginate($request->per_page ?? 20);

            Log::info('✅ ADVERTISEMENTS FETCHED SUCCESSFULLY', ['count' => $advertisements->count()]);

            return response()->json([
                'success' => true,
                'data' => $advertisements,
                'message' => 'Advertisements retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('❌ FAILED TO FETCH ADVERTISEMENTS', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load advertisements: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new advertisement
     */
    public function store(Request $request)
    {
        Log::info('🆕 CREATING NEW ADVERTISEMENT');
        
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:500',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120',
            'target_url' => 'nullable|url|max:500',
            'is_active' => 'boolean',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'priority' => 'nullable|integer|min:0|max:10',
        ]);

        if ($validator->fails()) {
            Log::error('❌ VALIDATION FAILED', ['errors' => $validator->errors()]);
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Handle image upload
            $imagePath = null;
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('advertisements', 'public');
                Log::info('📸 IMAGE UPLOADED', ['path' => $imagePath]);
            }

            // Process target cities
            $targetCities = $this->processTargetCities($request);
            Log::info('🏙️ TARGET CITIES PROCESSED', ['cities' => $targetCities]);

            $advertisement = Advertisement::create([
                'title' => $request->title,
                'subtitle' => $request->subtitle,
                'image' => $imagePath,
                'target_url' => $request->target_url,
                'is_active' => $request->boolean('is_active', true),
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'priority' => $request->priority ?? 0,
                'target_cities' => $targetCities,
                'created_by' => $request->user()->id,
            ]);

            Log::info('✅ ADVERTISEMENT CREATED', [
                'id' => $advertisement->id,
                'title' => $advertisement->title
            ]);

            return response()->json([
                'success' => true,
                'data' => $advertisement->load('admin'),
                'message' => 'Advertisement created successfully'
            ], 201);

        } catch (\Exception $e) {
            Log::error('❌ FAILED TO CREATE ADVERTISEMENT', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create advertisement: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update advertisement - COMPLETELY REWRITTEN
     */
   /**
 * Update advertisement - FIXED FOR EXISTING ADS
 */
/**
 * Update advertisement - FINAL WORKING VERSION
 */
public function update(Request $request, $id)
{
    Log::info('✏️ UPDATING ADVERTISEMENT - FINAL FIX', ['id' => $id]);
    Log::info('📦 ALL REQUEST DATA:', $request->all());
    
    $advertisement = Advertisement::find($id);

    if (!$advertisement) {
        return response()->json([
            'success' => false,
            'message' => 'Advertisement not found'
        ], 404);
    }

    $validator = Validator::make($request->all(), [
        'title' => 'sometimes|required|string|max:255',
        'subtitle' => 'nullable|string|max:500',
        'image' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:5120',
        'target_url' => 'nullable|url|max:500',
        'is_active' => 'boolean',
        'start_date' => 'nullable|date',
        'end_date' => 'nullable|date|after:start_date',
        'priority' => 'nullable|integer|min:0|max:10',
    ]);

    if ($validator->fails()) {
        Log::error('❌ VALIDATION FAILED', ['errors' => $validator->errors()]);
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors()
        ], 422);
    }

    try {
        // ✅ CRITICAL FIX: Process target cities FIRST
        $targetCities = $this->processTargetCities($request);
        
        Log::info('🏙️ TARGET CITIES FOR UPDATE:', [
            'input' => $request->input('target_cities'),
            'processed' => $targetCities,
            'type' => gettype($targetCities),
            'is_array' => is_array($targetCities),
            'count' => is_array($targetCities) ? count($targetCities) : 0
        ]);

        // ✅ CRITICAL: Use fill() and save() instead of update() to ensure model casting works
        $advertisement->title = $request->title ?? $advertisement->title;
        $advertisement->subtitle = $request->subtitle ?? $advertisement->subtitle;
        $advertisement->target_url = $request->target_url ?? $advertisement->target_url;
        $advertisement->is_active = $request->has('is_active') ? $request->boolean('is_active') : $advertisement->is_active;
        $advertisement->start_date = $request->start_date ?? $advertisement->start_date;
        $advertisement->end_date = $request->end_date ?? $advertisement->end_date;
        $advertisement->priority = $request->priority ?? $advertisement->priority;
        
        // ✅ CRITICAL: Explicitly set target_cities
        $advertisement->target_cities = $targetCities;

        Log::info('🔄 DATA TO UPDATE:', [
            'title' => $advertisement->title,
            'target_cities' => $advertisement->target_cities,
            'is_active' => $advertisement->is_active,
            'priority' => $advertisement->priority
        ]);

        // Handle image upload
        if ($request->hasFile('image')) {
            Log::info('📸 UPLOADING NEW IMAGE');
            
            // Delete old image if exists
            if ($advertisement->image && Storage::disk('public')->exists($advertisement->image)) {
                Storage::disk('public')->delete($advertisement->image);
                Log::info('🗑️ OLD IMAGE DELETED', ['path' => $advertisement->image]);
            }
            
            // Upload new image
            $imagePath = $request->file('image')->store('advertisements', 'public');
            $advertisement->image = $imagePath;
            Log::info('✅ NEW IMAGE UPLOADED', ['path' => $imagePath]);
        }

        // ✅ CRITICAL: Save the model to trigger model events and casting
        $advertisement->save();

        // Reload fresh data from database
        $advertisement->refresh();

        Log::info('✅ ADVERTISEMENT UPDATED SUCCESSFULLY', [
            'id' => $advertisement->id,
            'title' => $advertisement->title,
            'target_cities' => $advertisement->target_cities,
            'target_cities_type' => gettype($advertisement->target_cities),
            'is_active' => $advertisement->is_active,
            'priority' => $advertisement->priority
        ]);

        return response()->json([
            'success' => true,
            'data' => $advertisement->load('admin'),
            'message' => 'Advertisement updated successfully'
        ]);

    } catch (\Exception $e) {
        Log::error('❌ FAILED TO UPDATE ADVERTISEMENT', [
            'id' => $id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return response()->json([
            'success' => false,
            'message' => 'Failed to update advertisement: ' . $e->getMessage()
        ], 500);
    }
}
    /**
     * Process target cities from request - COMPLETELY REWRITTEN
     */
 /**
 * Process target cities from request - DEBUG VERSION
 */
/**
 * Process target cities from request - ENHANCED JSON PROCESSING
 */
/**
 * Process target cities from request - ULTIMATE DEBUG VERSION
 */
/**
 * Process target cities - SIMPLE FORCE FIX
 */
/**
 * Process target cities from request - SIMPLIFIED AND ROBUST
 */
/**
 * Process target cities from request - ENHANCED VERSION
 */
private function processTargetCities(Request $request)
{
    Log::info('🔍 PROCESSING TARGET CITIES');
    
    $targetCitiesValue = $request->input('target_cities');
    
    Log::info('🔍 RAW INPUT ANALYSIS:', [
        'value' => $targetCitiesValue,
        'type' => gettype($targetCitiesValue),
        'is_string' => is_string($targetCitiesValue),
        'is_array' => is_array($targetCitiesValue),
        'exists' => $request->has('target_cities')
    ]);

    // ✅ CASE 1: Empty array '[]' means show in ALL cities (return null)
    if (is_string($targetCitiesValue) && trim($targetCitiesValue) === '[]') {
        Log::info('🏙️ CASE 1: EMPTY ARRAY - RETURN NULL (ALL CITIES)');
        return null;
    }

    // ✅ CASE 2: JSON array with cities
    if (is_string($targetCitiesValue) && !empty(trim($targetCitiesValue))) {
        $decoded = json_decode($targetCitiesValue, true);
        $jsonError = json_last_error();
        
        Log::info('🔍 JSON DECODE RESULT:', [
            'decoded' => $decoded,
            'json_error' => $jsonError,
            'json_error_msg' => $jsonError !== JSON_ERROR_NONE ? json_last_error_msg() : 'No error',
            'is_array' => is_array($decoded),
            'count' => is_array($decoded) ? count($decoded) : 0
        ]);

        if ($jsonError === JSON_ERROR_NONE && is_array($decoded)) {
            if (!empty($decoded)) {
                Log::info('✅ CASE 2: JSON ARRAY WITH CITIES', ['cities' => $decoded]);
                return $decoded;
            } else {
                Log::info('🏙️ CASE 2: EMPTY JSON ARRAY - RETURN NULL (ALL CITIES)');
                return null;
            }
        } else {
            Log::warning('❌ JSON DECODE FAILED:', [
                'error' => json_last_error_msg(),
                'input' => $targetCitiesValue
            ]);
        }
    }

    // ✅ CASE 3: Already an array
    if (is_array($targetCitiesValue)) {
        if (!empty($targetCitiesValue)) {
            Log::info('✅ CASE 3: DIRECT ARRAY WITH CITIES', ['cities' => $targetCitiesValue]);
            return $targetCitiesValue;
        } else {
            Log::info('🏙️ CASE 3: EMPTY ARRAY - RETURN NULL (ALL CITIES)');
            return null;
        }
    }

    // ✅ CASE 4: No target_cities field or empty - show in ALL cities
    Log::info('🏙️ CASE 4: NO CITIES SPECIFIED - RETURN NULL (ALL CITIES)');
    return null;
}
    /**
     * Toggle advertisement status
     */
    public function toggleStatus($id)
    {
        $advertisement = Advertisement::find($id);

        if (!$advertisement) {
            return response()->json([
                'success' => false,
                'message' => 'Advertisement not found'
            ], 404);
        }

        try {
            $advertisement->update([
                'is_active' => !$advertisement->is_active
            ]);

            $status = $advertisement->is_active ? 'activated' : 'deactivated';

            Log::info('✅ ADVERTISEMENT STATUS TOGGLED', [
                'id' => $id,
                'status' => $status
            ]);

            return response()->json([
                'success' => true,
                'message' => "Advertisement {$status} successfully",
                'data' => $advertisement
            ]);

        } catch (\Exception $e) {
            Log::error('❌ FAILED TO TOGGLE ADVERTISEMENT STATUS', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle advertisement status'
            ], 500);
        }
    }

    /**
     * Get active advertisements for customers
     */
    public function getActiveAds(Request $request)
    {
        try {
            // ✅ IMPROVED: Get city from multiple sources
            $city = $request->header('X-City') ?? 
                    $request->input('city') ?? 
                    $request->city ?? 
                    'Sana\'a';
            
            Log::info('📢 FETCHING ADS FOR CITY', [
                'city' => $city,
                'x-city-header' => $request->header('X-City'),
                'city-param' => $request->input('city'),
                'all-headers' => $request->headers->all()
            ]);

            // Get all active ads first
            $allAds = Advertisement::where('is_active', true)
                ->where(function($query) {
                    $query->where('start_date', '<=', now())
                          ->orWhereNull('start_date');
                })
                ->where(function($query) {
                    $query->where('end_date', '>=', now())
                          ->orWhereNull('end_date');
                })
                ->orderBy('priority', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();

            // Debug: log all ads and their target_cities
            Log::info('🔍 ALL ACTIVE ADS BEFORE FILTERING', [
                'total' => $allAds->count(),
                'requested_city' => $city,
                'ads' => $allAds->map(function($ad) {
                    return [
                        'id' => $ad->id,
                        'title' => $ad->title,
                        'target_cities' => $ad->target_cities,
                        'target_cities_type' => gettype($ad->target_cities),
                        'is_active' => $ad->is_active
                    ];
                })->toArray()
            ]);

            // ✅ FIXED: Proper filtering logic
            $filteredAds = $allAds->filter(function ($ad) use ($city) {
                $cities = $ad->target_cities;
                
                Log::info('🔍 CHECKING AD FOR CITY FILTERING', [
                    'ad_id' => $ad->id,
                    'ad_title' => $ad->title,
                    'target_cities' => $cities,
                    'requested_city' => $city,
                    'cities_is_null' => is_null($cities),
                    'cities_is_array' => is_array($cities),
                    'cities_is_empty' => is_array($cities) && empty($cities),
                    'city_in_array' => is_array($cities) && in_array($city, $cities)
                ]);

                // ✅ FIXED: If cities is null OR empty array, show to ALL cities
                if ($cities === null || (is_array($cities) && empty($cities))) {
                    Log::info('✅ AD SHOWS TO ALL CITIES (null or empty array)', ['ad_id' => $ad->id]);
                    return true;
                }
                
                // ✅ If it's an array, check if city is included
                if (is_array($cities) && in_array($city, $cities)) {
                    Log::info('✅ AD SHOWS TO SPECIFIC CITY', ['ad_id' => $ad->id, 'city' => $city]);
                    return true;
                }
                
                Log::info('❌ AD FILTERED OUT - CITY NOT MATCHED', ['ad_id' => $ad->id]);
                return false;
            });

            $advertisements = $filteredAds->map(function ($ad) {
                $imageUrl = null;
                if ($ad->image) {
                    if (str_starts_with($ad->image, 'http')) {
                        $imageUrl = $ad->image;
                    } else {
                        $imageUrl = asset('storage/' . $ad->image);
                    }
                }
                
                return [
                    'id' => $ad->id,
                    'title' => $ad->title,
                    'subtitle' => $ad->subtitle,
                    'image' => $imageUrl,
                    'target_url' => $ad->target_url,
                    'priority' => $ad->priority,
                    'target_cities' => $ad->target_cities,
                ];
            });

            Log::info('📢 ADS FILTERED SUCCESS', [
                'city' => $city,
                'total_ads' => $allAds->count(),
                'filtered_ads' => $advertisements->count(),
                'ads_titles' => $advertisements->pluck('title')->toArray()
            ]);

            return response()->json([
                'success' => true,
                'data' => $advertisements->values(),
                'message' => 'Active advertisements retrieved successfully',
                'debug' => [
                    'requested_city' => $city,
                    'total_active_ads' => $allAds->count(),
                    'filtered_ads' => $advertisements->count(),
                    'city_header_received' => $request->header('X-City')
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('❌ FAILED TO FETCH ACTIVE ADS', [
                'city' => $city ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch advertisements'
            ], 500);
        }
    }

    /**
     * Get advertisement statistics
     */
    public function getStats()
    {
        try {
            $total = Advertisement::count();
            $active = Advertisement::where('is_active', true)->count();
            $inactive = $total - $active;
            $expired = Advertisement::where('end_date', '<', now())->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total' => $total,
                    'active' => $active,
                    'inactive' => $inactive,
                    'expired' => $expired,
                ],
                'message' => 'Advertisement statistics retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('❌ FAILED TO FETCH AD STATS', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch advertisement statistics'
            ], 500);
        }
    }

    /**
     * Show specific advertisement
     */
    public function show($id)
    {
        try {
            $advertisement = Advertisement::with('admin')->find($id);

            if (!$advertisement) {
                return response()->json([
                    'success' => false,
                    'message' => 'Advertisement not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $advertisement,
                'message' => 'Advertisement retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('❌ FAILED TO FETCH ADVERTISEMENT', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch advertisement'
            ], 500);
        }
    }

    /**
     * Update advertisement with image support (form data)
     */
    public function updateWithImage(Request $request, $id)
    {
        Log::info('🖼️ UPDATING ADVERTISEMENT WITH IMAGE SUPPORT', ['id' => $id]);
        
        return $this->update($request, $id); // Reuse the main update method
    }


    /**
 * Remove the specified advertisement.
 */
public function destroy($id)
{
    Log::info('🗑️ DELETING ADVERTISEMENT', ['id' => $id]);

    try {
        $advertisement = Advertisement::find($id);

        if (!$advertisement) {
            return response()->json([
                'success' => false,
                'message' => 'Advertisement not found'
            ], 404);
        }

        // Delete the image file if it exists
        if ($advertisement->image && Storage::disk('public')->exists($advertisement->image)) {
            Storage::disk('public')->delete($advertisement->image);
            Log::info('✅ OLD IMAGE DELETED', ['path' => $advertisement->image]);
        }

        // Delete the record
        $advertisement->delete();

        Log::info('✅ ADVERTISEMENT DELETED SUCCESSFULLY', ['id' => $id]);

        return response()->json([
            'success' => true,
            'message' => 'Advertisement deleted successfully'
        ]);

    } catch (\Exception $e) {
        Log::error('❌ FAILED TO DELETE ADVERTISEMENT', [
            'id' => $id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return response()->json([
            'success' => false,
            'message' => 'Failed to delete advertisement: ' . $e->getMessage()
        ], 500);
    }
}
}