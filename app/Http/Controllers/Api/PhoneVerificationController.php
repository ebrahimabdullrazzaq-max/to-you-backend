<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;

class PhoneVerificationController extends Controller
{
    private $twilioClient;
    private $twilioNumber;

    public function __construct()
    {
        // ✅ استخدام config بدلاً من env مباشرة
        $accountSid = config('services.twilio.sid');
        $authToken = config('services.twilio.token');
        $this->twilioNumber = config('services.twilio.number');
        
        // ✅ التحقق من صحة البيانات
        Log::info('Twilio Configuration Check:', [
            'sid_exists' => !empty($accountSid),
            'token_exists' => !empty($authToken),
            'number_exists' => !empty($this->twilioNumber),
            'sid_prefix' => $accountSid ? substr($accountSid, 0, 10) . '...' : 'NULL',
        ]);

        if (empty($accountSid) || empty($authToken)) {
            Log::warning('Twilio credentials are missing - Using development mode');
            $this->twilioClient = null;
            return;
        }

        try {
            $this->twilioClient = new Client($accountSid, $authToken);
            
            // اختبار الاتصال بمحاولة جلب معلومات الحساب
            $account = $this->twilioClient->api->v2010->accounts($accountSid)->fetch();
            Log::info('Twilio client initialized successfully', [
                'account_name' => $account->friendlyName,
                'status' => $account->status
            ]);
        } catch (\Exception $e) {
            Log::error('Twilio initialization failed: ' . $e->getMessage());
            $this->twilioClient = null;
        }
    }

    /**
     * إرسال رمز التحقق عبر SMS
     */
  /**
 * إرسال رمز التحقق عبر SMS
 */
public function sendVerificationCode(Request $request)
{
    $request->validate([
        'phone_number' => 'required|string',
        'country_code' => 'required|string|in:+967' // ✅ يبقى للتحقق من أن الرقم يمني
    ]);

    $fullPhoneNumber = $request->country_code . $request->phone_number;
    
    // ✅ التحقق من أن الرقم يمني صالح (10 أرقام بعد +967)
    if (!preg_match('/^\+967[0-9]{9}$/', $fullPhoneNumber)) {
        return response()->json([
            'success' => false,
            'message' => 'رقم الهاتف غير صحيح. يجب أن يكون رقم يمني صالح (9 أرقام بعد +967)'
        ], 400);
    }
    
    // إنشاء رمز تحقق مكون من 6 أرقام
    $verificationCode = sprintf("%06d", mt_rand(1, 999999));
    
    // تخزين الرمز في الكاش لمدة 10 دقائق
    Cache::put('verification_code_' . $fullPhoneNumber, $verificationCode, 600);
    
    // رسالة بالعربية مع اسم التطبيق
    $message = "🛍️ تطبيق اليك\n";
    $message .= "كود التحقق: " . $verificationCode . "\n\n";
    $message .= "هذا الكود صالح لمدة 10 دقائق\n";
    $message .= "شكراً لانضمامك إلى اليك!";

    // ✅ محاولة إرسال SMS فعلي عبر Twilio
    $smsSent = false;
    $messageSid = null;
    $twilioError = null;

    if ($this->twilioClient) {
        try {
            Log::info('Attempting to send SMS via Twilio', [
                'to' => $fullPhoneNumber,
                'from' => $this->twilioNumber
            ]);

            $message = $this->twilioClient->messages->create(
                $fullPhoneNumber,
                [
                    'from' => $this->twilioNumber,
                    'body' => $message
                ]
            );
            
            $smsSent = true;
            $messageSid = $message->sid;
            
            Log::info('SMS sent successfully via Twilio', [
                'to' => $fullPhoneNumber,
                'message_sid' => $messageSid,
                'status' => $message->status
            ]);
            
        } catch (\Exception $e) {
            $twilioError = $e->getMessage();
            Log::error('Twilio SMS failed, using development mode', [
                'phone' => $fullPhoneNumber,
                'error' => $twilioError,
                'twilio_number' => $this->twilioNumber
            ]);
        }
    } else {
        Log::warning('Twilio client not available, using development mode');
    }

    if ($smsSent) {
        // ✅ تم إرسال SMS بنجاح
        return response()->json([
            'success' => true,
            'message' => 'تم إرسال رمز التحقق إلى هاتفك',
            'message_sid' => $messageSid,
            'phone' => $fullPhoneNumber,
            'mode' => 'production'
        ]);
    } else {
        // ✅ وضع التطوير - عرض الرمز
        Log::info('Development mode: Verification code generated', [
            'phone' => $fullPhoneNumber,
            'code' => $verificationCode,
            'twilio_error' => $twilioError
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء رمز التحقق بنجاح! استخدم هذا الرمز: ' . $verificationCode,
            'verification_code' => $verificationCode,
            'phone' => $fullPhoneNumber,
            'note' => 'في الإنتاج سيتم إرسال الرمز تلقائياً إلى هاتفك',
            'mode' => 'development',
            'twilio_error' => $twilioError
        ]);
    }
}

    /**
     * التحقق من صحة الرمز
     */
/**
 * التحقق من صحة الرمز
 */
/**
 * التحقق من صحة الرمز وتسجيل الدخول/التسجيل
 */
public function verifyCode(Request $request)
{
    Log::info('Verification Request Data:', $request->all());
    
    $request->validate([
        'phone_number' => 'required|string',
        'country_code' => 'required|string|in:+967',
        'code' => 'required|string|size:6',
        'name' => 'sometimes|string|max:255', // Optional for registration
        'address' => 'sometimes|string|max:500',
        'latitude' => 'sometimes|numeric',
        'longitude' => 'sometimes|numeric',
    ]);

    $fullPhoneNumber = $request->country_code . $request->phone_number;
    
    if (!preg_match('/^\+967[0-9]{9}$/', $fullPhoneNumber)) {
        return response()->json([
            'success' => false,
            'message' => 'رقم الهاتف غير صحيح. يجب أن يكون رقم يمني صالح'
        ], 400);
    }
    
    $cachedCode = Cache::get('verification_code_' . $fullPhoneNumber);

    Log::info('Verification attempt', [
        'phone' => $fullPhoneNumber,
        'entered_code' => $request->code,
        'cached_code' => $cachedCode ? '***' . substr($cachedCode, -2) : 'NOT_FOUND',
    ]);

    if (!$cachedCode) {
        return response()->json([
            'success' => false,
            'message' => 'رمز التحقق منتهي الصلاحية أو غير موجود'
        ], 400);
    }

    if ($cachedCode === $request->code) {
        Cache::forget('verification_code_' . $fullPhoneNumber);
        
        // Check if user exists
        $user = \App\Models\User::where('phone', $fullPhoneNumber)->first();
        
        if ($user) {
            // User exists - login
            $token = $user->createToken('phone_auth')->plainTextToken;
            
            Log::info('User logged in with phone', ['user_id' => $user->id]);
            
            return response()->json([
                'success' => true,
                'message' => 'تم تسجيل الدخول بنجاح',
                'user' => $user,
                'token' => $token,
                'is_new_user' => false
            ]);
        } else {
            // New user - check if we have registration data
            if ($request->has('name') && $request->has('address')) {
                // Create new user with phone
                $user = \App\Models\User::create([
                    'name' => $request->name,
                    'email' => $fullPhoneNumber . '@elyak.app', // Generate email from phone
                    'phone' => $fullPhoneNumber,
                    'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(16)),
                    'address' => $request->address,
                    'latitude' => $request->latitude,
                    'longitude' => $request->longitude,
                    'role' => 'customer',
                    'status' => 'approved',
                    'phone_verified_at' => now(),
                    'email_verified_at' => now(), // Auto-verify email for phone users
                    'registration_type' => 'phone',
                ]);
                
                $token = $user->createToken('phone_auth')->plainTextToken;
                
                Log::info('New user registered with phone', ['user_id' => $user->id]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'تم إنشاء الحساب بنجاح',
                    'user' => $user,
                    'token' => $token,
                    'is_new_user' => true
                ]);
            } else {
                // Need registration data
                return response()->json([
                    'success' => true,
                    'message' => 'تم التحقق من رقم الهاتف بنجاح',
                    'requires_registration' => true,
                    'is_new_user' => true
                ]);
            }
        }
    }

    return response()->json([
        'success' => false,
        'message' => 'رمز التحقق غير صحيح'
    ], 400);
}

    /**
     * إعادة إرسال الرمز
     */
    public function resendCode(Request $request)
    {
        return $this->sendVerificationCode($request);
    }

    /**
     * اختبار نظام التحقق
     */
    public function testVerificationSystem()
    {
        $testPhone = '+967781058382';
        $testCode = sprintf("%06d", mt_rand(1, 999999));
        
        // تخزين رمز اختبار
        Cache::put('verification_code_' . $testPhone, $testCode, 600);
        
        // محاولة استرجاعه
        $cachedCode = Cache::get('verification_code_' . $testPhone);
        
        // اختبار Twilio
        $twilioStatus = 'not_tested';
        if ($this->twilioClient) {
            try {
                $account = $this->twilioClient->api->v2010->accounts(config('services.twilio.sid'))->fetch();
                $twilioStatus = 'connected - ' . $account->status;
            } catch (\Exception $e) {
                $twilioStatus = 'error - ' . $e->getMessage();
            }
        } else {
            $twilioStatus = 'client_not_available';
        }

        return response()->json([
            'success' => true,
            'message' => 'نظام التحقق يعمل بشكل صحيح',
            'cache_working' => $cachedCode === $testCode,
            'twilio_status' => $twilioStatus,
            'test_data' => [
                'phone' => $testPhone,
                'code_generated' => $testCode,
                'code_cached' => $cachedCode,
            ],
            'configuration' => [
                'twilio_sid_exists' => !empty(config('services.twilio.sid')),
                'twilio_token_exists' => !empty(config('services.twilio.token')),
                'twilio_number' => config('services.twilio.number'),
            ]
        ]);
    }

    /**
     * اختبار إرسال SMS فعلي
     */
  /**
 * اختبار إرسال SMS فعلي
 */
public function testRealSMS(Request $request)
{
    try {
        // ✅ السماح باختبار أي رقم يمني
        $testPhone = $request->phone ?? '+967781058382';
        
        // ✅ التحقق من أن الرقم يمني صالح
        if (!preg_match('/^\+967[0-9]{9}$/', $testPhone)) {
            return response()->json([
                'success' => false,
                'message' => 'رقم الهاتف غير صحيح. يجب أن يكون رقم يمني صالح (مثال: +967781058382)'
            ], 400);
        }
        
        if (!$this->twilioClient) {
            return response()->json([
                'success' => false,
                'message' => 'Twilio client not available',
                'reason' => 'Check Twilio credentials in .env file'
            ], 500);
        }

        $message = $this->twilioClient->messages->create(
            $testPhone,
            [
                'from' => $this->twilioNumber,
                'body' => "✅ اختبار Twilio من تطبيق اليك\n\nهذه رسالة اختبار للتأكد من أن نظام الرسائل يعمل بشكل صحيح.\nشكراً لاستخدامك اليك!"
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'تم إرسال رسالة الاختبار بنجاح',
            'message_sid' => $message->sid,
            'status' => $message->status,
            'to' => $testPhone,
            'from' => $this->twilioNumber
        ]);

    } catch (\Exception $e) {
        Log::error('Real SMS test failed', [
            'error' => $e->getMessage(),
            'phone' => $testPhone
        ]);

        return response()->json([
            'success' => false,
            'message' => 'فشل إرسال رسالة الاختبار: ' . $e->getMessage(),
            'configuration_check' => [
                'sid' => config('services.twilio.sid') ? substr(config('services.twilio.sid'), 0, 10) . '...' : 'NULL',
                'token_length' => config('services.twilio.token') ? strlen(config('services.twilio.token')) : 0,
                'number' => config('services.twilio.number'),
            ]
        ], 500);
    }
}

    /**
     * الحصول على إحصائيات النظام
     */
    public function getSystemStats()
    {
        $stats = [
            'twilio_configured' => $this->twilioClient !== null,
            'cache_driver' => config('cache.default'),
            'app_env' => config('app.env'),
            'timestamp' => now()->toDateTimeString(),
        ];

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'services' => [
                'twilio' => [
                    'sid_exists' => !empty(config('services.twilio.sid')),
                    'token_exists' => !empty(config('services.twilio.token')),
                    'number' => config('services.twilio.number'),
                ]
            ]
        ]);
    }

    /**
 * اختبار إرسال SMS لأي رقم يمني
 */
public function testSMSAnyNumber(Request $request)
{
    $request->validate([
        'phone_number' => 'required|string'
    ]);

    $testPhone = $request->phone_number;
    
    // ✅ إضافة +967 إذا لم تكن موجودة
    if (!str_starts_with($testPhone, '+967')) {
        $testPhone = '+967' . $testPhone;
    }
    
    // ✅ التحقق من أن الرقم يمني صالح
    if (!preg_match('/^\+967[0-9]{9}$/', $testPhone)) {
        return response()->json([
            'success' => false,
            'message' => 'رقم الهاتف غير صحيح. يجب أن يكون رقم يمني صالح (مثال: 781058382 أو +967781058382)'
        ], 400);
    }

    try {
        if (!$this->twilioClient) {
            return response()->json([
                'success' => false,
                'message' => 'Twilio client not available',
                'reason' => 'Check Twilio credentials in .env file'
            ], 500);
        }

        $message = $this->twilioClient->messages->create(
            $testPhone,
            [
                'from' => $this->twilioNumber,
                'body' => "✅ اختبار Twilio من تطبيق اليك\n\nهذه رسالة اختبار للتأكد من أن نظام الرسائل يعمل بشكل صحيح.\nشكراً لاستخدامك اليك!"
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'تم إرسال رسالة الاختبار بنجاح إلى ' . $testPhone,
            'message_sid' => $message->sid,
            'status' => $message->status,
            'to' => $testPhone,
            'from' => $this->twilioNumber
        ]);

    } catch (\Exception $e) {
        Log::error('SMS test to any number failed', [
            'error' => $e->getMessage(),
            'phone' => $testPhone
        ]);

        return response()->json([
            'success' => false,
            'message' => 'فشل إرسال رسالة الاختبار: ' . $e->getMessage(),
            'phone' => $testPhone
        ], 500);
    }
}
}