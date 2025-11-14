<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CustomerOrderController;
use App\Http\Controllers\Api\AdminOrderController;
use App\Http\Controllers\Api\EmployerController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\Api\AdminEmployerController;
use App\Http\Controllers\Api\OrderItemController;
use App\Http\Controllers\Api\CustomOrderController;
use App\Http\Controllers\Api\PhoneVerificationController;
use App\Http\Controllers\Api\AdvertisementController;
use App\Http\Controllers\Api\WaterTankOrderController;

// -----------------------------
// Public routes (no authentication required)
// -----------------------------

// ✅ AUTH ROUTES
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/employer-register', [AuthController::class, 'employerRegister']);
Route::post('/send-phone-verification', [AuthController::class, 'sendPhoneVerification']);
Route::post('/verify-phone-code', [AuthController::class, 'verifyPhoneCode']);
Route::post('/register-with-phone', [AuthController::class, 'registerWithPhone']);

// ✅ تحقق الهاتف لتطبيق "اليك" - مسارات عامة
Route::post('/send-verification-sms', [PhoneVerificationController::class, 'sendVerificationCode']);
Route::post('/verify-sms-code', [PhoneVerificationController::class, 'verifyCode']);
Route::post('/resend-verification-sms', [PhoneVerificationController::class, 'resendCode']);

// ✅ مسارات اختبار نظام التحقق
Route::get('/test-verification-system', [PhoneVerificationController::class, 'testVerificationSystem']);
Route::get('/test-real-sms', [PhoneVerificationController::class, 'testRealSMS']);
Route::get('/system-stats', [PhoneVerificationController::class, 'getSystemStats']);
// ✅ اختبار SMS لأي رقم يمني
Route::post('/test-sms-any-number', [PhoneVerificationController::class, 'testSMSAnyNumber']);

// ✅ اختبار إعدادات Twilio مباشرة
Route::get('/test-twilio-env', function () {
    return response()->json([
        'env_sid' => env('TWILIO_SID'),
        'env_token_exists' => !empty(env('TWILIO_TOKEN')),
        'env_token_length' => env('TWILIO_TOKEN') ? strlen(env('TWILIO_TOKEN')) : 0,
        'env_number' => env('TWILIO_NUMBER'),
        'config_sid' => config('services.twilio.sid'),
        'config_token_exists' => !empty(config('services.twilio.token')),
        'config_token_length' => config('services.twilio.token') ? strlen(config('services.twilio.token')) : 0,
        'config_number' => config('services.twilio.number'),
        'app_env' => config('app.env'),
        'app_debug' => config('app.debug'),
    ]);
});

// ✅ اختبار SMS لتطبيق اليك
Route::get('/test-sms-elyak', function () {
    try {
        $twilio = new Twilio\Rest\Client(
            env('TWILIO_SID'),
            env('TWILIO_TOKEN')
        );
        
        $message = $twilio->messages->create(
            '+967781058382',
            [
                'from' => env('TWILIO_NUMBER'),
                'body' => "🛍️ تطبيق اليك\n\nمرحباً بك! هذا اختبار لنظام الرسائل.\nشكراً لاختيارك اليك للتوصيل!"
            ]
        );
        
        return response()->json([
            'success' => true,
            'message' => 'تم إرسال الرسالة بنجاح!',
            'message_sid' => $message->sid
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

// ✅ PASSWORD RESET ROUTES
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// ✅ EMAIL VERIFICATION ROUTES
Route::post('/email/verification-notification', [AuthController::class, 'sendVerificationEmail']);
Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verify'])
    ->name('verification.verify');
Route::post('/check-verification-status', [AuthController::class, 'checkVerificationByEmail']);

// ✅ GOOGLE AUTH
Route::post('/auth/google', [AuthController::class, 'googleAuth']);

// ✅ TEST & DEBUG ROUTES
Route::get('/test', function () {
    return response()->json([
        'message' => 'API IS WORKING!',
        'app_url' => env('APP_URL'),
        'app_env' => env('APP_ENV'),
        'timestamp' => now(),
    ]);
});

Route::get('/health-check', [AuthController::class, 'healthCheck']);
Route::post('/test-email', [AuthController::class, 'testEmail']);

// ✅ DEBUG ROUTES (remove in production)
Route::get('/fix-user-statuses', [AuthController::class, 'fixUserStatuses']);
Route::post('/check-user', [AuthController::class, 'checkUser']);

// ✅ PUBLIC API ENDPOINTS
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::get('/stores', [StoreController::class, 'index']);
Route::get('/stores/{id}', [StoreController::class, 'show']);
Route::get('/stores/category/{categoryId}', [StoreController::class, 'getByCategory']);
Route::get('/products/store/{storeId}', [ProductController::class, 'getByStore']);
Route::get('/products/store/{storeId}/categories', [ProductController::class, 'getStoreCategories']);

// ✅ PUBLIC ADVERTISEMENTS ROUTE
Route::get('/advertisements/active', [AdvertisementController::class, 'getActiveAds']);

// -----------------------------
// Protected routes (auth:sanctum required)
// -----------------------------
Route::middleware('auth:sanctum')->group(function () {
    // ✅ CUSTOM ORDERS ROUTES
    Route::prefix('custom-orders')->group(function () {
        Route::post('/', [CustomOrderController::class, 'store']);
        Route::get('/', [CustomOrderController::class, 'index']);
        Route::get('/{id}', [CustomOrderController::class, 'show']);
        Route::post('/{id}/cancel', [CustomOrderController::class, 'cancel']);
    });
    
    // ✅ AUTH MANAGEMENT
    Route::put('/change-password', [AuthController::class, 'changePassword']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/email/verification-status', [AuthController::class, 'checkVerificationStatus']);

    // ========================================
    // 🚚 EMPLOYEE (DELIVERY DRIVER) ENDPOINTS
    // ========================================
    Route::prefix('employee')->group(function () {
        // Dashboard & Stats
        Route::get('/dashboard', [EmployerController::class, 'dashboard']);
        Route::get('/performance', [EmployerController::class, 'performanceStats']);

        // Order Management
        Route::get('/orders', [EmployerController::class, 'myOrders']);
        Route::get('/orders/active', [EmployerController::class, 'activeDelivery']);
        Route::get('/orders/available', [EmployerController::class, 'availableOrders']);
        Route::post('/orders/{orderId}/accept', [EmployerController::class, 'acceptOrder']);
        Route::put('/orders/{orderId}/status', [EmployerController::class, 'updateOrderStatus']);
        Route::post('/orders/{orderId}/deliver', [EmployerController::class, 'markAsDelivered']);

        // Delivery Tracking
        Route::post('/location', [EmployerController::class, 'updateLocation']);

        // History
        Route::get('/history', [EmployerController::class, 'deliveryHistory']);
    });

    // ========================================
    // 👨‍💼 ADMIN ENDPOINTS
    // ========================================
    Route::prefix('admin')->group(function () {

        // ✅ ADVERTISEMENT MANAGEMENT ROUTES - FIXED
        Route::prefix('advertisements')->group(function () {
            Route::get('/', [AdvertisementController::class, 'index']);
            Route::post('/', [AdvertisementController::class, 'store']);
            Route::get('/{id}', [AdvertisementController::class, 'show']);
            Route::put('/{id}', [AdvertisementController::class, 'update']); // ✅ FIXED: Changed to PUT
            Route::delete('/{id}', [AdvertisementController::class, 'destroy']);
            Route::post('/{id}/toggle-status', [AdvertisementController::class, 'toggleStatus']);
            Route::get('/stats/overview', [AdvertisementController::class, 'getStats']);
        });
        
        // Notifications
        Route::get('/notifications', [AdminController::class, 'getAdminNotifications']);
        
        // ✅ ADMIN REGISTRATION ROUTE (protected - only existing admins can create new admins)
        Route::post('/admin-register', [AuthController::class, 'adminRegister']);

        // User Management
        Route::get('/users', [AdminController::class, 'listUsers']);
        Route::get('/users/{id}', [AdminController::class, 'showUser']);
        Route::delete('/users/{id}', [AdminController::class, 'deleteUser']);
        Route::get('/users/new-registrations', [AdminController::class, 'newRegistrations']);

        // Order Management


        

        Route::get('/orders', [AdminOrderController::class, 'index']);
        Route::get('/orders/{id}', [AdminOrderController::class, 'show']);
        Route::put('/orders/{id}/assign', [AdminOrderController::class, 'assignEmployer']);
        Route::put('/orders/{id}/status', [AdminOrderController::class, 'updateStatus']);
        Route::delete('/orders/{id}', [AdminOrderController::class, 'destroy']);
        
        // Order Items Management
        Route::apiResource('order-items', OrderItemController::class);
        Route::get('orders/{orderId}/items', [OrderItemController::class, 'getByOrder']);
        Route::put('order-items/{id}/special-instructions', [OrderItemController::class, 'updateSpecialInstructions']);

        // Category Management
        Route::get('/categories', [CategoryController::class, 'index']);
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::put('/categories/{id}', [CategoryController::class, 'update']);
        Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

        // Store Management
        Route::get('/stores', [StoreController::class, 'index']);
        Route::get('/stores/{id}', [StoreController::class, 'show']);
        Route::post('/stores', [StoreController::class, 'store']);
        Route::put('/stores/{id}', [StoreController::class, 'update']);
        Route::delete('/stores/{id}', [StoreController::class, 'destroy']);
        Route::get('/stores/cities', [StoreController::class, 'getCities']);

        // Store Categories
        Route::post('/store-categories', [StoreController::class, 'addStoreCategory']);
        Route::put('/store-categories/{id}', [StoreController::class, 'updateStoreCategory']);
        Route::delete('/store-categories/{id}', [StoreController::class, 'deleteStoreCategory']);
        Route::get('/stores/{storeId}/categories', [StoreController::class, 'getCategories']);

        // Product Management
        Route::get('/products', [ProductController::class, 'index']);
        Route::post('/products', [ProductController::class, 'store']);
        Route::get('/products/{id}', [ProductController::class, 'show']);
        Route::put('/products/{id}', [ProductController::class, 'update']);
        Route::delete('/products/{id}', [ProductController::class, 'destroy']);

        // Employer (Driver) Management
        Route::prefix('employers')->group(function () {
            Route::get('/', [AdminEmployerController::class, 'index']);
            Route::get('/pending', [AdminEmployerController::class, 'getPendingEmployers']);
            Route::put('/{id}/status', [AdminEmployerController::class, 'updateStatus']);
            Route::post('/{id}/approve', [AdminEmployerController::class, 'approveEmployer']);
            Route::post('/{id}/reject', [AdminEmployerController::class, 'rejectEmployer']);
            Route::delete('/{id}', [AdminEmployerController::class, 'destroy']);
        });

        // Image Upload
        Route::post('/upload-image', function (Request $request) {
            $request->validate([
                'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);
            $path = $request->file('image')->store('stores', 'public');
            return response()->json(['path' => $path], 200);
        });
    });

    // ========================================
    // 👥 CUSTOMER ENDPOINTS
    // ========================================
 // ========================================
// 👥 CUSTOMER ENDPOINTS
// ========================================
Route::prefix('customer')->group(function () {
    // ✅ WATER TANK ORDERS ROUTES - CORRECTED
    Route::prefix('water-tank-orders')->group(function () {
        Route::post('/', [WaterTankOrderController::class, 'store']);
        Route::get('/', [WaterTankOrderController::class, 'index']);
        Route::get('/{id}', [WaterTankOrderController::class, 'show']);
        Route::post('/{id}/cancel', [WaterTankOrderController::class, 'cancel']);
        Route::get('/stats/overview', [WaterTankOrderController::class, 'stats']);
    });

    // Orders
    Route::post('/orders', [CustomerOrderController::class, 'store']);
    Route::get('/orders', [CustomerOrderController::class, 'index']);
    Route::get('/orders/{id}', [CustomerOrderController::class, 'show']);
    Route::patch('/orders/{id}/status', [CustomerOrderController::class, 'updateStatus']);
    Route::post('/orders/{id}/cancel', [CustomerOrderController::class, 'cancelOrder']);
    Route::post('/orders/{id}/rate', [CustomerOrderController::class, 'rateOrder']);

    // Cart
    Route::post('/cart/add', [CartController::class, 'addToCart']);
    Route::get('/cart', [CartController::class, 'getCart']);
    Route::delete('/cart', [CartController::class, 'clearCart']);
    Route::delete('/cart/{productId}', [CartController::class, 'removeFromCart']);
    Route::put('/cart/{productId}/increase', [CartController::class, 'increaseQuantity']);
    Route::put('/cart/{productId}/decrease', [CartController::class, 'decreaseQuantity']);
    
    // ✅ ADD ORDER STATS
    Route::get('/orders/stats', [CustomerOrderController::class, 'getStats']);
});
    // ========================================
    // 🔐 USER PROFILE
    // ========================================
    Route::prefix('user')->group(function () {
        Route::get('/', [UserController::class, 'show']);
        Route::match(['post','put'], '/', [UserController::class, 'update']);
        Route::put('/location', [UserController::class, 'updateLocation']);
        Route::put('/notification-token', [UserController::class, 'updateNotificationToken']);
        Route::delete('/', [UserController::class, 'destroy']);
    });

    // ========================================
    // 🔔 NOTIFICATIONS
    // ========================================
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/mark-read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);

    // ========================================
    // 📱 APP SETTINGS & CONFIG
    // ========================================
    Route::get('/app-config', function () {
        return response()->json([
            'app_name' => config('app.name'),
            'app_version' => '1.0.0',
            'min_app_version' => '1.0.0',
            'maintenance_mode' => false,
            'features' => [
                'online_payments' => true,
                'grocery_ordering' => true,
                'real_time_tracking' => true,
                'email_verification' => true,
                'employer_registration' => true,
                'sms_verification' => true,
                'advertisements' => true,
            ]
        ]);
    });
});

// Debug route to check advertisements
Route::get('/debug-ads', function() {
    try {
        $city = request()->header('X-City') ?? 'Sana\'a';
        
        // Check all ads without any conditions
        $allAds = \App\Models\Advertisement::all();
        
        // Check active ads with conditions
        $activeAds = \App\Models\Advertisement::active()->get();
        
        // Check ads for specific city
        $cityAds = \App\Models\Advertisement::active()
            ->forCity($city)
            ->get();
            
        return response()->json([
            'debug_info' => [
                'requested_city' => $city,
                'total_advertisements' => $allAds->count(),
                'active_advertisements' => $activeAds->count(),
                'city_targeted_ads' => $cityAds->count(),
            ],
            'all_ads' => $allAds->map(function($ad) {
                return [
                    'id' => $ad->id,
                    'title' => $ad->title,
                    'is_active' => $ad->is_active,
                    'start_date' => $ad->start_date,
                    'end_date' => $ad->end_date,
                    'target_cities' => $ad->target_cities,
                    'priority' => $ad->priority,
                    'created_at' => $ad->created_at,
                ];
            }),
            'active_ads' => $activeAds->map(function($ad) {
                return [
                    'id' => $ad->id,
                    'title' => $ad->title,
                    'is_active' => $ad->is_active,
                ];
            }),
            'city_ads' => $cityAds->map(function($ad) {
                return [
                    'id' => $ad->id,
                    'title' => $ad->title,
                ];
            })
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});

// -----------------------------
// Fallback route for undefined endpoints
// -----------------------------
Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'Endpoint not found. Please check the API documentation.',
        'available_endpoints' => [
            'public' => [
                'POST /login',
                'POST /register',
                'POST /employer-register',
                'POST /send-verification-sms',
                'POST /verify-sms-code',
                'POST /resend-verification-sms',
                'GET /test-verification-system',
                'GET /test-real-sms',
                'GET /system-stats',
                'GET /test-sms-elyak',
                'POST /forgot-password',
                'POST /auth/google',
                'GET /categories',
                'GET /products',
                'GET /stores',
                'GET /advertisements/active',
                'GET /test',
                'GET /health-check'
            ],
            'protected' => [
                'GET /user',
                'PUT /change-password',
                'POST /logout',
                'POST /customer/orders',
                'GET /customer/orders',
                'GET /employee/orders',
                'GET /admin/orders',
                'POST /custom-orders',
                'GET /custom-orders',
                'GET /employee/dashboard',
                'GET /admin/users',
                'GET /admin/advertisements',
                'POST /admin/advertisements',
                'POST /admin/advertisements/{id}', // ✅ FIXED: Now using POST for updates
                'DELETE /admin/advertisements/{id}',
                'POST /admin/advertisements/{id}/toggle-status',
                'GET /admin/advertisements/stats/overview',
                'GET /notifications'
            ]
        ]
    ], 404);
});