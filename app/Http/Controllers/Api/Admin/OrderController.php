<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Transaction;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    public function __construct()
    {
    }

    /**
     * عرض جميع الطلبات
     */
    public function index(Request $request)
    {
        try {
            $query = Order::with(['user', 'items.product.productable'])
                ->latest();

            // البحث
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('order_number', 'like', "%{$search}%")
                      ->orWhere('transaction_id', 'like', "%{$search}%")
                      ->orWhereHas('user', function($userQuery) use ($search) {
                          $userQuery->where('name', 'like', "%{$search}%")
                                   ->orWhere('email', 'like', "%{$search}%");
                      });
                });
            }

            // التصفية حسب الحالة
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // التصفية حسب حالة الدفع
            if ($request->has('payment_status')) {
                $query->where('payment_status', $request->payment_status);
            }

            // التصفية حسب طريقة الدفع
            if ($request->has('payment_method')) {
                $query->where('payment_method', $request->payment_method);
            }

            // التصفية حسب المستخدم
            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            // التصفية حسب التاريخ
            if ($request->has('date_from')) {
                $query->where('created_at', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->where('created_at', '<=', $request->date_to);
            }

            // الباجينيت
            $perPage = $request->per_page ?? 20;
            $orders = $query->paginate($perPage);

            // الإحصائيات
            $stats = [
                'total' => Order::count(),
                'pending' => Order::where('status', 'pending')->count(),
                'paid' => Order::where('status', 'paid')->count(),
                'completed' => Order::where('status', 'completed')->count(),
                'cancelled' => Order::where('status', 'cancelled')->count(),
                'total_revenue' => Order::where('status', 'paid')->sum('total_amount'),
                'today_revenue' => Order::whereDate('created_at', today())
                    ->where('status', 'paid')
                    ->sum('total_amount')
            ];

            return response()->json([
                'success' => true,
                'data' => $orders,
                'stats' => $stats,
                'filters' => $request->only(['search', 'status', 'payment_status', 'payment_method', 'user_id'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في جلب الطلبات',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function show($id)
    {
        try {
            $order = Order::with([
                'user',
                'items.product.productable',
                'transactions',
                'invoices'
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $order
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم العثور على الطلب'
            ], 404);
        }
    }

    /**
     * تحديث حالة الطلب
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|in:pending,paid,processing,completed,cancelled,refunded',
            'payment_status' => 'sometimes|in:pending,paid,failed,refunded',
            'payment_method' => 'sometimes|in:credit_card,paypal,bank_transfer,wallet',
            'notes' => 'nullable|string',
            'completed_at' => 'nullable|date',
            'cancelled_at' => 'nullable|date',
            'refunded_at' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $order = Order::with('items')->findOrFail($id);
            $oldStatus = $order->status;

            // تجميع التحديثات
            $updates = [];

            if ($request->has('status')) {
                $updates['status'] = $request->status;

                // تحديث التواريخ بناءً على الحالة الجديدة
                if ($request->status === 'completed' && !$order->completed_at) {
                    $updates['completed_at'] = now();
                } elseif ($request->status === 'cancelled' && !$order->cancelled_at) {
                    $updates['cancelled_at'] = now();
                }
            }

            if ($request->has('payment_status')) {
                $updates['payment_status'] = $request->payment_status;

                if ($request->payment_status === 'paid' && !$order->paid_at) {
                    $updates['paid_at'] = now();
                } elseif ($request->payment_status === 'refunded' && !$order->refunded_at) {
                    $updates['refunded_at'] = now();
                }
            }

            if ($request->has('payment_method')) {
                $updates['payment_method'] = $request->payment_method;
            }

            if ($request->has('notes')) {
                $updates['notes'] = $request->notes;
            }

            // تطبيق التحديثات
            $order->update($updates);

            // إذا تم تغيير الحالة إلى مدفوع، إنشاء فاتورة
            if ($request->status === 'paid' || $request->payment_status === 'paid') {
                if (!$order->invoices()->exists()) {
                    $order->createInvoice();
                }
            }

            // إذا تم إلغاء الطلب، تحرير الخدمات المرتبطة
            if ($request->status === 'cancelled' && $oldStatus !== 'cancelled') {
                foreach ($order->items as $item) {
                    if ($item->product && $item->product->productable_type === 'App\Models\Server') {
                        $item->product->productable->releaseServer();
                    }
                    $item->cancel('تم إلغاء الطلب من قبل الإدارة');
                }
            }

            // إذا تم استرداد الطلب، استرداد الرصيد للمستخدم
            if ($request->payment_status === 'refunded' && $oldStatus !== 'refunded') {
                $order->user->addToBalance($order->total_amount);

                // تسجيل معاملة الاسترداد
                Transaction::create([
                    'user_id' => $order->user_id,
                    'order_id' => $order->id,
                    'type' => 'refund',
                    'amount' => $order->total_amount,
                    'status' => 'completed',
                    'gateway' => $order->payment_method,
                    'description' => 'استرداد مبلغ الطلب #' . $order->order_number
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث الطلب بنجاح',
                'data' => $order->fresh(['user', 'items'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في تحديث الطلب',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * حذف الطلب
     */
    public function destroy($id)
    {
        DB::beginTransaction();

        try {
            $order = Order::findOrFail($id);

            // التحقق من إمكانية الحذف
            if ($order->status === 'paid' || $order->status === 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يمكن حذف طلب مدفوع أو مكتمل'
                ], 409);
            }

            // حذف العناصر المرتبطة
            $order->items()->delete();
            $order->transactions()->delete();
            $order->invoices()->delete();

            // حذف الطلب
            $order->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم حذف الطلب بنجاح'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في حذف الطلب',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * استرداد مبلغ الطلب
     */
    public function refundOrder(Request $request, $id)
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
            $order = Order::findOrFail($id);

            // التحقق من إمكانية الاسترداد
            if ($order->payment_status !== 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يمكن استرداد طلب غير مدفوع'
                ], 409);
            }

            if ($order->refunded_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'تم استرداد هذا الطلب مسبقاً'
                ], 409);
            }

            $refundAmount = min($request->amount, $order->total_amount);

            // إضافة المبلغ المسترد لرصيد المستخدم
            $order->user->addToBalance($refundAmount);

            // تحديث حالة الطلب
            $order->update([
                'payment_status' => 'refunded',
                'refunded_at' => now(),
                'notes' => $order->notes . "\nتم الاسترداد: " . $request->reason
            ]);

            // تسجيل معاملة الاسترداد
            $transaction = Transaction::create([
                'user_id' => $order->user_id,
                'order_id' => $order->id,
                'type' => 'refund',
                'amount' => $refundAmount,
                'status' => 'completed',
                'gateway' => $order->payment_method,
                'transaction_id' => 'REFUND-' . time(),
                'description' => 'استرداد جزئي للطلب #' . $order->order_number . ' - السبب: ' . $request->reason
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم استرداد المبلغ بنجاح',
                'data' => [
                    'order' => $order,
                    'transaction' => $transaction,
                    'refunded_amount' => $refundAmount,
                    'new_balance' => $order->user->balance
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في استرداد المبلغ',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * إنشاء طلب يدوي
     */




     public function getStats(Request $request)
     {
        try {
            // تحديد النطاق الزمني
            $period = $request->period ?? 'month'; // day, week, month, year, custom

            $startDate = null;
            $endDate = now();

            switch ($period) {
                case 'day':
                    $startDate = now()->subDay();
                    break;
                case 'week':
                    $startDate = now()->subWeek();
                    break;
                case 'month':
                    $startDate = now()->subMonth();
                    break;
                case 'year':
                    $startDate = now()->subYear();
                    break;
                case 'custom':
                    $startDate = $request->date_from ?? now()->subMonth();
                    $endDate = $request->date_to ?? now();
                    break;
                default:
                    $startDate = now()->subMonth();
            }

            // الإحصائيات العامة
            $generalStats = [
                'total_orders' => Order::count(),
                'total_revenue' => Order::where('payment_status', 'paid')->sum('total_amount'),
                'average_order_value' => Order::where('payment_status', 'paid')->avg('total_amount') ?? 0,
                'conversion_rate' => Order::count() > 0 ?
                    (Order::where('payment_status', 'paid')->count() / Order::count() * 100) : 0
            ];

            // إحصائيات حسب الحالة
            $statusStats = Order::selectRaw('
                status,
                COUNT(*) as count,
                SUM(total_amount) as revenue
            ')
            ->groupBy('status')
            ->get();

            // إحصائيات حسب طريقة الدفع
            $paymentStats = Order::selectRaw('
                payment_method,
                COUNT(*) as count,
                SUM(total_amount) as revenue
            ')
            ->whereNotNull('payment_method')
            ->groupBy('payment_method')
            ->get();

            // إحصائيات زمنية
            $timeStats = Order::selectRaw('
                DATE(created_at) as date,
                COUNT(*) as orders,
                SUM(CASE WHEN payment_status = "paid" THEN total_amount ELSE 0 END) as revenue
            ')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

            // أعلى العملاء
            $topCustomers = Order::selectRaw('
                user_id,
                COUNT(*) as orders_count,
                SUM(total_amount) as total_spent
            ')
            ->with('user')
            ->where('payment_status', 'paid')
            ->groupBy('user_id')
            ->orderByDesc('total_spent')
            ->limit(10)
            ->get();

            // المنتجات الأكثر طلباً


            return response()->json([
                'success' => true,
                'stats' => [
                    'general' => $generalStats,
                    'status' => $statusStats,
                    'payment' => $paymentStats,
                    'time_series' => $timeStats,
                    'top_customers' => $topCustomers,
                    'period' => [
                        'start' => $startDate,
                        'end' => $endDate
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في جلب الإحصائيات',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * تصدير الطلبات إلى CSV
     */
    public function exportOrders(Request $request)
    {
        try {
            $query = Order::with(['user', 'items']);

            // تطبيق الفلاتر
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('date_from')) {
                $query->where('created_at', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->where('created_at', '<=', $request->date_to);
            }

            $orders = $query->get();

            // إنشاء محتوى CSV
            $csvData = [];

            // العناوين
            $csvData[] = [
                'رقم الطلب',
                'المستخدم',
                'البريد الإلكتروني',
                'المبلغ الإجمالي',
                'الحالة',
                'حالة الدفع',
                'طريقة الدفع',
                'تاريخ الإنشاء',
                'تاريخ الدفع',
                'عدد العناصر'
            ];

            // البيانات
            foreach ($orders as $order) {
                $csvData[] = [
                    $order->order_number,
                    $order->user->name ?? 'غير معروف',
                    $order->user->email ?? 'غير معروف',
                    $order->total_amount,
                    $order->status,
                    $order->payment_status,
                    $order->payment_method ?? 'غير محدد',
                    $order->created_at->format('Y-m-d H:i:s'),
                    $order->paid_at ? $order->paid_at->format('Y-m-d H:i:s') : 'لم يدفع',
                    $order->items->count()
                ];
            }

            // تحويل إلى CSV
            $csvContent = '';
            foreach ($csvData as $row) {
                $csvContent .= implode(',', $row) . "\n";
            }

            return response($csvContent)
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', 'attachment; filename="orders_' . date('Y-m-d') . '.csv"');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في تصدير البيانات',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * إرسال إشعار للمستخدم بخصوص الطلب
     */
    public function sendNotification(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'type' => 'in:status_update,payment_reminder,general'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $order = Order::with('user')->findOrFail($id);

            // إرسال الإشعار
            // Mail::to($order->user->email)->send(new OrderNotification($order, $request->all()));

            // تسجيل الإشعار
            $order->update([
                'notes' => $order->notes . "\nتم إرسال إشعار: " . $request->subject . ' - ' . now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم إرسال الإشعار بنجاح',
                'data' => [
                    'to' => $order->user->email,
                    'subject' => $request->subject,
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
}
