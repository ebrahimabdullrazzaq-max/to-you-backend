<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

class AdminController extends Controller
{
    /**
     * List all users (customers, employers, admins) with optional filters
     */
    public function listUsers(Request $request)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $query = User::orderBy('created_at', 'desc');

        // Filter for recent users (last 24 hours)
        if ($request->has('recent') && $request->recent == 'true') {
            $query->where('created_at', '>=', now()->subDay());
        }

        // Filter by role if specified
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        $users = $query->get(['id', 'name', 'email', 'phone', 'address', 'role', 'status', 'created_at']);

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    /**
     * Show a specific user by ID
     */
    public function showUser($id)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $user = User::where('id', $id)
            ->first(['id', 'name', 'email', 'phone', 'address', 'role', 'status', 'created_at']);

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $user,
        ]);
    }

    /**
     * List all employers (users with role = 'employer') with ALL details
     */
    public function listEmployers(Request $request)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $query = User::where('role', 'employer')->orderBy('created_at', 'desc');

        // Filter by status if specified
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Get ALL employer details
        $employers = $query->get([
            'id',
            'name', 
            'email',
            'phone',
            'address',
            'latitude',
            'longitude',
            'vehicle_type',
            'max_delivery_distance',
            'availability',
            'status',
            'role',
            'rating',
            'total_orders',
            'is_online',
            'is_available',
            'email_verified_at',
            'created_at',
            'updated_at'
        ]);

        // Format the response with proper availability parsing
        $formattedEmployers = $employers->map(function ($employer) {
            return [
                'id' => $employer->id,
                'name' => $employer->name,
                'email' => $employer->email,
                'phone' => $employer->phone,
                'address' => $employer->address,
                'latitude' => $employer->latitude,
                'longitude' => $employer->longitude,
                'vehicle_type' => $employer->vehicle_type,
                'max_delivery_distance' => $employer->max_delivery_distance,
                'availability' => $this->parseAvailability($employer->availability),
                'status' => $employer->status,
                'role' => $employer->role,
                'rating' => $employer->rating ?? 0.0,
                'total_orders' => $employer->total_orders ?? 0,
                'is_online' => (bool) $employer->is_online,
                'is_available' => (bool) $employer->is_available,
                'email_verified' => !is_null($employer->email_verified_at),
                'created_at' => $employer->created_at,
                'updated_at' => $employer->updated_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formattedEmployers,
        ]);
    }

    /**
     * Parse availability JSON safely
     */
    private function parseAvailability($availability)
    {
        if (is_string($availability)) {
            try {
                return json_decode($availability, true);
            } catch (\Exception $e) {
                return ['days' => [], 'start_time' => 'Not set', 'end_time' => 'Not set'];
            }
        }
        
        if (is_array($availability)) {
            return $availability;
        }
        
        return ['days' => [], 'start_time' => 'Not set', 'end_time' => 'Not set'];
    }

    /**
     * Get employer details by ID
     */
    public function getEmployer($id)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $employer = User::where('id', $id)
            ->where('role', 'employer')
            ->first([
                'id',
                'name', 
                'email',
                'phone',
                'address',
                'latitude',
                'longitude',
                'vehicle_type',
                'max_delivery_distance',
                'availability',
                'status',
                'rating',
                'total_orders',
                'is_online',
                'is_available',
                'email_verified_at',
                'created_at',
                'updated_at'
            ]);

        if (!$employer) {
            return response()->json(['error' => 'Employer not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $employer->id,
                'name' => $employer->name,
                'email' => $employer->email,
                'phone' => $employer->phone,
                'address' => $employer->address,
                'latitude' => $employer->latitude,
                'longitude' => $employer->longitude,
                'vehicle_type' => $employer->vehicle_type,
                'max_delivery_distance' => $employer->max_delivery_distance,
                'availability' => $this->parseAvailability($employer->availability),
                'status' => $employer->status,
                'rating' => $employer->rating ?? 0.0,
                'total_orders' => $employer->total_orders ?? 0,
                'is_online' => (bool) $employer->is_online,
                'is_available' => (bool) $employer->is_available,
                'email_verified' => !is_null($employer->email_verified_at),
                'created_at' => $employer->created_at,
                'updated_at' => $employer->updated_at,
            ],
        ]);
    }

    /**
     * List orders with optional status filter
     */
    public function listOrders(Request $request)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $query = Order::orderBy('created_at', 'desc');

        // Filter by status if specified
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $orders = $query->get();

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    /**
     * Approve an employer
     */
    public function approveEmployer($id)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $user = User::where('id', $id)->where('role', 'employer')->first();

        if (!$user) {
            return response()->json(['error' => 'Employer not found'], 404);
        }

        $user->status = 'approved';
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Employer approved successfully.',
            'data' => $user,
        ]);
    }

    /**
     * Reject an employer
     */
    public function rejectEmployer($id)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $user = User::where('id', $id)->where('role', 'employer')->first();

        if (!$user) {
            return response()->json(['error' => 'Employer not found'], 404);
        }

        $user->status = 'rejected';
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Employer rejected successfully.',
            'data' => $user,
        ]);
    }

    /**
     * Update employer status (generic method)
     */
    public function updateEmployerStatus(Request $request, $id)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'status' => 'required|in:pending,approved,rejected,active,suspended'
        ]);

        $user = User::where('id', $id)->where('role', 'employer')->first();

        if (!$user) {
            return response()->json(['error' => 'Employer not found'], 404);
        }

        $user->status = $request->status;
        
        // Update online/available status based on new status
        if (in_array($request->status, ['approved', 'active'])) {
            $user->is_online = true;
            $user->is_available = true;
        } else {
            $user->is_online = false;
            $user->is_available = false;
        }
        
        $user->save();

        return response()->json([
            'success' => true,
            'message' => "Employer status updated to {$request->status} successfully.",
            'data' => $user,
        ]);
    }

    public function deleteUser($id)
    {
        try {
            $user = User::findOrFail($id);
            $user->delete();
            
            return response()->json([
                'message' => 'User deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error deleting user: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete employer specifically
     */
    public function deleteEmployer($id)
    {
        try {
            $employer = User::where('id', $id)->where('role', 'employer')->firstOrFail();
            $employer->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Employer deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Delete employer error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error deleting employer: ' . $e->getMessage()
            ], 500);
        }
    }

    public function newRegistrations(Request $request)
    {
        try {
            // Get users registered in the last 24 hours
            $newUsers = User::where('created_at', '>=', now()->subDays(1))
                ->orderBy('created_at', 'desc')
                ->get();
                
            return response()->json([
                'data' => $newUsers
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error fetching new registrations: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get employer statistics
     */
    public function employerStats()
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $totalEmployers = User::where('role', 'employer')->count();
        $pendingEmployers = User::where('role', 'employer')->where('status', 'pending')->count();
        $approvedEmployers = User::where('role', 'employer')->where('status', 'approved')->count();
        $activeEmployers = User::where('role', 'employer')->where('status', 'active')->count();
        $onlineEmployers = User::where('role', 'employer')->where('is_online', true)->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_employers' => $totalEmployers,
                'pending_employers' => $pendingEmployers,
                'approved_employers' => $approvedEmployers,
                'active_employers' => $activeEmployers,
                'online_employers' => $onlineEmployers,
            ]
        ]);
    }
}