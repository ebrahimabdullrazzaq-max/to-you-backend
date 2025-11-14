<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Notification;
use App\Notifications\NewEmployerRegistered;
use App\Notifications\EmployerApproved;
use App\Notifications\EmployerRejected;
use App\Notifications\EmployerPendingApprovalNotification;
use Illuminate\Support\Facades\Cache;
use Twilio\Rest\Client as TwilioClient;
use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Register a new employer with admin approval requirement
     */
   public function employerRegister(Request $request)
{
    // 🚨 CLEANUP: Remove any existing user with this email (including soft deleted)
    $deletedCount = User::withTrashed()
        ->where('email', $request->email)
        ->forceDelete();
        
    if ($deletedCount > 0) {
        Log::info('🧹 CLEANED UP EXISTING USER', [
            'email' => $request->email,
            'deleted_count' => $deletedCount
        ]);
    }

    // Continue with normal registration...
    $validator = Validator::make($request->all(), [
        'name' => 'required|string|max:255',
        'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'required|string|max:20',
            'address' => 'required|string|max:500',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'vehicle_type' => 'required|in:motorcycle,car,bicycle,truck,scooter,water_truck',
            'max_delivery_distance' => 'required|integer|min:1|max:100',
            'availability' => 'required|array',
            'availability.days' => 'required|array|min:1',
            'availability.days.*' => 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'availability.start_time' => 'required|date_format:H:i',
            'availability.end_time' => 'required|date_format:H:i|after:availability.start_time',
        ], [
            'availability.days.min' => 'Please select at least one working day.',
            'availability.end_time.after' => 'End time must be after start time.',
            'vehicle_type.in' => 'Please select a valid vehicle type.',
        ]);

        if ($validator->fails()) {
            Log::error('❌ VALIDATION FAILED', ['errors' => $validator->errors()->toArray()]);
            return response()->json([
                'success' => false,
                'message' => 'Please check your input fields',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone' => $request->phone,
                'address' => $request->address,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'vehicle_type' => $request->vehicle_type,
                'max_delivery_distance' => $request->max_delivery_distance,
                'availability' => $request->availability,
                'role' => 'employer',
                'status' => 'pending',
                'registration_type' => 'employer_app',
            ]);

            try {
                event(new Registered($user));
                Log::info('📧 VERIFICATION EMAIL SENT', ['user_id' => $user->id]);
            } catch (\Exception $e) {
                Log::error('❌ FAILED TO SEND VERIFICATION EMAIL', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
            }

            $this->notifyAdminAboutNewEmployer($user);
            $token = $user->createToken('employer_app_token')->plainTextToken;

            DB::commit();

            Log::info('✅ EMPLOYER REGISTERED SUCCESSFULLY', [
                'email' => $user->email,
                'user_id' => $user->id,
                'vehicle_type' => $user->vehicle_type,
            ]);

            return response()->json([
                'success' => true,
                'message' => '🎉 Application submitted successfully! Please check your email for verification and wait for admin approval.',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'status' => $user->status,
                    'vehicle_type' => $user->vehicle_type,
                ],
                'token' => $token,
                'requires_verification' => true,
                'requires_approval' => true,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ EMPLOYER REGISTRATION FAILED', [
                'email' => $request->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Registration failed: ' . $e->getMessage(),
                'error_details' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Register a new user with email verification
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'required|string|max:20',
            'address' => 'required|string|max:500',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone' => $request->phone,
                'address' => $request->address,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'role' => 'customer',
                'status' => 'approved', // ✅ FIXED: was 'active'
            ]);

            event(new Registered($user));
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'User registered successfully. Please check your email for verification.',
                'user' => $user,
                'token' => $token,
                'requires_verification' => true,
            ], 201);

        } catch (\Exception $e) {
            Log::error('❌ CUSTOMER REGISTRATION FAILED', [
                'email' => $request->email,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Registration failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Login with Email/Password
     */
    public function login(Request $request)
    {
        Log::info('🔐 LOGIN ATTEMPT', ['email' => $request->email]);

        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            Log::warning('❌ LOGIN FAILED - USER NOT FOUND', ['email' => $request->email]);
            return response()->json([
                'success' => false,
                'message' => 'The provided credentials are incorrect.',
            ], 401);
        }

        if ($user->google_id && !Hash::check($request->password, $user->password)) {
            Log::warning('❌ GOOGLE USER TRYING PASSWORD LOGIN', ['email' => $request->email]);
            return response()->json([
                'success' => false,
                'message' => 'This email is registered with Google. Please use Google Sign-In.',
                'is_google_user' => true,
            ], 401);
        }

        if (!Hash::check($request->password, $user->password)) {
            Log::warning('❌ INVALID PASSWORD', ['email' => $request->email]);
            return response()->json([
                'success' => false,
                'message' => 'The provided credentials are incorrect.',
            ], 401);
        }

        if ($user->isEmployer()) {
            if (!$user->isActive() && !$user->isApproved()) {
                Log::warning('🚚 EMPLOYER NOT APPROVED', [
                    'email' => $request->email,
                    'status' => $user->status
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Your employer account is pending approval. Please wait for admin approval.',
                    'status' => $user->status,
                ], 403);
            }
        } else {
            if (!$user->isActive() && !$user->isApproved()) {
                Log::warning('❌ USER NOT ACTIVE OR APPROVED', [
                    'email' => $request->email,
                    'status' => $user->status
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Your account is not active. Please contact support.',
                    'status' => $user->status,
                ], 403);
            }
        }

        if (!$user->hasVerifiedEmail()) {
            Log::warning('📧 EMAIL NOT VERIFIED', ['email' => $request->email]);
            return response()->json([
                'success' => false,
                'message' => 'Please verify your email address before logging in.',
                'requires_verification' => true,
                'email' => $user->email
            ], 403);
        }

        $user->update(['last_login_at' => now()]);
        $token = $user->createToken('api_token')->plainTextToken;

        Log::info('✅ LOGIN SUCCESSFUL', [
            'email' => $user->email,
            'role' => $user->role,
            'status' => $user->status
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'user' => $user->loadMissing([]),
            'token' => $token,
        ]);
    }


public function googleAuth(Request $request)
    {
        Log::info('🔐 GOOGLE AUTH ATTEMPT', ['email' => $request->email]);

        DB::beginTransaction();
        
        try {
            $validator = Validator::make($request->all(), [
                'google_token' => 'required|string',
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $email = trim($request->email);
            
            // 🚨 FIRST: Force delete any existing user with this email
            $deletedCount = User::where('email', $email)->forceDelete();
            
            if ($deletedCount > 0) {
                Log::info('🧹 DELETED EXISTING USER', [
                    'email' => $email,
                    'deleted_count' => $deletedCount
                ]);
            }
            
            // 🚨 SECOND: Force create new user (no duplicate checks)
            Log::info('👤 FORCE CREATING NEW USER', ['email' => $email]);
            
            $user = User::create([
                'name' => $request->name,
                'email' => $email,
                'password' => Hash::make(Str::random(16)),
                'google_id' => hash('sha256', $request->google_token),
                'email_verified_at' => now(),
                'role' => 'customer',
                'status' => 'approved',
                'registration_type' => 'customer_app',
                'is_online' => false,
                'is_available' => false,
                'rating' => 0.0,
                'total_orders' => 0,
                'max_delivery_distance' => 20,
                'address' => $request->address ?? 'Default Address',
                'latitude' => $request->latitude ?? 0.0,
                'longitude' => $request->longitude ?? 0.0,
                'phone' => $request->phone ?? '+967000000000',
            ]);

            DB::commit();

            $token = $user->createToken('google_auth_token')->plainTextToken;

            Log::info('✅ GOOGLE AUTH SUCCESSFUL', [
                'email' => $user->email,
                'user_id' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'user' => $user,
                'token' => $token,
                'message' => 'Google authentication successful'
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('❌ GOOGLE AUTH FAILED', [
                'email' => $request->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Google authentication failed: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Send password reset link
     */
    public function forgotPassword(Request $request)
    {
        Log::info('🔐 PASSWORD RESET REQUEST', ['email' => $request->email]);

        $validator = Validator::make($request->all(), [
            'email' => 'required|email'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                Log::warning('❌ PASSWORD RESET - USER NOT FOUND', ['email' => $request->email]);
                return response()->json([
                    'success' => true,
                    'message' => 'If this email exists in our system, a password reset link has been sent.'
                ], 200);
            }

            if ($user->google_id && !$user->password) {
                Log::warning('❌ PASSWORD RESET - GOOGLE USER', ['email' => $request->email]);
                return response()->json([
                    'success' => false,
                    'message' => 'This email is registered with Google. Please use Google Sign-In.',
                    'is_google_user' => true,
                ], 400);
            }

            Log::info('✅ PASSWORD RESET - USER FOUND', [
                'user_id' => $user->id,
                'email' => $user->email,
                'role' => $user->role
            ]);

            $token = Str::random(60);
            DB::table('password_resets')->updateOrInsert(
                ['email' => $request->email],
                [
                    'token' => Hash::make($token),
                    'created_at' => now()
                ]
            );

            $this->sendPasswordResetEmail($user, $token);

            return response()->json([
                'success' => true,
                'message' => 'Password reset link has been sent to your email. Please check your inbox and spam folder.',
            ]);

        } catch (\Exception $e) {
            Log::error('💥 PASSWORD RESET ERROR', [
                'email' => $request->email,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again.',
            ], 500);
        }
    }

    /**
     * Send password reset email
     */
    private function sendPasswordResetEmail(User $user, string $token)
    {
        try {
            $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
            $resetLink = "{$frontendUrl}/reset-password?token={$token}&email=" . urlencode($user->email);
            $subject = "🔐 Password Reset Request - TO YOU";
            
            $message = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #4361EE; color: white; padding: 20px; text-align: center; }
                    .content { background: #f9f9f9; padding: 20px; }
                    .footer { background: #eee; padding: 10px; text-align: center; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>Password Reset Request</h2>
                    </div>
                    <div class='content'>
                        <p>Hello <strong>{$user->name}</strong>,</p>
                        <p>You requested to reset your password for your TO YOU account.</p>
                        
                        <div style='text-align: center; margin: 20px 0;'>
                            <a href='{$resetLink}' style='
                                background: #4361EE; 
                                color: white; 
                                padding: 12px 24px; 
                                text-decoration: none; 
                                border-radius: 5px; 
                                display: inline-block;
                                font-weight: bold;
                            '>Reset Your Password</a>
                        </div>
                        
                        <p>If the button doesn't work, copy and paste this link in your browser:</p>
                        <p><code style='background: #f5f5f5; padding: 10px; display: block; word-break: break-all;'>{$resetLink}</code></p>
                        
                        <p><strong>This link will expire in 1 hour.</strong></p>
                        <p>If you didn't request this reset, please ignore this email.</p>
                    </div>
                    <div class='footer'>
                        <p>TO YOU Delivery Service</p>
                    </div>
                </div>
            </body>
            </html>
            ";

            Mail::html($message, function ($mail) use ($user, $subject) {
                $mail->to($user->email)
                     ->subject($subject)
                     ->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'));
            });

            Log::info('📧 PASSWORD RESET EMAIL SENT', ['user_id' => $user->id]);

        } catch (\Exception $e) {
            Log::error('❌ FAILED TO SEND PASSWORD RESET EMAIL', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Reset password
     */
    public function resetPassword(Request $request)
    {
        Log::info('🔄 PASSWORD RESET REQUEST RECEIVED', ['email' => $request->email]);

        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'token' => 'required',
            'password' => 'required|min:8|confirmed'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $resetRecord = DB::table('password_resets')
                ->where('email', $request->email)
                ->first();

            if (!$resetRecord) {
                Log::warning('❌ RESET RECORD NOT FOUND', ['email' => $request->email]);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid reset token.'
                ], 400);
            }

            $tokenAge = now()->diffInMinutes($resetRecord->created_at);
            if ($tokenAge > 60) {
                Log::warning('❌ TOKEN EXPIRED', [
                    'email' => $request->email,
                    'token_age_minutes' => $tokenAge
                ]);
                DB::table('password_resets')->where('email', $request->email)->delete();
                return response()->json([
                    'success' => false,
                    'message' => 'Reset token has expired. Please request a new one.'
                ], 400);
            }

            if (!Hash::check($request->token, $resetRecord->token)) {
                Log::warning('❌ INVALID TOKEN', ['email' => $request->email]);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid reset token.'
                ], 400);
            }

            $user = User::where('email', $request->email)->first();
            if (!$user) {
                Log::warning('❌ USER NOT FOUND', ['email' => $request->email]);
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.'
                ], 404);
            }

            if ($user->google_id && !$user->password) {
                $user->update([
                    'password' => Hash::make($request->password),
                    'google_id' => null,
                ]);
            } else {
                $user->update([
                    'password' => Hash::make($request->password)
                ]);
            }

            DB::table('password_resets')->where('email', $request->email)->delete();
            Log::info('✅ PASSWORD RESET SUCCESSFUL', ['user_id' => $user->id, 'email' => $user->email]);

            return response()->json([
                'success' => true,
                'message' => 'Password has been reset successfully. You can now login with your new password.'
            ]);

        } catch (\Exception $e) {
            Log::error('💥 PASSWORD RESET ERROR', [
                'email' => $request->email,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset password. Please try again.'
            ], 500);
        }
    }

    /**
     * Send email verification notification
     */
    public function sendVerificationEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'message' => 'Email already verified'
            ], 400);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'success' => true,
            'message' => 'Verification email sent successfully'
        ]);
    }

    /**
     * Verify email (Laravel's built-in verification)
     */
    public function verify(Request $request, $id, $hash)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid verification link'
            ], 400);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'message' => 'Email already verified'
            ], 400);
        }

        $user->markEmailAsVerified();

        // ✅ Already handled in User model — no need to repeat here
        // (See User::markEmailAsVerified())

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully. You can now login to your account.'
        ]);
    }

    /**
     * Check email verification status (for logged-in users)
     */
    public function checkVerificationStatus(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'is_verified' => $user->hasVerifiedEmail(),
            'email_verified_at' => $user->email_verified_at
        ]);
    }

    /**
     * Check verification status by email (public endpoint)
     */
    public function checkVerificationByEmail(Request $request)
    {
        Log::info('Checking verification status for email: ' . $request->email);

        $validator = Validator::make($request->all(), [
            'email' => 'required|email'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            Log::warning('User not found for verification check: ' . $request->email);
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $isVerified = $user->hasVerifiedEmail();
        Log::info('Verification status for ' . $request->email . ': ' . ($isVerified ? 'verified' : 'not verified'));

        return response()->json([
            'success' => true,
            'is_verified' => $isVerified,
            'email_verified_at' => $user->email_verified_at,
            'message' => $isVerified ? 'Email verified successfully!' : 'Email not verified yet'
        ]);
    }

    /**
     * Change password
     */
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect.'
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->password)
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully!'
        ]);
    }

    /**
     * Logout
     */
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();
        Log::info('✅ USER LOGGED OUT', ['user_id' => $request->user()->id]);
        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.'
        ]);
    }

    /**
     * Get current authenticated user
     */
    public function getUser(Request $request)
    {
        $user = $request->user()->load([]);
        return response()->json([
            'success' => true,
            'user' => $user
        ]);
    }

    /**
     * Health check endpoint
     */
    public function healthCheck()
    {
        $emailConfig = [
            'driver' => config('mail.default'),
            'host' => config('mail.mailers.smtp.host'),
            'port' => config('mail.mailers.smtp.port'),
            'username' => config('mail.mailers.smtp.username'),
            'from_address' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
        ];

        return response()->json([
            'success' => true,
            'message' => 'API is working!',
            'timestamp' => now(),
            'environment' => app()->environment(),
            'email_config' => $emailConfig,
            'services' => [
                'database' => 'connected',
                'mail' => !empty($emailConfig['host']) ? 'configured' : 'not configured',
            ]
        ]);
    }

    /**
     * Notify admin about new employer registration
     */
    private function notifyAdminAboutNewEmployer(User $employer)
    {
        try {
            $admins = User::where('role', 'admin')->get();
            foreach ($admins as $admin) {
                $admin->notify(new NewEmployerRegistered($employer));
            }
            Log::info('📧 ADMIN NOTIFIED ABOUT NEW EMPLOYER', [
                'employer_id' => $employer->id,
                'admin_count' => $admins->count()
            ]);
        } catch (\Exception $e) {
            Log::error('❌ FAILED TO NOTIFY ADMIN', [
                'employer_id' => $employer->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Check user status and type
     */
    public function checkUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status,
                'is_google_user' => !is_null($user->google_id),
                'email_verified' => !is_null($user->email_verified_at),
                'has_password' => !is_null($user->password),
                'vehicle_type' => $user->vehicle_type,
            ]
        ]);
    }

    /**
     * Test email configuration
     */
    public function testEmail(Request $request)
    {
        try {
            $email = $request->email ?? env('MAIL_FROM_ADDRESS');
            Mail::html('
                <h1>Test Email from TO YOU</h1>
                <p>This is a test email to verify email configuration.</p>
                <p>If you receive this, your email setup is working correctly!</p>
                <p>Timestamp: ' . now() . '</p>
            ', function ($mail) use ($email) {
                $mail->to($email)
                     ->subject('Test Email - TO YOU System')
                     ->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'));
            });

            return response()->json([
                'success' => true,
                'message' => 'Test email sent successfully to ' . $email
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send test email: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Register a new admin (simplified - no location required)
     */
    public function adminRegister(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'required|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone' => $request->phone,
                'address' => 'Admin Account',
                'latitude' => 0.0,
                'longitude' => 0.0,
                'role' => 'admin',
                'status' => 'approved',
                'is_online' => false,
                'is_available' => false,
                'rating' => 0.0,
                'total_orders' => 0,
            ]);

            event(new Registered($user));
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Admin registered successfully. Please check your email for verification.',
                'user' => $user,
                'token' => $token,
                'requires_verification' => true,
            ], 201);

        } catch (\Exception $e) {
            Log::error('❌ ADMIN REGISTRATION FAILED', [
                'email' => $request->email,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Registration failed: ' . $e->getMessage()
            ], 500);
        }
    }


    public function sendPhoneVerification(Request $request)
    {
        Log::info('📱 SENDING PHONE VERIFICATION', ['phone' => $request->phone_number]);

        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string|max:20'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $phoneNumber = $request->phone_number;
            
            // Generate 6-digit verification code
            $verificationCode = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

            // Delete any existing verification codes for this phone
            DB::table('phone_verifications')
                ->where('phone_number', $phoneNumber)
                ->delete();

            // Create new verification code
            DB::table('phone_verifications')->insert([
                'phone_number' => $phoneNumber,
                'code' => $verificationCode,
                'created_at' => now(),
                'expires_at' => now()->addMinutes(10), // Code expires in 10 minutes
                'attempts' => 0,
            ]);

            Log::info('✅ VERIFICATION CODE GENERATED', [
                'phone' => $phoneNumber,
                'code' => $verificationCode
            ]);

            // TODO: في الإنتاج، أرسل الرسالة الحقيقية عبر SMS
            // $this->sendRealSMS($phoneNumber, $verificationCode);

            // في التطوير، أرجع الكود في الرد
            if (app()->environment('local', 'development')) {
                return response()->json([
                    'success' => true,
                    'message' => 'تم إرسال كود التحقق إلى هاتفك',
                    'debug_code' => $verificationCode, // فقط للتطوير
                    'note' => 'في الإنتاج، سيتم إرسال هذا الكود إلى هاتفك'
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم إرسال كود التحقق إلى هاتفك'
            ]);

        } catch (\Exception $e) {
            Log::error('❌ FAILED TO SEND PHONE VERIFICATION', [
                'phone' => $request->phone_number,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'فشل إرسال كود التحقق. يرجى المحاولة مرة أخرى.'
            ], 500);
        }
    }

    /**
     * Verify phone code and login/register
     */
    public function verifyPhoneCode(Request $request)
    {
        Log::info('🔐 VERIFYING PHONE CODE', ['phone' => $request->phone_number]);

        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string|max:20',
            'verification_code' => 'required|string|size:6'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $phoneNumber = $request->phone_number;
            $code = $request->verification_code;

            // Find verification record
            $verification = DB::table('phone_verifications')
                ->where('phone_number', $phoneNumber)
                ->where('code', $code)
                ->where('expires_at', '>', now())
                ->first();

            if (!$verification) {
                Log::warning('❌ INVALID OR EXPIRED VERIFICATION CODE', [
                    'phone' => $phoneNumber,
                    'code' => $code
                ]);

                // Increment attempts
                DB::table('phone_verifications')
                    ->where('phone_number', $phoneNumber)
                    ->increment('attempts');

                return response()->json([
                    'success' => false,
                    'message' => 'كود التحقق غير صحيح أو منتهي الصلاحية'
                ], 400);
            }

            // Mark as verified
            DB::table('phone_verifications')
                ->where('id', $verification->id)
                ->update(['verified_at' => now()]);

            Log::info('✅ PHONE VERIFICATION SUCCESSFUL', [
                'phone' => $phoneNumber,
                'verification_id' => $verification->id
            ]);

            // Check if user exists with this phone number
            $user = User::where('phone', $phoneNumber)->first();

            if ($user) {
                // User exists - login
                $user->update(['last_login_at' => now()]);
                $token = $user->createToken('phone_auth_token')->plainTextToken;

                Log::info('✅ USER LOGGED IN WITH PHONE', [
                    'user_id' => $user->id,
                    'phone' => $phoneNumber
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'تم تسجيل الدخول بنجاح',
                    'user' => $user,
                    'token' => $token,
                    'is_new_user' => false
                ]);
            } else {
                // User doesn't exist - need registration data
                return response()->json([
                    'success' => true,
                    'message' => 'تم التحقق من رقم الهاتف بنجاح',
                    'is_new_user' => true,
                    'requires_registration' => true
                ]);
            }

        } catch (\Exception $e) {
            Log::error('❌ PHONE VERIFICATION FAILED', [
                'phone' => $request->phone_number,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'فشل التحقق. يرجى المحاولة مرة أخرى.'
            ], 500);
        }
    }

    /**
     * Register new user with phone number
     */
    public function registerWithPhone(Request $request)
    {
        Log::info('📱 REGISTERING USER WITH PHONE', ['phone' => $request->phone_number]);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone_number' => 'required|string|max:20|unique:users,phone',
            'verification_code' => 'required|string|size:6',
            'address' => 'required|string|max:500',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // First verify the phone code
            $phoneNumber = $request->phone_number;
            $code = $request->verification_code;

            $verification = DB::table('phone_verifications')
                ->where('phone_number', $phoneNumber)
                ->where('code', $code)
                ->where('expires_at', '>', now())
                ->whereNotNull('verified_at')
                ->first();

            if (!$verification) {
                return response()->json([
                    'success' => false,
                    'message' => 'يرجى التحقق من رقم الهاتف أولاً'
                ], 400);
            }

            // Create new user
            $user = User::create([
                'name' => $request->name,
                'email' => $phoneNumber . '@toyou.app', // Generate email from phone
                'phone' => $phoneNumber,
                'password' => Hash::make(Str::random(16)), // Random password for phone users
                'address' => $request->address,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'role' => 'customer',
                'status' => 'approved',
                'phone_verified_at' => now(),
                'registration_type' => 'phone',
            ]);

            // Delete used verification code
            DB::table('phone_verifications')
                ->where('phone_number', $phoneNumber)
                ->delete();

            $token = $user->createToken('phone_auth_token')->plainTextToken;

            Log::info('✅ USER REGISTERED WITH PHONE SUCCESSFULLY', [
                'user_id' => $user->id,
                'phone' => $phoneNumber
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء الحساب بنجاح',
                'user' => $user,
                'token' => $token,
                'is_new_user' => true
            ], 201);

        } catch (\Exception $e) {
            Log::error('❌ PHONE REGISTRATION FAILED', [
                'phone' => $request->phone_number,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'فشل إنشاء الحساب. يرجى المحاولة مرة أخرى.'
            ], 500);
        }
    }

    /**
     * Send real SMS (to be implemented with SMS provider)
     */
    private function sendRealSMS($phoneNumber, $code)
    {
        // TODO: Implement with your SMS provider (Twilio, MessageBird, etc.)
        // Example with Twilio:
        /*
        $twilio = new \Twilio\Rest\Client(env('TWILIO_SID'), env('TWILIO_TOKEN'));
        
        $message = $twilio->messages->create(
            $phoneNumber,
            [
                'from' => env('TWILIO_PHONE_NUMBER'),
                'body' => "كود التحقق: {$code} - TO YOU"
            ]
        );
        */
        
        Log::info('📲 SMS SENT (SIMULATED)', [
            'phone' => $phoneNumber,
            'code' => $code
        ]);
    }
}