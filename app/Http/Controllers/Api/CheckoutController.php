<?php


namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\User;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{

    public function show()
    {
        $user = auth('sanctum')->user();
        if(!$user){
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }
         if($user->status != 'active'){
            return response()->json([
                'success' => false,
                'message' => 'not active user'
            ], 401);
        }
        $cart = Cart::where('user_id', $user->id)
            ->where('is_active', true)
            ->with(['items.product.productable'])
            ->first();

        if (!$cart || $cart->items->isEmpty()) {

            return response()->json([
                'cart' => null,
                'total' => 0,
                'message' => 'عربة التسوق فارغة',
            ]);
        }

        $total = $cart->items->sum(function ($item) {
            return $item->quantity * $item->price;
        });

       return response()->json([
            'cart' => $cart,
            'total' => $total,
        ]);
    }

        private function getAvailablePaymentMethods(): array
 {
       $methods = [
    [
        'id' => 'credit_card',
        'name' => 'بطاقة ائتمان',
        'icon' => 'credit-card',
        'description' => 'ادفع باستخدام بطاقة الائتمان أو البنكية (Visa/Mastercard)'
    ],
    [
        'id' => 'paypal',
        'name' => 'باي بال',
        'icon' => 'paypal',
        'description' => 'ادفع باستخدام حساب باي بال (للمعاملات الدولية)'
    ],
<<<<<<< HEAD
           [
        'id' => 'ShamCash',
        'name' => 'برنامج شام كاش',
        'icon' => 'ShamCash',
        'description' => 'تحويل مباشر إلى الحساب الالكتروني'
           ],
=======
>>>>>>> 1539afe (وصف التحديث الجديد)
    [
        'id' => 'bank_transfer',
        'name' => 'تحويل بنكي',
        'icon' => 'bank',
        'description' => 'تحويل مباشر إلى الحساب البنكي'
    ],
    [
        'id' => 'cash_on_delivery',
        'name' => 'الدفع عند الاستلام',
        'icon' => 'package',
        'description' => 'ادفع نقداً عند استلام الطلب (شائع جداً في سوريا)'
    ],
    [
        'id' => 'stc_pay',
        'name' => 'STC Pay',
        'icon' => 'mobile',
        'description' => 'محفظة إلكترونية عبر الهاتف (للعملاء من سوريا)'
    ],
    [
        'id' => 'omne',
        'name' => 'أمنية',
        'icon' => 'smartphone',
        'description' => 'خدمة الدفع الإلكتروني السورية'
    ],
    [
        'id' => 'mobile_cash',
        'name' => 'المحفظة المتنقلة',
        'icon' => 'phone',
        'description' => 'شحن رصيد والدفع عبر شركات الاتصالات'
    ],
    [
        'id' => 'exchange_offices',
        'name' => 'مكاتب الصرافة',
        'icon' => 'dollar-sign',
        'description' => 'الدفع عبر مكاتب الصرافة المرخصة'
    ],
    [
        'id' => 'western_union',
        'name' => 'ويسترن يونيون',
        'icon' => 'globe',
        'description' => 'تحويل الأموال عبر ويسترن يونيون'
    ],
    [
        'id' => 'money_gram',
        'name' => 'ماني جرام',
        'icon' => 'send',
        'description' => 'خدمة تحويل الأموال العالمية'
    ],
    [
        'id' => 'crypto',
        'name' => 'عملات رقمية',
        'icon' => 'bitcoin',
        'description' => 'الدفع باستخدام العملات المشفرة (مثل Bitcoin)'
    ],
    [
        'id' => 'hawala',
        'name' => 'الحوالة التقليدية',
        'icon' => 'users',
        'description' => 'نظام الحوالات التقليدي المنتشر في المنطقة'
    ],
    [
        'id' => 'wallet',
        'name' => 'المحفظة الداخلية',
        'icon' => 'wallet',
        'description' => 'استخدم رصيد محفظتك الداخلية للدفع الفوري'
    ],
    [
        'id'=>'syriatel_cash',
        'name'=>'سيريتل كاش',
        'icon'=>'phone',
        'description'=>'الدفع عبر خدمة سيريتل كاش في سوريا'
    ],
    [
      'id'=>'mtn_cash',
        'name'=>'MTN كاش',
        'icon'=>'phone',
        'description'=>'الدفع عبر خدمة MTN كاش في سوريا'
    ]
     ];



        return $methods;
 }

     public function showPayment($orderId)
    {
        $order = Order::find($orderId);
        if(!$order){
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }
        $user = $order->user;
        if(!$user){
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }
         if($user->status != 'active'){
            return response()->json([
                'success' => false,
                'message' => 'not active user'
            ], 401);
        }
        $paymentMethods = $this->getAvailablePaymentMethods();


       //طريقة الدفع غير موجودة
        if(!in_array($order->payment_method, $paymentMethods)) {
            $order->payment_method = null;
            $order->save();


        }

        return response()->json([
            'success' => $order->payment_method != null,
            'order' => $order,
            'payment_methods' => $paymentMethods
        ]);
    }


  public function process(Order $order)
    {
        $user = $order->user;

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'المستخدم غير مصرح له'], 401);
        }

        if ($user->status != 'active') {
            return response()->json(['success' => false, 'message' => 'المستخدم غير نشط'], 401);
        }

        if (!$order || $order->user_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'الطلب غير موجود'], 404);
        }

        // 1. معالجة الدفع عبر المحفظة (فوري)
        if ($order->payment_method === 'wallet') {
            return $this->processWalletPayment($order, $user);
        }

        // 2. معالجة طرق الدفع اليدوية السورية (تتطلب موافقة أدمن)
        $manualMethods = ['syriatel_cash', 'mtn_cash', 'bank_transfer', 'alharam_transfer', 'usdt'];

        if (in_array($order->payment_method, $manualMethods)) {
            return $this->processManualPayment($order);
        }

        // 3. معالجة بوابات الدفع العالمية (إذا توفرت لاحقاً)
        // if ($order->payment_method === 'paypal') { ... }

        return response()->json([
            'success' => false,
            'message' => 'طريقة الدفع غير مدعومة حالياً',
        ], 400);
    }

    /**
     * معالجة الدفع عبر المحفظة الداخلية
     */
    private function processWalletPayment($order, $user)
    {
        if ($user->balance < $order->total) {
            return response()->json([
                'success' => false,
                'message' => 'رصيد المحفظة غير كافي. يرجى شحن رصيدك.',
            ], 400);
        }

        DB::beginTransaction();
        try {
            // خصم الرصيد
            $user->decrement('balance', $order->total);

            // تحديث حالة الطلب
            $order->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            // تسجيل المعاملة
            DB::table('transactions')->insert([
                'transaction_id' => 'TXN-' . strtoupper(uniqid()),
                'user_id' => $user->id,
                'order_id' => $order->id,
                'type' => 'payment',
                'status' => 'completed',
                'amount' => $order->total,
                'currency' => 'SAR', // أو SYP حسب عملتك
                'gateway' => 'wallet',
                'description' => 'دفع فوري من المحفظة للطلب #' . $order->order_number,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // تفعيل الخدمات (سيرفر/دومين) هنا إذا كان التفعيل تلقائي
            // $this->activateServices($order);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم الدفع بنجاح عبر المحفظة',
                'order' => $order,
                'status' => 'paid'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'حدث خطأ أثناء الدفع: ' . $e->getMessage()], 500);
        }
    }

    /**
     * معالجة الدفع اليدوي (سيريتل/MTN/بنك)
     */
    private function processManualPayment($order)
    {
        // هنا لا نخصم رصيد ولا نضع الحالة paid
        // بل نضع الحالة pending_payment ونرسل تعليمات التحويل

        // جلب تعليمات الدفع بناء على الطريقة
        $instructions = $this->getPaymentInstructions($order->payment_method);

        $order->update([
            'status' => 'pending_payment', // حالة جديدة تعني بانتظار التحويل
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم استلام طلبك. يرجى إتمام عملية التحويل لتفعيل الطلب.',
            'order' => $order,
            'status' => 'pending',
            'action_required' => true,
            'payment_instructions' => $instructions
        ]);
    }

    /**
     * جلب بيانات التحويل (أرقام الهواتف/الحسابات)
     */
    private function getPaymentInstructions($method)
    {
        $instructions = [
            'syriatel_cash' => [
                'text' => 'يرجى تحويل المبلغ إلى رقم سيريتل كاش التالي وإرفاق رقم العملية.',
                'account_number' => '093xxxxxxx', // ضع رقمك هنا
                'merchant_id' => '123456'
            ],
            'mtn_cash' => [
                'text' => 'يرجى تحويل المبلغ إلى رقم MTN كاش التالي.',
                'account_number' => '094xxxxxxx' // ضع رقمك هنا
            ],
            'usdt' => [
                'text' => 'USDT (TRC20) Address',
                'wallet_address' => 'TVxxxxxxxxxxxxxxxxxxxxxxxx' // عنوان محفظتك
            ],
            'bank_transfer' => [
                'bank_name' => 'بنك بيمو',
                'iban' => 'SYxxxxxxxxxxxxxxxxx',
                'beneficiary' => 'اسم المستفيد'
            ]
        ];

        return $instructions[$method] ?? [];
    }




    public function quickPay(Request $request)
    {
        $request->validate([
            'cart_id' => 'required|exists:carts,id',
        ]);

        $user = auth('sanctum')->user();

        DB::beginTransaction();

        try {
            $cart = Cart::where('id', $request->cart_id)
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->with(['items.product'])
                ->firstOrFail();

            if ($cart->items->isEmpty()) {
                throw new \Exception('عربة التسوق فارغة');
            }

            $total = $cart->items->sum(function ($item) {
                return $item->quantity * $item->price;
            });

            if ($user->balance < $total) {
                return response()->json([
                    'success' => false,
                    'message' => 'رصيدك غير كافي. الرصيد المطلوب: ' . $total,
                ], 400);
            }

            $order = Order::create([
                'order_number' => 'ORD-' . strtoupper(uniqid()),
                'user_id' => $user->id,
                'cart_id' => $cart->id,
                'total' => $total,
                'status' => 'paid',
                'payment_method' => 'wallet',
                'paid_at' => now(),
            ]);

            $user->decrement('balance', $total);

          DB::table('transactions')->insert([
                'transaction_id' => 'TXN-' . strtoupper(uniqid()),
                'user_id' => $user->id,
                'order_id' => $order->id,
                'type' => 'payment',
                'status' => 'completed',
                'amount' => $total,
                'currency' => 'SAR',
                'description' => 'دفع طلب #' . $order->order_number . ' باستخدام المحفظة',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $cart->update(['is_active' => false]);
            Cart::create([
                'user_id' => $user->id,
                'is_active' => true,
            ]);

            DB::commit();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'تم الدفع بنجاح',
                    'order' => $order->load(['cart.items.product.productable']),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم الدفع بنجاح',
                'order' => $order->load(['cart.items.product.productable']),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 400);
            }

            return back()->with('error', $e->getMessage());
        }
    }


    public function verifyStock(Request $request)
    {
        $user = auth('sanctum')->user();
   if(!$user){
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }
         if($user->status != 'active'){
            return response()->json([
                'success' => false,
                'message' => 'not active user'
            ], 401);
        }
        $cart = Cart::where('user_id', $user->id)
            ->where('is_active', true)
            ->with(['items.product.productable'])
            ->first();

        if (!$cart) {
            return response()->json([
                'available' => false,
                'message' => 'لا توجد عربة تسوق نشطة',
            ]);
        }

        $unavailableItems = [];

        foreach ($cart->items as $item) {
            $product = $item->product;

            if (!$product) {
                $unavailableItems[] = [
                    'id' => $item->id,
                    'name' => $item->product_name,
                    'reason' => 'المنتج غير موجود',
                ];
                continue;
            }

            $actualProduct = $product->productable;

            if (!$actualProduct) {
                $unavailableItems[] = [
                    'id' => $item->id,
                    'name' => $item->product_name,
                    'reason' => 'المنتج غير متوفر',
                ];
                continue;
            }

            // التحقق من توفر الدومين
            if ($product->type === 'domain') {
                if (!$actualProduct->available) {
                    $unavailableItems[] = [
                        'id' => $item->id,
                        'name' => $item->product_name,
                        'reason' => 'النطاق غير متوفر',
                    ];
                }
            }

            if ($product->type === 'server') {
                if ($actualProduct->status !== 'available') {
                    $unavailableItems[] = [
                        'id' => $item->id,
                        'name' => $item->product_name,
                        'reason' => 'السيرفر غير متوفر',
                    ];
                }
            }
        }

        return response()->json([
            'available' => empty($unavailableItems),
            'items' => $unavailableItems,
            'cart_id' => $cart->id,
        ]);
    }


    // public function partialPayment(Request $request)
    // {
    //     $request->validate([
    //         'wallet_amount' => 'required|numeric|min:0',
    //         'remaining_method' => 'required|in:cash,credit_card',
    //     ]);

    //     $user = auth('sanctum')->user();

    //     DB::beginTransaction();

    //     try {
    //         $cart = Cart::where('user_id', $user->id)
    //             ->where('is_active', true)
    //             ->with(['items.product'])
    //             ->firstOrFail();

    //         $total = $cart->items->sum(function ($item) {
    //             return $item->quantity * $item->price;
    //         });

    //         $walletAmount = min($request->wallet_amount, $user->balance);
    //         $walletAmount = min($walletAmount, $total);

    //         if ($walletAmount > 0) {
    //             $user->decrement('balance', $walletAmount);
    //         }

    //         $remainingAmount = $total - $walletAmount;

    //         $order = Order::create([
    //             'order_number' => 'ORD-' . strtoupper(uniqid()),
    //             'user_id' => $user->id,
    //             'cart_id' => $cart->id,
    //             'total' => $total,
    //             'status' => $remainingAmount > 0 ? 'pending' : 'paid',
    //             'payment_method' => $request->remaining_method,
    //             'paid_at' => $remainingAmount === 0 ? now() : null,
    //             'billing_info' => [
    //                 'wallet_payment' => $walletAmount,
    //                 'remaining_payment' => $remainingAmount,
    //                 'payment_method' => $request->remaining_method,
    //             ],
    //         ]);

    //         $cart->update(['is_active' => false]);
    //         Cart::create([
    //             'user_id' => $user->id,
    //             'is_active' => true,
    //         ]);

    //         DB::commit();

    //         return redirect()->route('orders.show', $order->id)
    //             ->with('success', sprintf(
    //                 'تم الدفع %s من المحفظة. المبلغ المتبقي: %s',
    //                 $walletAmount,
    //                 $remainingAmount
    //             ));

    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         return back()->with('error', $e->getMessage());
    //     }
    // }



}
