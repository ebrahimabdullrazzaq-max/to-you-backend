<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use App\Notifications\EmployerApprovedNotification;
use App\Notifications\EmployerRejectedNotification;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

class AdminEmployerController extends Controller
{
    /**
     * List all employers with ALL details (for admin dashboard)
     */
    public function index(Request $request)
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
     * Get pending employers
     */
    public function getPendingEmployers(Request $request)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $query = User::where('role', 'employer')
                    ->where('status', 'pending')
                    ->orderBy('created_at', 'desc');

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
            'rating',
            'total_orders',
            'is_online',
            'is_available',
            'email_verified_at',
            'created_at',
            'updated_at'
        ]);

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
     * Approve or reject an employer
     */
    public function updateStatus(Request $request, $id)
    {
        // ✅ Only allow admins
        $admin = auth()->user();
        if ($admin->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // ✅ Validate input
        $request->validate([
            'status' => 'required|in:approved,rejected',
        ]);

        // ✅ Find employer
        $employer = User::where('id', $id)
            ->where('role', 'employer')
            ->first();

        if (!$employer) {
            return response()->json(['error' => 'Employer not found'], 404);
        }

        // ✅ Update status
        $oldStatus = $employer->status;
        $employer->status = $request->status;
        
        // Update online/available status
        if ($request->status === 'approved') {
            $employer->is_online = true;
            $employer->is_available = true;
        } else {
            $employer->is_online = false;
            $employer->is_available = false;
        }
        
        $employer->save();

        // ✅ Send notification
        if ($request->status === 'approved' && $oldStatus !== 'approved') {
            $employer->notify(new EmployerApprovedNotification());
        } elseif ($request->status === 'rejected' && $oldStatus !== 'rejected') {
            $employer->notify(new EmployerRejectedNotification());
        }

        return response()->json([
            'success' => true,
            'message' => "Employer {$request->status} successfully.",
            'employer' => $employer,
        ]);
    }

    /**
     * Approve an employer (specific endpoint)
     */
    public function approveEmployer($id)
    {
        $admin = auth()->user();
        if ($admin->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $employer = User::where('id', $id)
            ->where('role', 'employer')
            ->first();

        if (!$employer) {
            return response()->json(['error' => 'Employer not found'], 404);
        }

        $oldStatus = $employer->status;
        $employer->status = 'approved';
        $employer->is_online = true;
        $employer->is_available = true;
        $employer->save();

        if ($oldStatus !== 'approved') {
            $employer->notify(new EmployerApprovedNotification());
        }

        return response()->json([
            'success' => true,
            'message' => 'Employer approved successfully.',
            'employer' => $employer,
        ]);
    }

    /**
     * Reject an employer (specific endpoint)
     */
    public function rejectEmployer($id)
    {
        $admin = auth()->user();
        if ($admin->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $employer = User::where('id', $id)
            ->where('role', 'employer')
            ->first();

        if (!$employer) {
            return response()->json(['error' => 'Employer not found'], 404);
        }

        $oldStatus = $employer->status;
        $employer->status = 'rejected';
        $employer->is_online = false;
        $employer->is_available = false;
        $employer->save();

        if ($oldStatus !== 'rejected') {
            $employer->notify(new EmployerRejectedNotification());
        }

        return response()->json([
            'success' => true,
            'message' => 'Employer rejected successfully.',
            'employer' => $employer,
        ]);
    }

    /**
     * Delete an employer
     */
    public function destroy($id)
    {
        // ✅ Only allow admins
        $admin = auth()->user();
        if ($admin->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // ✅ Find employer
        $employer = User::where('id', $id)
            ->where('role', 'employer')
            ->first();

        if (!$employer) {
            return response()->json(['error' => 'Employer not found'], 404);
        }

        // ✅ Delete employer
        $employer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Employer deleted successfully.',
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
}