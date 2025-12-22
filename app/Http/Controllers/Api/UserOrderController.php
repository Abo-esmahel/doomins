<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\Invoice;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class UserOrderController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * عرض جميع طلبات المستخدم
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            $query = Order::where('user_id', $user->id)
                ->with(['items.product.productable', 'invoices', 'transactions'])
                ->latest();

            // التصفية حسب الحالة
            if ($request->has('status')) {
                $statuses = is_array($request->status) ? $request->status : [$request->status];
                $query->whereIn('status', $statuses);
            }

            // التصفية حسب حالة الدفع
            if ($request->has('payment_status')) {
                $query->where('payment_status', $request->payment_status);
            }

            // التصفية حسب طريقة الدفع
            if ($request->has('payment_method')) {
                $query->where('payment_method', $request->payment_method);
            }

            // التصفية حسب التاريخ
            if ($request->has('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            // البحث حسب رقم الطلب
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('order_number', 'like', "%{$search}%")
                      ->orWhere('transaction_id', 'like', "%{$search}%");
                });
            }

            // الباجينيت
            $perPage = $request->per_page ?? 15;
            $orders = $query->paginate($perPage);

            // إحصائيات الطلبات
            $orderStats = [
                'total' => Order::where('user_id', $user->id)->count(),
                'pending' => Order::where('user_id', $user->id)->where('status', 'pending')->count(),
                'paid' => Order::where('user_id', $user->id)->where('status', 'paid')->count(),
                'completed' => Order::where('user_id', $user->id)->where('status', 'completed')->count(),
                'cancelled' => Order::where('user_id', $user->id)->where('status', 'cancelled')->count(),
                'total_spent' => Order::where('user_id', $user->id)
                    ->where('payment_status', 'paid')
                    ->sum('total_amount')
            ];

            // إضافة معلومات إضافية
            $orders->getCollection()->transform(function ($order) {
                $order->status_text = $this->getStatusText($order->status);
                $order->payment_status_text = $this->getPaymentStatusText($order->payment_status);
                $order->can_be_cancelled = $this->canBeCancelled($order);
                $order->can_be_paid = $this->canBePaid($order);
                $order->has_invoice = $order->invoices()->exists();
                $order->invoice_download_url = $order->invoices()->exists()
                    ? route('user.invoices.download', $order->invoices()->first()->id)
                    : null;

                return $order;
            });

            return response()->json([
                'success' => true,
                'message' => 'تم جلب الطلبات بنجاح',
                'data' => $orders,
                'stats' => $orderStats,
                'filters' => $request->only(['status', 'payment_status', 'payment_method', 'date_from', 'date_to', 'search'])
            ]);
        } catch (\Exception $e) {
            Log::error('User Order Index Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في جلب الطلبات'
            ], 500);
        }
    }

    /**
     * إنشاء طلب جديد من السلة
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'payment_method' => 'required|in:wallet,credit_card,paypal,bank_transfer',
            'billing_cycle' => 'sometimes|in:monthly,yearly',
            'notes' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();

        DB::beginTransaction();

        try {
            // التحقق من وجود سلة وبنودها
            $cart = Cart::where('user_id', $user->id)
                ->with('items.product.productable')
                ->first();

            if (!$cart || $cart->items->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'السلة فارغة'
                ], 400);
            }

            // إنشاء الطلب
            $order = Order::create([
                'user_id' => $user->id,
                'order_number' => $this->generateOrderNumber(),
                'subtotal' => $cart->items()->sum('total'),
                'discount_amount' => $cart->discount_amount ?? 0,
                'tax_amount' => $cart->tax_amount ?? 0,
                'total_amount' => $this->calculateOrderTotal($cart),
                'status' => 'pending',
                'payment_method' => $request->payment_method,
                'payment_status' => 'pending',
                'notes' => $request->notes
            ]);

            // نسخ عناصر السلة إلى الطلب
            foreach ($cart->items as $cartItem) {
                $orderItem = OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $cartItem->product_id,
                    'quantity' => $cartItem->quantity,
                    'price' => $cartItem->price,
                    'total' => $cartItem->total,
                    'billing_cycle' => $request->billing_cycle ?? $cartItem->billing_cycle ?? 'monthly',
                    'options' => $cartItem->options,
                    'status' => 'pending'
                ]);

                // إذا كان الدفع من الرصيد، تفعيل الخدمة فوراً
                if ($request->payment_method === 'wallet') {
                    $orderItem->activate();
                }
            }

            // تفريغ السلة
            $cart->items()->delete();
            $cart->update([
                'coupon_code' => null,
                'discount_amount' => 0,
                'tax_amount' => 0
            ]);

            // إذا كان الدفع من الرصيد، معالجته فوراً
            if ($request->payment_method === 'wallet') {
                $this->processWalletPayment($order, $user);
            }

            DB::commit();

            // إرسال إيميل تأكيد الطلب
            $this->sendOrderConfirmationEmail($order, $user);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء الطلب بنجاح',
                'data' => [
                    'order' => $order->load(['items.product', 'invoices']),
                    'payment_required' => $request->payment_method !== 'wallet',
                    'payment_url' => $request->payment_method !== 'wallet'
                        ? route('user.payment.show', $order->id)
                        : null,
                    'invoice' => $order->invoices()->first()
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('User Order Store Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في إنشاء الطلب: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * إنشاء طلب مباشر (بدون سلة)
     */
    public function createDirectOrder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'billing_cycle' => 'required|in:monthly,yearly',
            'payment_method' => 'required|in:wallet,credit_card,paypal,bank_transfer',
            'options' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();

        DB::beginTransaction();

        try {
            $product = Product::with('productable')->findOrFail($request->product_id);

            // التحقق من توفر المنتج
            if ($product->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'هذا المنتج غير متاح حالياً'
                ], 400);
            }

            // حساب السعر حسب دورة الفوترة
            $price = $request->billing_cycle === 'yearly'
                ? $product->price_yearly
                : $product->price_monthly;

            $total = $price * $request->quantity;

            // إنشاء الطلب
            $order = Order::create([
                'user_id' => $user->id,
                'order_number' => $this->generateOrderNumber(),
                'subtotal' => $total,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'total_amount' => $total,
                'status' => 'pending',
                'payment_method' => $request->payment_method,
                'payment_status' => 'pending',
                'notes' => 'طلب مباشر'
            ]);

            // إضافة المنتج للطلب
            $orderItem = OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => $request->quantity,
                'price' => $price,
                'total' => $total,
                'billing_cycle' => $request->billing_cycle,
                'options' => $request->options,
                'status' => 'pending'
            ]);

            // إذا كان الدفع من الرصيد، تفعيل الخدمة فوراً
            if ($request->payment_method === 'wallet') {
                $orderItem->activate();
                $this->processWalletPayment($order, $user);
            }

            DB::commit();

            // إرسال إيميل تأكيد الطلب
            $this->sendOrderConfirmationEmail($order, $user);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء الطلب المباشر بنجاح',
                'data' => [
                    'order' => $order->load(['items.product', 'invoices']),
                    'payment_required' => $request->payment_method !== 'wallet',
                    'payment_url' => $request->payment_method !== 'wallet'
                        ? route('user.payment.show', $order->id)
                        : null
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('User Direct Order Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في إنشاء الطلب المباشر: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * عرض تفاصيل طلب معين
     */
    public function show(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();

            $order = Order::where('user_id', $user->id)
                ->with([
                    'items.product.productable',
                    'invoices',
                    'transactions',
                    'user'
                ])
                ->findOrFail($id);

            // إضافة معلومات إضافية
            $order->status_text = $this->getStatusText($order->status);
            $order->payment_status_text = $this->getPaymentStatusText($order->payment_status);
            $order->can_be_cancelled = $this->canBeCancelled($order);
            $order->can_be_paid = $this->canBePaid($order);
            $order->has_invoice = $order->invoices()->exists();
            $order->invoice_download_url = $order->invoices()->exists()
                ? route('user.invoices.download', $order->invoices()->first()->id)
                : null;

            // تفاصيل كل عنصر
            $order->items->each(function ($item) {
                $item->product_details = $item->product->productable?->getDashboardData();
                $item->can_be_managed = $item->status === 'active';
                $item->management_url = $item->status === 'active'
                    ? route('user.services.show', $item->id)
                    : null;
            });

            // معلومات الدفع
            $paymentInfo = $this->getPaymentInfo($order);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب تفاصيل الطلب بنجاح',
                'data' => [
                    'order' => $order,
                    'payment_info' => $paymentInfo,
                    'actions' => [
                        'can_cancel' => $this->canBeCancelled($order),
                        'can_pay' => $this->canBePaid($order),
                        'can_download_invoice' => $order->invoices()->exists(),
                        'can_contact_support' => true
                    ],
                    'support_url' => route('user.tickets.create', ['order_id' => $order->id])
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم العثور على الطلب'
            ], 404);
        }
    }

    /**
     * دفع طلب معين
     */
    public function pay(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'payment_method' => 'required|in:wallet,credit_card,paypal,bank_transfer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();

        DB::beginTransaction();

        try {
            $order = Order::where('user_id', $user->id)
                ->where('status', 'pending')
                ->findOrFail($id);

            // التحقق من أن الطلب قابل للدفع
            if ($order->payment_status === 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'تم دفع هذا الطلب مسبقاً'
                ], 400);
            }

            // إذا كان الدفع من الرصيد
            if ($request->payment_method === 'wallet') {
                $this->processWalletPayment($order, $user);
            } else {
                // تحديث طريقة الدفع
                $order->update([
                    'payment_method' => $request->payment_method
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $request->payment_method === 'wallet'
                    ? 'تم الدفع بنجاح'
                    : 'تم تحديث طريقة الدفع',
                'data' => [
                    'order' => $order->fresh(['items', 'invoices']),
                    'payment_redirect_url' => $request->payment_method !== 'wallet'
                        ? route('user.payment.show', $order->id)
                        : null,
                    'invoice' => $order->invoices()->first()
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('User Order Pay Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في عملية الدفع: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * إلغاء طلب معين
     */
    public function cancel(Request $request, $id): JsonResponse
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

        $user = Auth::user();

        DB::beginTransaction();

        try {
            $order = Order::where('user_id', $user->id)
                ->whereIn('status', ['pending', 'processing'])
                ->findOrFail($id);

            // التحقق من إمكانية الإلغاء
            if (!$this->canBeCancelled($order)) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يمكن إلغاء هذا الطلب'
                ], 400);
            }

            // إلغاء الطلب
            $order->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'notes' => $order->notes . "\nتم الإلغاء بسبب: " . $request->reason
            ]);

            // إلغاء العناصر المرتبطة
            foreach ($order->items as $item) {
                $item->cancel('تم إلغاء الطلب');

                // إذا كان سيرفر، تحريره
                if ($item->product && $item->product->productable_type === 'App\Models\Server') {
                    $item->product->productable->releaseServer();
                }
            }

            // إذا كان مدفوعاً، استرداد المبلغ
            if ($order->payment_status === 'paid') {
                $user->increment('balance', $order->total_amount);

                // تسجيل معاملة الاسترداد
                Transaction::create([
                    'user_id' => $user->id,
                    'order_id' => $order->id,
                    'type' => 'refund',
                    'amount' => $order->total_amount,
                    'status' => 'completed',
                    'gateway' => $order->payment_method,
                    'description' => 'استرداد مبلغ الطلب الملغي #' . $order->order_number
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم إلغاء الطلب بنجاح',
                'data' => [
                    'order' => $order->fresh(),
                    'refunded' => $order->payment_status === 'paid',
                    'refund_amount' => $order->payment_status === 'paid' ? $order->total_amount : 0,
                    'new_balance' => $user->balance
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('User Order Cancel Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في إلغاء الطلب: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * تنزيل فاتورة الطلب
     */
    public function downloadInvoice(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();

            $order = Order::where('user_id', $user->id)->findOrFail($id);
            $invoice = $order->invoices()->firstOrFail();

            // محاكاة رابط التنزيل
            $downloadUrl = route('user.invoices.download', $invoice->id);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب رابط التنزيل',
                'data' => [
                    'invoice' => $invoice,
                    'download_url' => $downloadUrl,
                    'valid_until' => now()->addHours(24)->format('Y-m-d H:i:s')
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم العثور على الفاتورة'
            ], 404);
        }
    }

    /**
     * إعادة طلب سابق
     */
    public function reorder(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();

            $oldOrder = Order::where('user_id', $user->id)
                ->with('items.product')
                ->findOrFail($id);

            DB::beginTransaction();

            // إنشاء طلب جديد بنفس العناصر
            $newOrder = Order::create([
                'user_id' => $user->id,
                'order_number' => $this->generateOrderNumber(),
                'subtotal' => $oldOrder->subtotal,
                'discount_amount' => $oldOrder->discount_amount,
                'tax_amount' => $oldOrder->tax_amount,
                'total_amount' => $oldOrder->total_amount,
                'status' => 'pending',
                'payment_method' => $oldOrder->payment_method,
                'payment_status' => 'pending',
                'notes' => 'إعادة طلب #' . $oldOrder->order_number
            ]);

            // نسخ العناصر
            foreach ($oldOrder->items as $item) {
                OrderItem::create([
                    'order_id' => $newOrder->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'total' => $item->total,
                    'billing_cycle' => $item->billing_cycle,
                    'options' => $item->options,
                    'status' => 'pending'
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء طلب جديد بنفس العناصر',
                'data' => [
                    'old_order' => $oldOrder,
                    'new_order' => $newOrder->load('items.product'),
                    'payment_url' => route('user.payment.show', $newOrder->id)
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('User Reorder Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في إعادة الطلب: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * تتبع حالة الطلب
     */
    public function track(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();

            $order = Order::where('user_id', $user->id)
                ->with(['items.product', 'transactions'])
                ->findOrFail($id);

            // سجل حالة الطلب
            $statusHistory = [
                ['status' => 'pending', 'label' => 'قيد الانتظار', 'description' => 'تم استلام طلبك', 'timestamp' => $order->created_at],
                ['status' => 'processing', 'label' => 'قيد المعالجة', 'description' => 'جاري معالجة طلبك', 'timestamp' => $order->created_at->addMinutes(5)],
            ];

            if ($order->paid_at) {
                $statusHistory[] = ['status' => 'paid', 'label' => 'مدفوع', 'description' => 'تم دفع الطلب', 'timestamp' => $order->paid_at];
            }

            if ($order->completed_at) {
                $statusHistory[] = ['status' => 'completed', 'label' => 'مكتمل', 'description' => 'تم اكتمال الطلب', 'timestamp' => $order->completed_at];
            }

            if ($order->cancelled_at) {
                $statusHistory[] = ['status' => 'cancelled', 'label' => 'ملغي', 'description' => 'تم إلغاء الطلب', 'timestamp' => $order->cancelled_at];
            }

            // الوقت المقدر للتسليم
            $estimatedDelivery = $order->created_at->addHours(24);

            return response()->json([
                'success' => true,
                'message' => 'معلومات تتبع الطلب',
                'data' => [
                    'order' => $order,
                    'status_history' => $statusHistory,
                    'current_status' => [
                        'code' => $order->status,
                        'label' => $this->getStatusText($order->status),
                        'description' => $this->getStatusDescription($order->status)
                    ],
                    'estimated_delivery' => $estimatedDelivery->format('Y-m-d H:i'),
                    'time_remaining' => now()->diffInHours($estimatedDelivery, false),
                    'support_contact' => [
                        'email' => 'support@example.com',
                        'phone' => '+1234567890',
                        'available_hours' => '24/7'
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم العثور على الطلب'
            ], 404);
        }
    }

    /**
     * الحصول على ملخص الطلبات
     */
    public function summary(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            $summary = [
                'total_orders' => Order::where('user_id', $user->id)->count(),
                'pending_orders' => Order::where('user_id', $user->id)->where('status', 'pending')->count(),
                'active_services' => OrderItem::whereHas('order', function($q) use ($user) {
                    $q->where('user_id', $user->id)
                      ->whereIn('status', ['paid', 'completed']);
                })->where('status', 'active')->count(),
                'total_spent' => Order::where('user_id', $user->id)
                    ->where('payment_status', 'paid')
                    ->sum('total_amount'),
                'last_order' => Order::where('user_id', $user->id)
                    ->latest()
                    ->first()
                    ->only(['id', 'order_number', 'total_amount', 'status', 'created_at']) ?? null,
                'upcoming_renewals' => OrderItem::whereHas('order', function($q) use ($user) {
                    $q->where('user_id', $user->id)
                      ->whereIn('status', ['paid', 'completed']);
                })
                ->where('status', 'active')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now()->addDays(30))
                ->count()
            ];

            return response()->json([
                'success' => true,
                'message' => 'ملخص الطلبات',
                'data' => $summary
            ]);
        } catch (\Exception $e) {
            Log::error('User Order Summary Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في جلب الملخص'
            ], 500);
        }
    }

    // ========== الدوال المساعدة ==========

    /**
     * معالجة الدفع من الرصيد
     */
    private function processWalletPayment(Order $order, User $user): void
    {
        // التحقق من الرصيد الكافي
        if ($user->balance < $order->total_amount) {
            throw new \Exception('رصيدك غير كافي لإتمام الشراء. الرصيد الحالي: ' . $user->balance);
        }

        // خصم من الرصيد
        $user->decrement('balance', $order->total_amount);

        // تحديث حالة الطلب
        $order->update([
            'status' => 'paid',
            'payment_status' => 'paid',
            'paid_at' => now()
        ]);

        // تسجيل المعاملة
        Transaction::create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'type' => 'payment',
            'amount' => $order->total_amount,
            'status' => 'completed',
            'gateway' => 'wallet',
            'transaction_id' => 'WALLET-' . time(),
            'description' => 'دفع لطلب #' . $order->order_number
        ]);

        // إنشاء فاتورة
        Invoice::create([
            'invoice_number' => $this->generateInvoiceNumber(),
            'order_id' => $order->id,
            'user_id' => $user->id,
            'amount' => $order->total_amount,
            'status' => 'paid',
            'due_date' => now(),
            'paid_date' => now(),
            'payment_method' => 'wallet'
        ]);

        // تفعيل جميع عناصر الطلب
        foreach ($order->items as $item) {
            $item->activate();
        }
    }

    /**
     * إرسال إيميل تأكيد الطلب
     */
    private function sendOrderConfirmationEmail(Order $order, User $user): void
    {
        try {
            $data = [
                'order' => $order->load('items.product'),
                'user' => $user,
                'invoice' => $order->invoices()->first()
            ];

            // Mail::to($user->email)->send(new OrderConfirmationMail($data));

            Log::info('Order confirmation email sent for order #' . $order->order_number);
        } catch (\Exception $e) {
            Log::error('Failed to send order confirmation email: ' . $e->getMessage());
        }
    }

    /**
     * توليد رقم طلب
     */
    private function generateOrderNumber(): string
    {
        $prefix = 'ORD';
        $date = date('Ymd');

        $latest = Order::where('order_number', 'like', "{$prefix}-{$date}-%")
                      ->latest('id')
                      ->first();

        $number = $latest ? (int) substr($latest->order_number, -4) + 1 : 1;

        return sprintf('%s-%s-%04d', $prefix, $date, $number);
    }

    /**
     * توليد رقم فاتورة
     */
    private function generateInvoiceNumber(): string
    {
        $prefix = 'INV';
        $date = date('Ymd');

        $latest = Invoice::where('invoice_number', 'like', "{$prefix}-{$date}-%")
                        ->latest('id')
                        ->first();

        $number = $latest ? (int) substr($latest->invoice_number, -4) + 1 : 1;

        return sprintf('%s-%s-%04d', $prefix, $date, $number);
    }

    /**
     * حساب إجمالي الطلب
     */
    private function calculateOrderTotal($cart)
    {
        $subtotal = $cart->items()->sum('total') ?? 0;
        $discount = $cart->discount_amount ?? 0;
        $tax = $cart->tax_amount ?? 0;

        return max(0, $subtotal - $discount + $tax);
    }

    /**
     * الحصول على نص الحالة
     */
    private function getStatusText(string $status): string
    {
        $statuses = [
            'pending' => 'قيد الانتظار',
            'processing' => 'قيد المعالجة',
            'paid' => 'مدفوع',
            'completed' => 'مكتمل',
            'cancelled' => 'ملغي',
            'refunded' => 'تم الاسترداد'
        ];

        return $statuses[$status] ?? $status;
    }

    /**
     * الحصول على نص حالة الدفع
     */
    private function getPaymentStatusText(string $status): string
    {
        $statuses = [
            'pending' => 'قيد الانتظار',
            'paid' => 'مدفوع',
            'failed' => 'فشل',
            'refunded' => 'تم الاسترداد'
        ];

        return $statuses[$status] ?? $status;
    }

    /**
     * الحصول على وصف الحالة
     */
    private function getStatusDescription(string $status): string
    {
        $descriptions = [
            'pending' => 'طلبك قيد المراجعة',
            'processing' => 'جاري معالجة طلبك',
            'paid' => 'تم دفع الطلب بنجاح',
            'completed' => 'تم اكتمال طلبك',
            'cancelled' => 'تم إلغاء الطلب',
            'refunded' => 'تم استرداد مبلغ الطلب'
        ];

        return $descriptions[$status] ?? '';
    }

    /**
     * التحقق من إمكانية إلغاء الطلب
     */
    private function canBeCancelled(Order $order): bool
    {
        return in_array($order->status, ['pending', 'processing'])
            && $order->payment_status !== 'paid';
    }

    /**
     * التحقق من إمكانية دفع الطلب
     */
    private function canBePaid(Order $order): bool
    {
        return $order->status === 'pending'
            && $order->payment_status === 'pending';
    }

    /**
     * الحصول على معلومات الدفع
     */
    private function getPaymentInfo(Order $order): array
    {
        return [
            'method' => $order->payment_method,
            'method_text' => $this->getPaymentMethodText($order->payment_method),
            'status' => $order->payment_status,
            'status_text' => $this->getPaymentStatusText($order->payment_status),
            'amount' => $order->total_amount,
            'paid_at' => $order->paid_at?->format('Y-m-d H:i'),
            'transaction_id' => $order->transaction_id,
            'gateway_response' => $order->transactions()->first()?->gateway_response
        ];
    }

    /**
     * الحصول على نص طريقة الدفع
     */
    private function getPaymentMethodText(string $method): string
    {
        $methods = [
            'wallet' => 'المحفظة',
            'credit_card' => 'بطاقة ائتمان',
            'paypal' => 'باي بال',
            'bank_transfer' => 'تحويل بنكي'
        ];

        return $methods[$method] ?? $method;
    }
}
