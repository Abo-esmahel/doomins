<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Order;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function __construct()
    {
    }

    /**
     * عرض جميع المستخدمين
     */
    public function index(Request $request)
    {
        try {
            $query = User::withCount(['orders', 'transactions'])
                ->latest();

            // البحث
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            }

            // التصفية حسب الصلاحية
            if ($request->has('role')) {
                $query->where('role', $request->role);
            }

            // التصفية حسب الحالة
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // التصفية حسب البريد المؤكد
            if ($request->has('email_verified')) {
                $query->where('hasVerifiedEmail', $request->boolean('email_verified'));
            }

            // التصفية حسب الرصيد
            if ($request->has('min_balance')) {
                $query->where('balance', '>=', $request->min_balance);
            }

            if ($request->has('max_balance')) {
                $query->where('balance', '<=', $request->max_balance);
            }

            // الباجينيت
            $perPage = $request->per_page ?? 20;
            $users = $query->paginate($perPage);

            // الإحصائيات
            $stats = [
                'total' => User::count(),
                'active' => User::where('status', 'active')->count(),
                'inactive' => User::where('status', 'inactive')->count(),
                'blocked' => User::where('status', 'blocked')->count(),
                'admins' => User::whereIn('role', ['admin', 'superadmin'])->count(),
                'total_balance' => User::sum('balance'),
                'verified_emails' => User::where('hasVerifiedEmail', true)->count()
            ];

            // توزيع المستخدمين حسب الصلاحيات
            $roleDistribution = User::select('role')
                ->selectRaw('COUNT(*) as count')
                ->groupBy('role')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $users,
                'stats' => $stats,
                'role_distribution' => $roleDistribution,
                'filters' => $request->only(['search', 'role', 'status', 'email_verified'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في جلب المستخدمين',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * عرض مستخدم معين
     */
    public function show($id)
    {
        try {
            $user = User::withCount(['orders', 'transactions', 'invoices'])
                ->findOrFail($id);

            // الطلبات الحديثة
            $recentOrders = $user->orders()
                ->with('items.product')
                ->latest()
                ->limit(10)
                ->get();

            // المعاملات الحديثة
            $recentTransactions = $user->transactions()
                ->latest()
                ->limit(10)
                ->get();

            // الخدمات النشطة
            $activeServices = $user->orderItems()
                ->whereHas('order', function($q) {
                    $q->whereIn('status', ['paid', 'completed']);
                })
                ->where('status', 'active')
                ->with('product.productable')
                ->latest()
                ->limit(10)
                ->get();

            // الإحصائيات
            $stats = [
                'total_orders' => $user->orders()->count(),
                'total_spent' => $user->orders()->where('payment_status', 'paid')->sum('total_amount'),
                'active_services' => $activeServices->count(),
                'pending_orders' => $user->orders()->whereIn('status', ['pending', 'processing'])->count(),
                'completed_orders' => $user->orders()->where('status', 'completed')->count(),
                'cancelled_orders' => $user->orders()->where('status', 'cancelled')->count(),
                'total_invoices' => $user->invoices()->count(),
                'unpaid_invoices' => $user->invoices()->where('status', 'pending')->count()
            ];

            return response()->json([
                'success' => true,
                'data' => $user,
                'recent_orders' => $recentOrders,
                'recent_transactions' => $recentTransactions,
                'active_services' => $activeServices,
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم العثور على المستخدم'
            ], 404);
        }
    }

    /**
     * إنشاء مستخدم جديد
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:user,admin,superadmin',
            'balance' => 'nullable|numeric|min:0',
            'status' => 'in:active,inactive,blocked'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            // إنشاء المستخدم
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'role' => $request->role,
                'balance' => $request->balance ?? 0,
                'status' => $request->status ?? 'active',
                'hasVerifiedEmail' => true,
                'email_verified_at' => now()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء المستخدم بنجاح',
                'data' => $user
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في إنشاء المستخدم',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * تحديث المستخدم
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'phone' => 'nullable|string|max:20',
            'role' => 'sometimes|in:user,admin,superadmin',
            'balance' => 'nullable|numeric|min:0',
            'status' => 'sometimes|in:active,inactive,blocked',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $oldRole = $user->role;
            $oldStatus = $user->status;
            $oldBalance = $user->balance;

            // تجميع التحديثات
            $updates = [];

            if ($request->has('name')) {
                $updates['name'] = $request->name;
            }

            if ($request->has('email')) {
                $updates['email'] = $request->email;
            }

            if ($request->has('phone')) {
                $updates['phone'] = $request->phone;
            }

            if ($request->has('role')) {
                $updates['role'] = $request->role;
            }

            if ($request->has('balance')) {
                $updates['balance'] = $request->balance;
            }

            if ($request->has('status')) {
                $updates['status'] = $request->status;
            }

            if ($request->has('notes')) {
                $updates['notes'] = $request->notes;
            }

            // تطبيق التحديثات
            $user->update($updates);

            // تسجيل تغيير الرصيد
            if ($request->has('balance') && $request->balance != $oldBalance) {
                $difference = $request->balance - $oldBalance;

                Transaction::create([
                    'user_id' => $user->id,
                    'type' => $difference > 0 ? 'deposit' : 'withdrawal',
                    'amount' => abs($difference),
                    'status' => 'completed',
                    'gateway' => 'manual',
                    'description' => 'تعديل يدوي للرصيد من قبل الإدارة',
                    'notes' => $request->notes ?? 'تعديل الرصيد'
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث المستخدم بنجاح',
                'data' => $user,
                'changes' => [
                    'role_change' => $oldRole !== $user->role ?
                        ['from' => $oldRole, 'to' => $user->role] : null,
                    'status_change' => $oldStatus !== $user->status ?
                        ['from' => $oldStatus, 'to' => $user->status] : null,
                    'balance_change' => $oldBalance != $user->balance ?
                        ['from' => $oldBalance, 'to' => $user->balance, 'difference' => $user->balance - $oldBalance] : null
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في تحديث المستخدم',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * حذف المستخدم
     */
    public function destroy($id)
    {
        DB::beginTransaction();

        try {
            $user = User::findOrFail($id);

            // التحقق من عدم وجود طلبات نشطة
            $hasActiveOrders = $user->orders()
                ->whereIn('status', ['paid', 'completed', 'processing'])
                ->exists();

            if ($hasActiveOrders) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يمكن حذف مستخدم لديه طلبات نشطة'
                ], 409);
            }

            // حذف البيانات المرتبطة
            $user->orders()->delete();
            $user->transactions()->delete();
            $user->invoices()->delete();
            $user->cart()->delete();

            // حذف المستخدم
            $user->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم حذف المستخدم بنجاح'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في حذف المستخدم',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * تفعيل المستخدم
     */
    public function activate($id)
    {
        try {
            $user = User::findOrFail($id);

            if ($user->status === 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم مفعل بالفعل'
                ], 409);
            }

            $user->update([
                'status' => 'active'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم تفعيل المستخدم بنجاح',
                'data' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في تفعيل المستخدم',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * تعطيل المستخدم
     */
    public function deactivate($id)
    {
        try {
            $user = User::findOrFail($id);

            if ($user->status === 'inactive') {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم معطل بالفعل'
                ], 409);
            }

            $user->update([
                'status' => 'inactive'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم تعطيل المستخدم بنجاح',
                'data' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في تعطيل المستخدم',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * حظر المستخدم
     */
    public function block(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::findOrFail($id);

            if ($user->status === 'blocked') {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم محظور بالفعل'
                ], 409);
            }

            $user->update([
                'status' => 'blocked',
                'blocked_reason' => $request->reason,
                'blocked_at' => now(),
                'blocked_by' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم حظر المستخدم بنجاح',
                'data' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في حظر المستخدم',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * فك حظر المستخدم
     */
    public function unblock($id)
    {
        try {
            $user = User::findOrFail($id);

            if ($user->status !== 'blocked') {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم غير محظور'
                ], 409);
            }

            $user->update([
                'status' => 'active',
                'blocked_reason' => null,
                'blocked_at' => null,
                'blocked_by' => null
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم فك حظر المستخدم بنجاح',
                'data' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في فك حظر المستخدم',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ترقية المستخدم إلى أدمن
     */
    public function promoteToAdmin($id)
    {
        try {
            $user = User::findOrFail($id);

            if ($user->role === 'admin' || $user->role === 'superadmin') {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم لديه صلاحية أدمن بالفعل'
                ], 409);
            }

            $user->update([
                'role' => 'admin'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم ترقية المستخدم إلى أدمن بنجاح',
                'data' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في ترقية المستخدم',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ترقية المستخدم إلى سوبر أدمن
     */
    public function promoteToSuperadmin($id)
    {
        try {
            $user = User::findOrFail($id);

            if ($user->role === 'superadmin') {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم لديه صلاحية سوبر أدمن بالفعل'
                ], 409);
            }

            $user->update([
                'role' => 'superadmin'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم ترقية المستخدم إلى سوبر أدمن بنجاح',
                'data' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في ترقية المستخدم',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * تنزيل صلاحية المستخدم
     */
    public function demoteToUser($id)
    {
        try {
            $user = User::findOrFail($id);

            if ($user->role === 'user') {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم لديه صلاحية مستخدم بالفعل'
                ], 409);
            }

            $user->update([
                'role' => 'user'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم تنزيل صلاحية المستخدم بنجاح',
                'data' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في تنزيل صلاحية المستخدم',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * إضافة رصيد للمستخدم
     */
    public function addBalance(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $user = User::findOrFail($id);
            $oldBalance = $user->balance;

            $user->increment('balance', $request->amount);

            // تسجيل المعاملة
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'type' => 'deposit',
                'amount' => $request->amount,
                'status' => 'completed',
                'gateway' => 'manual',
                'transaction_id' => 'MANUAL-DEP-' . time(),
                'description' => 'إضافة رصيد يدوي من قبل الإدارة - ' . $request->reason
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم إضافة الرصيد بنجاح',
                'data' => [
                    'user' => $user,
                    'transaction' => $transaction,
                    'balance_change' => [
                        'old_balance' => $oldBalance,
                        'new_balance' => $user->balance,
                        'added_amount' => $request->amount
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في إضافة الرصيد',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * خصم رصيد من المستخدم
     */
    public function deductBalance(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $user = User::findOrFail($id);
            $oldBalance = $user->balance;

            if ($user->balance < $request->amount) {
                return response()->json([
                    'success' => false,
                    'message' => 'رصيد المستخدم غير كافي'
                ], 409);
            }

            $user->decrement('balance', $request->amount);

            // تسجيل المعاملة
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'type' => 'withdrawal',
                'amount' => $request->amount,
                'status' => 'completed',
                'gateway' => 'manual',
                'transaction_id' => 'MANUAL-WDL-' . time(),
                'description' => 'خصم رصيد يدوي من قبل الإدارة - ' . $request->reason
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم خصم الرصيد بنجاح',
                'data' => [
                    'user' => $user,
                    'transaction' => $transaction,
                    'balance_change' => [
                        'old_balance' => $oldBalance,
                        'new_balance' => $user->balance,
                        'deducted_amount' => $request->amount
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في خصم الرصيد',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * إعادة تعيين كلمة مرور المستخدم
     */
    public function resetPassword(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|string|min:8|confirmed',
            'logout_all_sessions' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::findOrFail($id);

            $user->update([
                'password' => Hash::make($request->password)
            ]);

            // إذا طلب تسجيل الخروج من جميع الجلسات
            if ($request->boolean('logout_all_sessions')) {
                $user->tokens()->delete();
            }

            return response()->json([
                'success' => true,
                'message' => 'تم إعادة تعيين كلمة المرور بنجاح'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في إعادة تعيين كلمة المرور',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * الحصول على أنشطة المستخدم
     */
    public function getUserActivity($id)
    {
        try {
            $user = User::findOrFail($id);

            // الطلبات الأخيرة
            $recentOrders = $user->orders()
                ->with('items.product')
                ->latest()
                ->limit(20)
                ->get();

            // المعاملات الأخيرة
            $recentTransactions = $user->transactions()
                ->latest()
                ->limit(20)
                ->get();

            // تسجيلات الدخول (إذا كنت تتبعها)
            $loginHistory = $user->loginHistory()
                ->latest()
                ->limit(20)
                ->get();

            // تغييرات الحالة
            $statusChanges = $user->statusChanges()
                ->latest()
                ->limit(20)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $user,
                    'recent_orders' => $recentOrders,
                    'recent_transactions' => $recentTransactions,
                    'login_history' => $loginHistory,
                    'status_changes' => $statusChanges
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في جلب أنشطة المستخدم',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * تصدير بيانات المستخدم
     */
    public function exportUserData($id)
    {
        try {
            $user = User::with([
                'orders.items',
                'transactions',
                'invoices'
            ])->findOrFail($id);

            // تحضير البيانات للتصدير
            $exportData = [
                'user_info' => $user,
                'orders' => $user->orders,
                'transactions' => $user->transactions,
                'invoices' => $user->invoices,
                'exported_at' => now()->format('Y-m-d H:i:s'),
                'exported_by' => auth()->user()->name
            ];

            return response()->json([
                'success' => true,
                'data' => $exportData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في تصدير بيانات المستخدم',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * إرسال إشعار للمستخدم
     */
    public function sendNotification(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'type' => 'in:info,warning,important'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::findOrFail($id);

            // إرسال الإشعار
            // Mail::to($user->email)->send(new AdminNotification($request->all()));

            // تسجيل الإشعار
            $user->notifications()->create([
                'subject' => $request->subject,
                'message' => $request->message,
                'type' => $request->type,
                'sent_by' => auth()->id(),
                'sent_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم إرسال الإشعار بنجاح',
                'data' => [
                    'to' => $user->email,
                    'subject' => $request->subject,
                    'type' => $request->type,
                    'sent_at' => now()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في إرسال الإشعار',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * البحث المتقدم في المستخدمين
     */
    public function searchUsers(Request $request)
    {
        try {
            $query = User::query();

            // فلاتر متقدمة
            if ($request->has('created_from')) {
                $query->where('created_at', '>=', $request->created_from);
            }

            if ($request->has('created_to')) {
                $query->where('created_at', '<=', $request->created_to);
            }

            if ($request->has('last_login_from')) {
                $query->where('last_login_at', '>=', $request->last_login_from);
            }

            if ($request->has('last_login_to')) {
                $query->where('last_login_at', '<=', $request->last_login_to);
            }

            if ($request->has('has_orders')) {
                $query->has('orders');
            }

            if ($request->has('min_orders')) {
                $query->has('orders', '>=', $request->min_orders);
            }

            if ($request->has('has_balance')) {
                $query->where('balance', '>', 0);
            }

            if ($request->has('email_verified')) {
                $query->where('hasVerifiedEmail', $request->boolean('email_verified'));
            }

            if ($request->has('phone_verified')) {
                $query->whereNotNull('phone_verified_at');
            }

            // الترتيب
            $orderBy = $request->order_by ?? 'created_at';
            $orderDirection = $request->order_direction ?? 'desc';
            $query->orderBy($orderBy, $orderDirection);

            // الباجينيت
            $perPage = $request->per_page ?? 50;
            $users = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $users,
                'filters' => $request->all()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في البحث',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * الحصول على إحصائيات المستخدمين
     */
    public function getStats()
    {
        try {
            // الإحصائيات العامة
            $stats = User::selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = "inactive" THEN 1 ELSE 0 END) as inactive,
                SUM(CASE WHEN status = "blocked" THEN 1 ELSE 0 END) as blocked,
                SUM(CASE WHEN hasVerifiedEmail = 1 THEN 1 ELSE 0 END) as email_verified,
                SUM(balance) as total_balance,
                AVG(balance) as avg_balance
            ')->first();

            // إحصائيات حسب الصلاحيات
            $roleStats = User::selectRaw('
                role,
                COUNT(*) as count,
                SUM(balance) as total_balance
            ')
            ->groupBy('role')
            ->get();

            // إحصائيات التسجيل خلال الـ 30 يوم الماضية
            $registrationStats = User::selectRaw('
                DATE(created_at) as date,
                COUNT(*) as count
            ')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

            // أفضل العملاء حسب الإنفاق
            $topCustomers = User::select('users.*')
                ->selectRaw('COUNT(orders.id) as orders_count, SUM(orders.total_amount) as total_spent')
                ->leftJoin('orders', 'users.id', '=', 'orders.user_id')
                ->where('orders.payment_status', 'paid')
                ->groupBy('users.id')
                ->orderByDesc('total_spent')
                ->limit(10)
                ->get();

            // العملاء الأكثر نشاطاً
            $mostActiveUsers = User::select('users.*')
                ->selectRaw('COUNT(orders.id) as orders_count')
                ->leftJoin('orders', 'users.id', '=', 'orders.user_id')
                ->groupBy('users.id')
                ->orderByDesc('orders_count')
                ->limit(10)
                ->get();

            return response()->json([
                'success' => true,
                'stats' => $stats,
                'role_stats' => $roleStats,
                'registration_stats' => $registrationStats,
                'top_customers' => $topCustomers,
                'most_active_users' => $mostActiveUsers
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في جلب الإحصائيات',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
