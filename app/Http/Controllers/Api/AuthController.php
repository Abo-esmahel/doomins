<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Mail\VerificationCodeMail;
use App\Mail\VerificationCodePassword;
use App\Mail\WelcomeNewUser;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
class AuthController extends Controller
{

    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone??null,
                'password' => Hash::make($request->password),
                'role' => $request->role ?? 'user',
                'balance' => 0.00,
                'status' => 'inactive',
            ]);

            $r = new Request(['email' => $request->email]);
            $code = $this->sendActivationCode($r);

if ($code['success'] === false) {
                return response()->json([
                    'status' => 'error',
                    'message' => $code['message'],
                ], 500);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'يرجى تفعيل الحساب ,تم تسجيل الحساب بنجاح',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'role' => $user->role,
                        'balance' => $user->balance,
                        'created_at' => $user->created_at->format('Y-m-d H:i:s'),
                    ],
                    'code' => $code['token'] ?? null,
                    'token_type' => 'Bearer',
                    'expires_in' => '7 days'
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'حدث خطأ أثناء التسجيل',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }


    public function logout(): JsonResponse
    {
        try {
            $user = auth('sanctum')->user();
            $user->tokens()->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'تم تسجيل الخروج بنجاح'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'حدث خطأ أثناء تسجيل الخروج',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }   

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        try {

        if (! $user || ! Hash::check($request->password, $user->password)) {
         throw ValidationException::withMessages([
        'email' => ['بيانات الاعتماد غير صحيحة'],
    ]);
                     }






                $token = $user->createToken('auth_token')->plainTextToken;
          Mail::to($request->email)->queue(new WelcomeNewUser($user));

            return response()->json([
                'status' => 'success',
                'message' => 'تم تسجيل الدخول بنجاح',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'role' => $user->role,
                        'balance' => $user->balance,
                    ],
                    'token' => $token,
                    'token_type' => 'Bearer',
                    'expires_in' => 604800,
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'بيانات الاعتماد غير صحيحة',
                'errors' => $e->errors()
            ], 401);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'حدث خطأ أثناء تسجيل الدخول',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }




    /**
     * تغيير كلمة المرور
     *
     * @param ChangePasswordRequest $request
     * @return JsonResponse
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        try {
            $user = $request->user();

            // التحقق من كلمة المرور الحالية
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'كلمة المرور الحالية غير صحيحة'
                ], 422);
            }

            // تحديث كلمة المرور
            $user->update([
                'password' => Hash::make($request->new_password)
            ]);

            // حذف جميع التوكنات لإجبار إعادة تسجيل الدخول
            $user->tokens()->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'تم تغيير كلمة المرور بنجاح. يرجى تسجيل الدخول مرة أخرى'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'حدث خطأ أثناء تغيير كلمة المرور',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * إرسال رابط إعادة تعيين كلمة المرور
     *
     * @param ForgotPasswordRequest $request
     * @return JsonResponse
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        try {
            $status = Password::sendResetLink(
                $request->only('email')
            );

            if ($status === Password::RESET_LINK_SENT) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'تم إرسال رابط إعادة تعيين كلمة المرور إلى بريدك الإلكتروني'
                ]);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'لم نتمكن من إرسال رابط إعادة التعيين'
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'حدث خطأ أثناء إرسال رابط إعادة التعيين',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * إعادة تعيين كلمة المرورثض
     *
     * @param ResetPasswordRequest $request
     * @return JsonResponse
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        try {
            $status = Password::reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function (User $user, string $password) {
                    $user->forceFill([
                        'password' => Hash::make($password)
                    ])->setRememberToken(Str::random(60));

                    $user->save();

                    event(new PasswordReset($user));
                }
            );

            if ($status === Password::PASSWORD_RESET) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'تم إعادة تعيين كلمة المرور بنجاح'
                ]);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'فشل إعادة تعيين كلمة المرور',
                'errors' => ['token' => 'رابط إعادة التعيين غير صالح أو منتهي الصلاحية']
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'حدث خطأ أثناء إعادة تعيين كلمة المرور',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }


   public function sendPasswordResetCode(string $email): array
    {
        try {
            $user = User::where('email', $email)->first();

            if (! $user) {
                return ['success' => false, 'message' => 'البريد الإلكتروني غير مسجل'];
            }

            $sentKey = "pwd_reset_sent:{$email}";
            $countKey = "pwd_reset_count:{$email}";
            $blockKey = "pwd_reset_block:{$email}";

            if (Cache::has($blockKey)) {
                return ['success' => false, 'message' => 'ممنوع طلب رموز الآن. حاول لاحقاً.'];
            }

            if (Cache::has($sentKey)) {
                return ['success' => false, 'message' => 'يرجى الانتظار قبل طلب رمز جديد'];
            }

            $count = Cache::get($countKey, 0);
            if ($count >= 10) {
                Cache::put($blockKey, true, now()->addMinutes(30));
                return ['success' => false, 'message' => 'تم تجاوز حد طلب الرموز، حاول لاحقاً'];
            }

            try {
                $otp_code = str_pad(random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
            } catch (\Throwable $e) {
                $otp_code = str_pad(rand(0, 99999999), 8, '0', STR_PAD_LEFT);
            }

            DB::table('password_reset_tokens')->where('email', $email)->delete();

            $insertData = [
                'email' => $email,
                'token' => Hash::make($otp_code),
                'created_at' => now(),
                'expires_at' => now()->addMinutes(15),

            ];




            DB::table('password_reset_tokens')->insert($insertData);

            try {
                Mail::to($email)->queue(new VerificationCodePassword($user, $otp_code));
            } catch (\Throwable $e) {
                Log::error("Mail Error (sendPasswordResetCode): " . $e->getMessage());
            }

            Cache::put($sentKey, true, now()->addSeconds(60));
            Cache::put($countKey, $count + 1, now()->addHour());

            return ['success' => true, 'message' => 'تم إرسال رمز استعادة كلمة المرور','token_reset' => $otp_code ];
        } catch (Exception $e) {
            Log::error("sendPasswordResetCode Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'حدث خطأ، حاول لاحقاً'];
        }
    }


 public function resetUserPassword(Request $request): array

 {
    try {
        $email = $request->input('email');
        $code = $request->input('code');
        $newPassword = $request->input('new_password');
      if (empty($email) || empty($code) || empty($newPassword)) {
            return ['success' => false, 'message' => 'جميع الحقول مطلوبة'];
        }
        $attemptsKey = "pwd_reset_attempts:{$email}";
        $lastAttemptKey = "pwd_reset_last_attempt:{$email}";
        $blockKey = "pwd_reset_block:{$email}";

        if (Cache::has($blockKey)) {
            return ['success' => false, 'message' => 'تم حظر المحاولات مؤقتاً. حاول لاحقاً'];
        }

        $reset = DB::table('password_reset_tokens')
        ->where('email', $email)
        ->first();

        if (! $reset) {
            return ['success' => false, 'message' => 'لا يوجد طلب استعادة كلمة مرور لهذا البريد'];
        }

         $isExpired = now()->greaterThan(Carbon::parse($reset->expires_at));


        if ($isExpired) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            Cache::forget($attemptsKey);
            Cache::forget($lastAttemptKey);
            return ['success' => false, 'message' => 'انتهت صلاحية الرمز. يرجى طلب رمز جديد'];
        }

        if (Cache::has($lastAttemptKey)) {
            $secondsSince = now()->diffInSeconds(Carbon::parse(Cache::get($lastAttemptKey)));
            if ($secondsSince < 10) {
                return ['success' => false, 'message' => 'محاولة سريعة جداً. انتظر قليلاً ثم حاول مرة أخرى'];
            }
        }

        if (!Hash::check($code, $reset->token)) {
            $attempts = Cache::get($attemptsKey, 0) + 1;
            Cache::put($attemptsKey, $attempts, now()->addMinutes(30));
            Cache::put($lastAttemptKey, now(), now()->addMinutes(30));

            if ($attempts >= 5) {
                DB::table('password_reset_tokens')->where('email', $email)->delete();
                Cache::put($blockKey, true, now()->addMinutes(15));
                Cache::forget($attemptsKey);
                Cache::forget($lastAttemptKey);
                return [
                    'success' => false,
                    'message' => 'تم تجاوز عدد المحاولات. يرجى طلب رمز جديد لاحقاً.'
                ];
            }

            return ['success' => false, 'message' => 'رمز غير صحيح'];
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            return ['success' => false, 'message' => 'المستخدم غير موجود'];
        }

        $user->update([
            'password' => Hash::make($newPassword)
        ]);

        DB::table('password_reset_tokens')->where('email', $email)->delete();
        Cache::forget($attemptsKey);
        Cache::forget($lastAttemptKey);
        Cache::forget("pwd_reset_count:{$email}");
        Cache::forget("pwd_reset_sent:{$email}");
        Cache::forget($blockKey);

        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;
        return [
            'success' => true,
            'message' => 'تم تغيير كلمة المرور بنجاح',
            'token' => $token,
        ];
    } catch (Exception $e) {
        Log::error("resetUserPassword Error: " . $e->getMessage());
        return ['success' => false, 'message' => 'حدث خطأ أثناء إعادة تعيين كلمة المرور'];
    }
 }

    /* ===================== VERIFY ACCOUNT ===================== */



public function sendActivationCode(Request $request): array
{
    $email = $request->input('email');

    try {
        $user = User::where('email', $email)
                    ->where('hasVerifiedEmail', false)
                    ->first();

        if (!$user) {
            return [
                'success' => false,
                'message' => 'المستخدم غير موجود أو مفعل بالفعل'
            ];
        }

        $plainCode = rand(10000000, 99999999);
        $hashedCode = Hash::make($plainCode);

        $user->update([
            'verification_token' => $hashedCode,
            'verification_token_expires_at' => now()->addMinutes(15),
        ]);

        Mail::to($user->email)->queue(new VerificationCodeMail($user, $plainCode));

        return [
            'success' => true,
            'message' => 'تم إرسال رمز التفعيل الجديد بنجاح',
            'token' => $plainCode,
            'expires_at' => $user->verification_token_expires_at->format('Y-m-d H:i:s'),
        ];

    } catch (\Exception $e) {
        Log::error("sendActivationCode Error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'حدث خطأ أثناء إعادة إرسال رمز التفعيل'
        ];
    }
}

public function ActivateAccount(Request $request): array
{
    $email = $request->input('email');
    $otp_code = $request->input('otp_code');

    $user = User::where('email', $email)
        ->where('status', 'inactive')
        ->first();

    if (!$user) {
        return ['success' => false, 'message' => 'رمز غير صالح أو الحساب مفعل مسبقاً'];
    }

    if (!$user->verification_token) {
        return ['success' => false, 'message' => 'لا يوجد رمز تفعيل'];
    }

    if (!Hash::check($otp_code, $user->verification_token)) {
        return ['success' => false, 'message' => 'كود التحقق غير صحيح'];
    }

    if (!$user->verification_token_expires_at || Carbon::now()->gt($user->verification_token_expires_at)) {
        $user->update([
            'verification_token' => null,
            'verification_token_expires_at' => null,
        ]);

        return ['success' => false, 'message' => 'انتهت صلاحية رمز التفعيل، يرجى طلب رمز جديد'];
    }

    try {
        Mail::to($user->email)->queue(new WelcomeNewUser($user));
    } catch (\Exception $e) {
        Log::error("Mail Error: " . $e->getMessage());
    }

    // تفعيل الحساب
    $user->update([
        'email_verified_at' => now(),
        'verification_token' => null,
        'verification_token_expires_at' => null,
        'hasVerifiedEmail' => true,
        'status' => 'active',
        'is_active' => true, // أضف هذا أيضاً
    ]);

    return [
        'success' => true,
        'message' => 'تم تفعيل الحساب بنجاح',
    ];
}

    public function verifyEmail(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if ($user->hasVerifiedEmail()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'البريد الإلكتروني تم التحقق منه'
                ]);
            }


            return response()->json([
                'status' => 'false',
                'message' => 'لم يتم التحقق منه'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'حدث خطأ أثناء التحقق من البريد الإلكتروني',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }


    public function checkAuth(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'غير مصادق',
                    'authenticated' => false
                ], 401);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'مصادق',
                'authenticated' => true,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'حدث خطأ أثناء التحقق من المصادقة',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
